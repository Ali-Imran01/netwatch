<?php

use App\Enums\MonitorType;
use App\Enums\UserRole;
use App\Jobs\RunCheck;
use App\Models\CheckResult;
use App\Models\Device;
use App\Models\Monitor;
use App\Models\User;
use App\Probes\DnsProbe;
use App\Probes\HttpProbe;
use App\Probes\PingProbe;
use App\Probes\TcpProbe;
use App\Services\CheckRunner;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;

function engineer(UserRole $role = UserRole::Engineer): User
{
    $user = User::factory()->create(['role' => $role]);
    test()->actingAs($user);

    return $user;
}

$valid = ['name' => 'Core ping', 'type' => 'ping', 'target' => '192.0.2.10', 'interval_s' => 30, 'timeout_ms' => 2000];

it('lets viewers read but not write monitors', function () use ($valid) {
    engineer(UserRole::Viewer);
    $monitor = Monitor::factory()->create();

    $this->getJson('/api/monitors')->assertOk()->assertJsonPath('data.0.id', $monitor->id);
    $this->postJson('/api/monitors', $valid)->assertForbidden();
    $this->postJson("/api/monitors/{$monitor->id}/run")->assertForbidden();
    $this->deleteJson("/api/monitors/{$monitor->id}")->assertForbidden();
});

it('creates, updates and deletes a monitor linked to a device', function () use ($valid) {
    engineer();
    $device = Device::factory()->create();

    $id = $this->postJson('/api/monitors', $valid + ['device_id' => $device->id])
        ->assertCreated()->assertJsonPath('device.name', $device->name)->assertJsonPath('type', 'ping')->json('id');
    $this->putJson("/api/monitors/{$id}", [...$valid, 'name' => 'Renamed', 'device_id' => null])
        ->assertOk()->assertJsonPath('name', 'Renamed')->assertJsonPath('device', null);
    $this->deleteJson("/api/monitors/{$id}")->assertNoContent();
});

it('validates monitor input per type', function (array $override, string $field) use ($valid) {
    engineer();

    $this->postJson('/api/monitors', [...$valid, ...$override])->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'tcp needs a port' => [['type' => 'tcp'], 'port'],
    'http needs a url' => [['type' => 'http', 'target' => '192.0.2.10'], 'target'],
    'http rejects other schemes' => [['type' => 'http', 'target' => 'file:///etc/passwd'], 'target'],
    'ping target cannot be an option' => [['target' => '-f'], 'target'],
    'ping target cannot carry a shell' => [['target' => '1.1.1.1; rm -rf /'], 'target'],
    'interval has a floor' => [['interval_s' => 5], 'interval_s'],
    'timeout must be under the interval' => [['timeout_ms' => 30000], 'timeout_ms'],
]);

it('runs a ping probe and parses the round-trip time', function () {
    Process::fake(['*' => Process::result("64 bytes from 192.0.2.10: icmp_seq=1 ttl=64 time=12.6 ms\n")]);

    $result = (new PingProbe)->run(Monitor::factory()->make(['target' => '192.0.2.10']));

    expect($result->success)->toBeTrue()->and($result->latencyMs)->toBe(13);
    Process::assertRan(fn ($p) => $p->command === ['ping', '-c', '1', '-W', '2', '192.0.2.10']);
});

it('reports a failed ping when there is no reply', function () {
    Process::fake(['*' => Process::result('', '', 1)]);

    expect((new PingProbe)->run(Monitor::factory()->make())->success)->toBeFalse();
});

it('judges HTTP monitors on the status code and survives connection errors', function () {
    $monitor = Monitor::factory()->make(['type' => MonitorType::Http, 'target' => 'https://example.test']);

    Http::fake(['*' => Http::sequence()->push('ok', 200)->push('nope', 503)->pushFailedConnection()]);

    $up = (new HttpProbe)->run($monitor);
    expect($up->success)->toBeTrue()->and($up->detail)->toBe('HTTP 200');
    expect((new HttpProbe)->run($monitor)->success)->toBeFalse();
    expect((new HttpProbe)->run($monitor)->success)->toBeFalse();
});

