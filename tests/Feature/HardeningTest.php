<?php

use App\Enums\IncidentState;
use App\Enums\MonitorState;
use App\Enums\UserRole;
use App\Models\Circuit;
use App\Models\Device;
use App\Models\Incident;
use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use App\Models\Provider;
use App\Models\Site;
use App\Models\User;
use App\Probes\SimulatorProbe;
use App\Services\CheckRunner;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Process;

// ---- rate limiting

it('throttles repeated failed sign-ins per account', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnprocessable();
    }

    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])->assertTooManyRequests();
    // Even the right password is refused while locked out.
    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertTooManyRequests();
});

it('limits heavy actions such as running checks on demand', function () {
    Process::fake(['*' => Process::result("time=1 ms\n")]);
    $this->actingAs(User::factory()->create(['role' => UserRole::Engineer]));
    $monitor = Monitor::factory()->create();

    for ($i = 0; $i < 20; $i++) {
        $this->postJson("/api/monitors/{$monitor->id}/run")->assertOk();
    }

    $this->postJson("/api/monitors/{$monitor->id}/run")->assertTooManyRequests();
});

it('answers an unauthenticated API call with 401 even without a JSON Accept header', function () {
    $this->get('/api/user')->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
    $this->get('/api/monitors')->assertUnauthorized();
});

// ---- authorization matrix

dataset('resources', ['sites', 'vlans', 'subnets', 'ip-addresses', 'devices', 'monitors', 'providers', 'circuits', 'maintenance-windows']);

it('requires a session for every resource', function (string $path) {
    $this->getJson("/api/{$path}")->assertUnauthorized();
    $this->postJson("/api/{$path}", [])->assertUnauthorized();
    $this->putJson("/api/{$path}/1", [])->assertUnauthorized();
    $this->deleteJson("/api/{$path}/1")->assertUnauthorized();
})->with('resources');

it('gives viewers read access but no write access on every resource', function (string $path) {
    $this->actingAs(User::factory()->create(['role' => UserRole::Viewer]));

    $this->getJson("/api/{$path}")->assertOk();
    $this->postJson("/api/{$path}", [])->assertForbidden();
    // Existence must not change the answer: a viewer is refused whether or not the record is there.
    $this->putJson("/api/{$path}/999999", [])->assertStatus(404);
    $this->deleteJson("/api/{$path}/999999")->assertStatus(404);
})->with('resources');

it('refuses viewers on existing records', function (string $path, Closure $make) {
    $this->actingAs(User::factory()->create(['role' => UserRole::Viewer]));
    $id = $make()->id;

    $this->putJson("/api/{$path}/{$id}", [])->assertForbidden();
    $this->deleteJson("/api/{$path}/{$id}")->assertForbidden();
})->with([
    'sites' => ['sites', fn () => Site::factory()->create()],
    'devices' => ['devices', fn () => Device::factory()->create()],
    'monitors' => ['monitors', fn () => Monitor::factory()->create()],
    'providers' => ['providers', fn () => Provider::factory()->create()],
    'circuits' => ['circuits', fn () => Circuit::factory()->create()],
    'maintenance windows' => ['maintenance-windows', fn () => MaintenanceWindow::factory()->create(['circuit_id' => Circuit::factory()])],
]);

it('keeps alert channels away from viewers entirely', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Viewer]));

    $channel = \App\Models\AlertChannel::factory()->create();

    $this->getJson('/api/alert-channels')->assertForbidden();
    $this->postJson("/api/alert-channels/{$channel->id}/test")->assertForbidden();
});

it('keeps incident and RFO actions away from viewers', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Viewer]));
    $incident = Incident::factory()->create(['state' => IncidentState::Resolved]);

    $this->postJson("/api/incidents/{$incident->id}/transition", ['to' => 'closed'])->assertForbidden();
    $this->putJson("/api/incidents/{$incident->id}", ['rfo_summary' => 'x'])->assertForbidden();
    $this->get("/api/incidents/{$incident->id}/rfo")->assertOk(); // reading the report is allowed
});

it('does not let an engineer manage users or alert channels', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Engineer]));

    $this->postJson('/api/alert-channels', ['name' => 'x', 'type' => 'email', 'target' => 'a@b.example'])->assertForbidden();
});

// ---- simulator

it('validates simulator monitors against the known scenarios', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Engineer]));
    $body = ['name' => 'Sim', 'type' => 'simulator', 'interval_s' => 30, 'timeout_ms' => 2000];

    $this->postJson('/api/monitors', [...$body, 'target' => 'outage_cycle'])->assertCreated();
    $this->postJson('/api/monitors', [...$body, 'target' => 'made_up'])->assertUnprocessable()->assertJsonValidationErrors('target');
});

