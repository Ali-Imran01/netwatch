<?php

use App\Enums\MonitorState;
use App\Enums\UserRole;
use App\Events\MonitorChecked;
use App\Models\CheckResult;
use App\Models\CheckRollup;
use App\Models\Monitor;
use App\Models\User;
use App\Services\StatusEvaluator;
use Illuminate\Support\Facades\Event;

/** Feed pass/fail results through the evaluator and return the monitor's state after each. */
function feed(Monitor $monitor, string $pattern): array
{
    $states = [];
    foreach (str_split($pattern) as $c) {
        $result = CheckResult::create(['monitor_id' => $monitor->id, 'checked_at' => now(), 'success' => $c === 'p', 'latency_ms' => $c === 'p' ? 10 : null]);
        app(StatusEvaluator::class)->apply($monitor, $result);
        $states[] = $monitor->state->value;
    }

    return $states;
}

it('does not go Down until 3 consecutive failures', function () {
    Event::fake();
    $monitor = Monitor::factory()->create();

    expect(feed($monitor, 'pff'))->toBe(['up', 'up', 'up']);
    expect(feed($monitor, 'f'))->toBe(['down']);
    expect($monitor->refresh()->state_changed_at)->not->toBeNull();
});

it('resets the failure count on a pass, so a flapping link stays Up', function () {
    Event::fake();
    $monitor = Monitor::factory()->create();

    expect(feed($monitor, 'pffpffpff'))->each->toBe('up')->and($monitor->refresh()->state)->toBe(MonitorState::Up);
});

it('needs 2 consecutive passes to recover from Down', function () {
    Event::fake();
    $monitor = Monitor::factory()->create(['state' => 'down']);
    $monitor->forceFill(['state' => 'down'])->save();

    expect(feed($monitor, 'pfpp'))->toBe(['down', 'down', 'down', 'up']);
});

it('marks an unknown monitor Up on its first pass, or Down after 3 failures', function () {
    Event::fake();

    expect(feed(Monitor::factory()->create(), 'p'))->toBe(['up']);
    expect(feed(Monitor::factory()->create(), 'fff'))->toBe(['unknown', 'unknown', 'down']);
});

it('honours per-monitor thresholds', function () {
    Event::fake();
    $monitor = Monitor::factory()->create(['down_after' => 1]);
    $monitor->forceFill(['down_after' => 1, 'state' => 'up'])->save();

    expect(feed($monitor, 'f'))->toBe(['down']);
});

it('broadcasts every check and flags state changes', function () {
    Event::fake([MonitorChecked::class]);
    $monitor = Monitor::factory()->create();

    feed($monitor, 'p');
    feed($monitor, 'p');

    Event::assertDispatchedTimes(MonitorChecked::class, 2);
    Event::assertDispatched(MonitorChecked::class, fn ($e) => $e->stateChanged === true);
    Event::assertDispatched(MonitorChecked::class, fn ($e) => $e->stateChanged === false);
    expect((new MonitorChecked($monitor, true))->broadcastWith())->toMatchArray(['id' => $monitor->id, 'state' => 'up', 'state_changed' => true]);
});

it('rolls up closed buckets only, and is idempotent', function () {
    $this->travelTo(now()->startOfHour()->addMinutes(7)->addSeconds(30));
    $monitor = Monitor::factory()->create();
    $bucket = now()->startOfHour();
    $add = fn (int $min, bool $ok, ?int $ms) => CheckResult::create(['monitor_id' => $monitor->id, 'checked_at' => $bucket->copy()->addMinutes($min)->addSeconds(5), 'success' => $ok, 'latency_ms' => $ms]);

    $add(1, true, 10);
    $add(2, true, 20);
    $add(3, true, 30);
    $add(4, false, null);
    $add(6, true, 99); // 12:05 bucket is still open

    $this->artisan('checks:rollup')->assertSuccessful();
    $this->artisan('checks:rollup')->assertSuccessful();

    $rollup = CheckRollup::where('bucket_size', 300)->sole();
    expect($rollup->bucket_start->equalTo($bucket))->toBeTrue()
        ->and([$rollup->checks, $rollup->failures, $rollup->avg_latency_ms, $rollup->p95_latency_ms])->toBe([4, 1, 20, 30]);
});

it('serves chart history from raw results (1h) and rollups (24h)', function () {
    $user = User::factory()->create(['role' => UserRole::Viewer]);
    $this->actingAs($user);
    $monitor = Monitor::factory()->create();
    CheckResult::create(['monitor_id' => $monitor->id, 'checked_at' => now()->subMinutes(5), 'success' => true, 'latency_ms' => 12]);
    CheckRollup::create(['monitor_id' => $monitor->id, 'bucket_start' => now()->subHours(2), 'bucket_size' => 300, 'checks' => 10, 'failures' => 1, 'avg_latency_ms' => 15, 'p95_latency_ms' => 40]);

    $this->getJson("/api/monitors/{$monitor->id}/history")->assertOk()
        ->assertJsonPath('range', '1h')->assertJsonCount(1, 'points')->assertJsonPath('points.0.avg', 12);
    $this->getJson("/api/monitors/{$monitor->id}/history?range=24h")->assertOk()
        ->assertJsonCount(1, 'points')->assertJsonPath('points.0.p95', 40)->assertJsonPath('points.0.failures', 1);
    $this->getJson("/api/monitors/{$monitor->id}/history?range=99y")->assertUnprocessable();
});

it('exposes state on the monitor API', function () {
    $this->actingAs(User::factory()->create());
    Monitor::factory()->create();

    $this->getJson('/api/monitors')->assertOk()->assertJsonPath('data.0.state', 'unknown')->assertJsonPath('data.0.down_after', 3);
});