it('checks TCP reachability against a real local socket', function () {
    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    $port = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);
    $monitor = Monitor::factory()->make(['type' => MonitorType::Tcp, 'target' => '127.0.0.1', 'port' => $port]);

    expect((new TcpProbe)->run($monitor)->success)->toBeTrue();

    fclose($server);
    expect((new TcpProbe)->run($monitor)->success)->toBeFalse();
});

it('fails a DNS check for a name that cannot resolve', function () {
    // .invalid is reserved (RFC 2606) and never resolves.
    $monitor = Monitor::factory()->make(['type' => MonitorType::Dns, 'target' => 'netwatch.invalid']);

    expect((new DnsProbe)->run($monitor)->success)->toBeFalse();
});

it('stores the result and refreshes the latest-result columns without auditing', function () {
    Process::fake(['*' => Process::result("time=4.2 ms\n")]);
    $monitor = Monitor::factory()->create();
    $audits = \App\Models\AuditLog::count();

    app(CheckRunner::class)->run($monitor);

    expect(CheckResult::where('monitor_id', $monitor->id)->where('success', true)->count())->toBe(1);
    $monitor->refresh();
    expect($monitor->last_success)->toBeTrue()->and($monitor->last_latency_ms)->toBe(4)->and($monitor->last_checked_at)->not->toBeNull();
    expect(\App\Models\AuditLog::count())->toBe($audits);
});

it('records a failure instead of throwing when a probe blows up', function () {
    Process::fake(fn () => throw new RuntimeException('boom'));
    $monitor = Monitor::factory()->create();

    app(CheckRunner::class)->run($monitor);

    expect($monitor->refresh()->last_success)->toBeFalse();
    expect(CheckResult::first()->detail)->toContain('boom');
});

it('runs a check on demand', function () {
    Process::fake(['*' => Process::result("time=1.0 ms\n")]);
    engineer();
    $monitor = Monitor::factory()->create();

    $this->postJson("/api/monitors/{$monitor->id}/run")
        ->assertOk()->assertJsonPath('result.success', true)->assertJsonPath('monitor.last_success', true);
});

it('queues only due, enabled monitors, once per interval', function () {
    Queue::fake();
    $due = Monitor::factory()->create(['next_check_at' => null]);
    $overdue = Monitor::factory()->create(['next_check_at' => now()->subMinute()]);
    Monitor::factory()->create(['next_check_at' => now()->addMinutes(5)]);
    Monitor::factory()->create(['enabled' => false]);

    $this->artisan('monitors:dispatch')->assertSuccessful();
    Queue::assertPushed(RunCheck::class, 2);
    Queue::assertPushedOn('checks', RunCheck::class);

    $this->artisan('monitors:dispatch');
    Queue::assertPushed(RunCheck::class, 2); // second tick: nothing is due yet

    expect($due->refresh()->next_check_at->isFuture())->toBeTrue();
    expect($overdue->refresh()->next_check_at->between(now()->addSeconds(25), now()->addSeconds(35)))->toBeTrue();
});

it('keeps a 30s cadence without drifting or double-queueing', function () {
    Queue::fake();
    $monitor = Monitor::factory()->create(['interval_s' => 30, 'next_check_at' => now()->addSecond()]);

    $this->artisan('monitors:dispatch'); // tick lands 1s early, inside the grace window
    Queue::assertPushed(RunCheck::class, 1);
    expect($monitor->refresh()->next_check_at->between(now()->addSeconds(30), now()->addSeconds(32)))->toBeTrue();
});

it('queues 50 due monitors in a single tick', function () {
    Queue::fake();
    Monitor::factory()->count(50)->create();

    $this->artisan('monitors:dispatch');

    Queue::assertPushed(RunCheck::class, 50);
});

it('prunes results older than the retention window', function () {
    $monitor = Monitor::factory()->create();
    CheckResult::create(['monitor_id' => $monitor->id, 'checked_at' => now()->subDays(15), 'success' => true]);
    CheckResult::create(['monitor_id' => $monitor->id, 'checked_at' => now()->subDays(2), 'success' => true]);

    $this->artisan('checks:prune')->assertSuccessful();

    expect(CheckResult::count())->toBe(1);
});
