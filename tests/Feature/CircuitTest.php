<?php

use App\Enums\UserRole;
use App\Models\CheckRollup;
use App\Models\Circuit;
use App\Models\Device;
use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use App\Models\Provider;
use App\Models\User;
use App\Services\SlaCalculator;

function asRole(UserRole $role = UserRole::Engineer): User
{
    $user = User::factory()->create(['role' => $role]);
    test()->actingAs($user);

    return $user;
}

/** A closed 5-minute rollup bucket starting `$minutesAgo` minutes before the top of the current hour. */
function bucket(Monitor $monitor, int $minutesAgo, int $checks, int $failures): CheckRollup
{
    return CheckRollup::create([
        'monitor_id' => $monitor->id, 'bucket_size' => 300, 'bucket_start' => now()->startOfHour()->subMinutes($minutesAgo),
        'checks' => $checks, 'failures' => $failures,
    ]);
}

it('lets viewers read but not write providers and circuits', function () {
    asRole(UserRole::Viewer);
    $circuit = Circuit::factory()->create();

    $this->getJson('/api/circuits')->assertOk()->assertJsonPath('data.0.id', $circuit->id);
    $this->postJson('/api/providers', ['name' => 'X'])->assertForbidden();
    $this->deleteJson("/api/circuits/{$circuit->id}")->assertForbidden();
    $this->postJson('/api/maintenance-windows', [])->assertForbidden();
});

it('creates a circuit and keeps the provider circuit reference unique per provider', function () {
    asRole();
    $provider = Provider::factory()->create();
    $body = ['provider_id' => $provider->id, 'circuit_ref' => 'CKT-1', 'name' => 'KL-SG IPLC', 'type' => 'iplc', 'bandwidth_mbps' => 1000, 'sla_target' => 99.95];

    $this->postJson('/api/circuits', $body)->assertCreated()->assertJsonPath('provider.name', $provider->name)->assertJsonPath('sla_30d.availability', null);
    $this->postJson('/api/circuits', $body)->assertUnprocessable()->assertJsonValidationErrors('circuit_ref');
    $this->postJson('/api/circuits', [...$body, 'provider_id' => Provider::factory()->create()->id])->assertCreated();
});

it('refuses to delete a provider that still has circuits', function () {
    asRole();
    $circuit = Circuit::factory()->create();

    $this->deleteJson("/api/providers/{$circuit->provider_id}")->assertStatus(409);
});

it('links a monitor to a circuit, not both a circuit and a device, and unlinks on circuit delete', function () {
    asRole();
    $circuit = Circuit::factory()->create();
    $device = Device::factory()->create();
    $body = ['name' => 'IPLC ping', 'type' => 'ping', 'target' => '192.0.2.1', 'interval_s' => 30, 'timeout_ms' => 2000];

    $this->postJson('/api/monitors', [...$body, 'circuit_id' => $circuit->id, 'device_id' => $device->id])->assertUnprocessable();
    $id = $this->postJson('/api/monitors', [...$body, 'circuit_id' => $circuit->id])
        ->assertCreated()->assertJsonPath('circuit.name', $circuit->name)->assertJsonPath('device', null)->json('id');

    $this->deleteJson("/api/circuits/{$circuit->id}")->assertNoContent();
    $this->getJson("/api/monitors/{$id}")->assertOk()->assertJsonPath('circuit', null);
});

it('validates maintenance windows', function (array $body) {
    asRole();

    $this->postJson('/api/maintenance-windows', $body)->assertUnprocessable();
})->with([
    'needs a target' => [['starts_at' => '2026-10-01 10:00', 'ends_at' => '2026-10-01 12:00']],
    'ends before it starts' => [['circuit_id' => 1, 'starts_at' => '2026-10-01 12:00', 'ends_at' => '2026-10-01 10:00']],
]);

it('reports window status and rejects both a circuit and a monitor', function () {
    asRole();
    $circuit = Circuit::factory()->create();
    $monitor = Monitor::factory()->create();

    $this->postJson('/api/maintenance-windows', ['circuit_id' => $circuit->id, 'monitor_id' => $monitor->id, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)])
        ->assertUnprocessable();
    $this->postJson('/api/maintenance-windows', ['circuit_id' => $circuit->id, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)])
        ->assertCreated()->assertJsonPath('status', 'scheduled');
    $this->postJson('/api/maintenance-windows', ['monitor_id' => $monitor->id, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour()])
        ->assertCreated()->assertJsonPath('status', 'active');
});