/** Run a simulator monitor through the real engine for `$slots` checks, 30s apart; returns per-slot states. */
function simulate(Monitor $monitor, int $slots): array
{
    $states = [];
    for ($i = 0; $i < $slots; $i++) {
        test()->travel(30)->seconds();
        app(CheckRunner::class)->run($monitor);
        $states[] = $monitor->refresh()->state;
    }

    return $states;
}

it('never lets the flapping scenario reach Down, thanks to flap protection', function () {
    $monitor = Monitor::factory()->create(['type' => 'simulator', 'target' => 'flapping']);

    $states = simulate($monitor, 40);

    expect($states)->not->toContain(MonitorState::Down)->and(Incident::count())->toBe(0);
    expect($monitor->results()->where('success', false)->count())->toBeGreaterThan(20); // it really was failing a lot
});

it('drives the outage_cycle scenario through an incident that opens and resolves itself', function () {
    $monitor = Monitor::factory()->create(['type' => 'simulator', 'target' => 'outage_cycle']);
    // Start exactly where this monitor's 30-minute cycle begins, i.e. at the start of its outage, so the run is deterministic.
    $base = 1_800_000_000;
    $this->travelTo(\Illuminate\Support\Carbon::createFromTimestamp($base - (($base + $monitor->id * 137) % 1800)));

    $states = simulate($monitor, 60); // 30 minutes: the outage, then recovery

    expect($states)->toContain(MonitorState::Down)->and(end($states))->toBe(MonitorState::Up);
    $incident = Incident::sole();
    expect($incident->state)->toBe(IncidentState::Resolved)->and($incident->timeToResolve())->toBeGreaterThan(4 * 60)->toBeLessThan(10 * 60);
});

it('spikes latency for a while every cycle in the latency_spike scenario', function () {
    $monitor = Monitor::factory()->create(['id' => 7, 'type' => 'simulator', 'target' => 'latency_spike']);
    $probe = new SimulatorProbe;

    $latencies = collect(range(0, 1799, 30))->map(fn ($t) => $probe->at($monitor, 1_700_000_000 + $t)->latencyMs);

    expect($latencies->max())->toBeGreaterThan(200)->and($latencies->min())->toBeLessThan(40)
        ->and($latencies->filter(fn ($ms) => $ms > 200)->count())->toBe(10); // 5 minutes of 30-second checks
});

// ---- demo data and users

it('seeds the demo carrier once, however many times it runs', function () {
    $this->seed(DemoSeeder::class);
    $this->seed(DemoSeeder::class);

    expect(Site::count())->toBe(4)->and(Device::count())->toBe(12)->and(Provider::count())->toBe(3)->and(Circuit::count())->toBe(5)
        ->and(\App\Models\Subnet::count())->toBe(6)->and(Monitor::count())->toBe(19)->and(MaintenanceWindow::count())->toBe(2);
    expect(Monitor::where('type', 'simulator')->count())->toBe(17)->and(Monitor::whereIn('type', ['http', 'dns'])->count())->toBe(2);
    expect(Monitor::where('monitorable_type', Circuit::class)->count())->toBe(5);
});

it('gives the demo account read-only access', function () {
    $this->seed(DemoSeeder::class);

    $this->withHeader('Referer', 'http://localhost:5173')->postJson('/api/login', ['email' => DemoSeeder::VIEWER_EMAIL, 'password' => DemoSeeder::VIEWER_PASSWORD])->assertOk()->assertJsonPath('user.role', 'viewer');
    $this->getJson('/api/circuits')->assertOk()->assertJsonCount(5, 'data');
    $this->postJson('/api/monitors', ['name' => 'Mine', 'type' => 'http', 'target' => 'https://attacker.example', 'interval_s' => 30, 'timeout_ms' => 2000])->assertForbidden();
    $this->postJson('/api/sites', ['name' => 'X', 'code' => 'X-1'])->assertForbidden();
    $this->postJson('/api/monitors/1/run')->assertForbidden();
});

it('creates users from the command line and enforces a strong password', function () {
    $this->artisan('netwatch:user', ['email' => 'ops@example.test', 'name' => 'Ops', '--role' => 'admin', '--password' => 'short'])->assertFailed();
    expect(User::where('email', 'ops@example.test')->exists())->toBeFalse();

    $this->artisan('netwatch:user', ['email' => 'ops@example.test', 'name' => 'Ops', '--role' => 'admin', '--password' => 'a-long-enough-password'])->assertSuccessful();
    expect(User::where('email', 'ops@example.test')->first())->role->toBe(UserRole::Admin);

    $this->artisan('netwatch:user', ['email' => 'ops@example.test', 'name' => 'Again', '--password' => 'a-long-enough-password'])->assertFailed();
    $this->artisan('netwatch:user', ['email' => 'x@example.test', 'name' => 'X', '--role' => 'root', '--password' => 'a-long-enough-password'])->assertFailed();
});