it('flags monitors in maintenance through their circuit or directly, only while the window is open', function () {
    asRole();
    $circuit = Circuit::factory()->create();
    $onCircuit = Monitor::factory()->create(['monitorable_type' => Circuit::class, 'monitorable_id' => $circuit->id]);
    $direct = Monitor::factory()->create();
    $other = Monitor::factory()->create();

    MaintenanceWindow::factory()->create(['circuit_id' => $circuit->id, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);
    MaintenanceWindow::factory()->create(['monitor_id' => $direct->id, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);
    MaintenanceWindow::factory()->create(['monitor_id' => $other->id, 'starts_at' => now()->subDay(), 'ends_at' => now()->subHour()]); // over

    expect($onCircuit->inMaintenance())->toBeTrue()->and($direct->inMaintenance())->toBeTrue()->and($other->inMaintenance())->toBeFalse();
    $this->getJson('/api/monitors')->assertOk()->assertJsonPath('data.0.in_maintenance', true)->assertJsonPath('data.2.in_maintenance', false);
});

it('excludes maintenance windows from SLA', function () {
    $this->travelTo(now()->startOfHour()->addMinutes(30));
    $circuit = Circuit::factory()->create(['sla_target' => 99.9]);
    $monitor = Monitor::factory()->create(['monitorable_type' => Circuit::class, 'monitorable_id' => $circuit->id]);

    bucket($monitor, 60, 10, 0);
    bucket($monitor, 55, 10, 0);
    bucket($monitor, 50, 10, 10); // full outage, but planned
    bucket($monitor, 45, 10, 2);  // unplanned loss
    // Exactly covers the [-50, -45) bucket.
    $window = MaintenanceWindow::factory()->create(['circuit_id' => $circuit->id, 'starts_at' => now()->startOfHour()->subMinutes(50), 'ends_at' => now()->startOfHour()->subMinutes(45)]);

    $sla = app(SlaCalculator::class)->forCircuit($circuit, now()->subDay(), now());

    // The planned outage drops out; only the 2 unplanned failures count: 28 of 30.
    expect($sla['excluded_checks'])->toBe(10)->and($sla['checks'])->toBe(30)->and($sla['failures'])->toBe(2)
        ->and($sla['availability'])->toBe(93.333)->and($sla['meets_target'])->toBeFalse();

    // A window edge that only clips a bucket excludes that whole bucket (5-minute granularity).
    $window->update(['ends_at' => now()->startOfHour()->subMinutes(44)]);
    expect(app(SlaCalculator::class)->forCircuit($circuit, now()->subDay(), now()))->toMatchArray(['excluded_checks' => 20, 'failures' => 0, 'availability' => 100.0]);
});

it('counts unplanned loss and ignores windows that belong to other circuits', function () {
    $this->travelTo(now()->startOfHour()->addMinutes(30));
    $circuit = Circuit::factory()->create(['sla_target' => 99.9]);
    $monitor = Monitor::factory()->create(['monitorable_type' => Circuit::class, 'monitorable_id' => $circuit->id]);

    bucket($monitor, 60, 10, 0);
    bucket($monitor, 55, 10, 0);
    bucket($monitor, 50, 10, 10);
    bucket($monitor, 45, 10, 2);
    MaintenanceWindow::factory()->create(['circuit_id' => Circuit::factory()->create()->id, 'starts_at' => now()->subDay(), 'ends_at' => now()]);

    $sla = app(SlaCalculator::class)->forCircuit($circuit, now()->subDay(), now());

    expect($sla['excluded_checks'])->toBe(0)->and($sla['checks'])->toBe(40)->and($sla['failures'])->toBe(12)
        ->and($sla['availability'])->toBe(70.0)->and($sla['meets_target'])->toBeFalse();
});

it('returns null availability with no data and serves the SLA endpoint', function () {
    asRole(UserRole::Viewer);
    $circuit = Circuit::factory()->create();

    $this->getJson("/api/circuits/{$circuit->id}/sla")->assertOk()->assertJsonPath('availability', null)->assertJsonPath('target', 99.9);
    $this->getJson("/api/circuits/{$circuit->id}/sla?from=2026-10-02&to=2026-10-01")->assertUnprocessable();
});
