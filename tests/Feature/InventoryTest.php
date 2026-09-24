<?php

use App\Enums\IpStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\IpAddress;
use App\Models\Site;
use App\Models\Subnet;
use App\Models\User;
use App\Models\Vlan;

function signIn(UserRole $role = UserRole::Engineer): User
{
    $user = User::factory()->create(['role' => $role]);
    test()->actingAs($user);

    return $user;
}

it('requires authentication', function () {
    $this->getJson('/api/sites')->assertUnauthorized();
});

it('lets viewers read but not write', function () {
    signIn(UserRole::Viewer);
    $site = Site::factory()->create();

    $this->getJson('/api/sites')->assertOk()->assertJsonPath('data.0.id', $site->id);
    $this->postJson('/api/sites', ['name' => 'X', 'code' => 'X-1'])->assertForbidden();
    $this->putJson("/api/sites/{$site->id}", ['name' => 'X', 'code' => 'X-1'])->assertForbidden();
    $this->deleteJson("/api/sites/{$site->id}")->assertForbidden();
});

it('creates, updates and deletes a site and audits it', function () {
    $user = signIn();

    $id = $this->postJson('/api/sites', ['name' => 'KL POP', 'code' => 'KL-01', 'country' => 'MY'])
        ->assertCreated()->json('id');
    $this->putJson("/api/sites/{$id}", ['name' => 'KL Core', 'code' => 'KL-01'])
        ->assertOk()->assertJsonPath('name', 'KL Core');
    $this->deleteJson("/api/sites/{$id}")->assertNoContent();

    expect(AuditLog::where('model', Site::class)->where('user_id', $user->id)->pluck('action')->all())
        ->toBe(['created', 'updated', 'deleted']);
});

it('rejects a duplicate site code', function () {
    signIn();
    Site::factory()->create(['code' => 'KL-01']);

    $this->postJson('/api/sites', ['name' => 'Other', 'code' => 'KL-01'])
        ->assertUnprocessable()->assertJsonValidationErrors('code');
});

it('keeps VLAN ids unique per site only', function () {
    signIn();
    $a = Site::factory()->create();
    $b = Site::factory()->create();
    Vlan::factory()->create(['site_id' => $a->id, 'vid' => 100]);

    $this->postJson('/api/vlans', ['site_id' => $a->id, 'vid' => 100, 'name' => 'dup'])->assertUnprocessable();
    $this->postJson('/api/vlans', ['site_id' => $b->id, 'vid' => 100, 'name' => 'ok'])->assertCreated();
    $this->postJson('/api/vlans', ['site_id' => $a->id, 'vid' => 4095, 'name' => 'bad'])->assertUnprocessable();
});

it('normalises a subnet CIDR to its network address', function () {
    signIn();
    $site = Site::factory()->create();

    $this->postJson('/api/subnets', ['site_id' => $site->id, 'cidr' => '10.1.2.77/24'])
        ->assertCreated()
        ->assertJsonPath('cidr', '10.1.2.0/24')
        ->assertJsonPath('usable_hosts', 254)
        ->assertJsonPath('utilization', 0);
});

it('rejects invalid, duplicate and mismatched subnets', function () {
    signIn();
    $site = Site::factory()->create();
    $otherSiteVlan = Vlan::factory()->create();
    Subnet::factory()->create(['site_id' => $site->id, 'cidr' => '10.1.2.0/24']);

    foreach (['10.1.2.0/33', '300.1.1.0/24', 'nonsense', '10.0.0.0/4'] as $cidr) {
        $this->postJson('/api/subnets', ['site_id' => $site->id, 'cidr' => $cidr])
            ->assertUnprocessable()->assertJsonValidationErrors('cidr');
    }
    // Same network written with a host bit set is still a duplicate.
    $this->postJson('/api/subnets', ['site_id' => $site->id, 'cidr' => '10.1.2.9/24'])->assertUnprocessable();
    $this->postJson('/api/subnets', ['site_id' => $site->id, 'cidr' => '10.9.0.0/24', 'vlan_id' => $otherSiteVlan->id])
        ->assertUnprocessable()->assertJsonValidationErrors('vlan_id');
    $this->postJson('/api/subnets', ['site_id' => $site->id, 'cidr' => '10.9.0.0/24', 'gateway' => '192.168.1.1'])
        ->assertUnprocessable()->assertJsonValidationErrors('gateway');
});

it('computes subnet utilization from non-free addresses', function () {
    signIn();
    $subnet = Subnet::factory()->create(['cidr' => '10.5.0.0/29']); // 6 usable hosts
    foreach ([['10.5.0.1', IpStatus::Assigned], ['10.5.0.2', IpStatus::Reserved], ['10.5.0.3', IpStatus::Free]] as [$address, $status]) {
        IpAddress::factory()->create(['subnet_id' => $subnet->id, 'address' => $address, 'status' => $status]);
    }

    $this->getJson("/api/subnets/{$subnet->id}")
        ->assertOk()
        ->assertJsonPath('usable_hosts', 6)
        ->assertJsonPath('used_count', 2)
        ->assertJsonPath('utilization', 33.3);
});

it('treats /31 and /32 as fully usable', function () {
    expect(Subnet::factory()->make(['cidr' => '10.0.0.0/31', 'prefix' => 31])->usableHosts())->toBe(2);
    expect(Subnet::factory()->make(['cidr' => '10.0.0.1/32', 'prefix' => 32])->usableHosts())->toBe(1);
});

it('validates IP addresses against their subnet', function () {
    signIn();
    $subnet = Subnet::factory()->create(['cidr' => '10.5.0.0/24']);
    $payload = fn (string $address) => ['subnet_id' => $subnet->id, 'address' => $address, 'status' => 'assigned'];

    $this->postJson('/api/ip-addresses', $payload('10.5.0.10'))->assertCreated();
    $this->postJson('/api/ip-addresses', $payload('10.5.0.10'))->assertUnprocessable();          // duplicate
    $this->postJson('/api/ip-addresses', $payload('10.6.0.10'))->assertUnprocessable();          // outside subnet
    $this->postJson('/api/ip-addresses', $payload('10.5.0.0'))->assertUnprocessable();           // network address
    $this->postJson('/api/ip-addresses', $payload('10.5.0.255'))->assertUnprocessable();         // broadcast
    $this->postJson('/api/ip-addresses', $payload('not-an-ip'))->assertUnprocessable();
});

it('links a device to its management IP', function () {
    signIn();
    $site = Site::factory()->create();
    $subnet = Subnet::factory()->create(['site_id' => $site->id, 'cidr' => '10.5.0.0/24']);
    $ip = IpAddress::factory()->create(['subnet_id' => $subnet->id, 'address' => '10.5.0.1']);

    $this->postJson('/api/devices', [
        'site_id' => $site->id, 'name' => 'core-1', 'type' => 'router', 'mgmt_ip_id' => $ip->id,
    ])->assertCreated()->assertJsonPath('mgmt_ip.address', '10.5.0.1');

    $this->postJson('/api/devices', ['site_id' => $site->id, 'name' => 'core-1', 'type' => 'router'])
        ->assertUnprocessable(); // name unique per site
    $this->postJson('/api/devices', ['site_id' => $site->id, 'name' => 'x', 'type' => 'toaster'])
        ->assertUnprocessable();
});

it('filters lists by query string', function () {
    signIn();
    $a = Site::factory()->create();
    $b = Site::factory()->create();
    Device::factory()->create(['site_id' => $a->id]);
    Device::factory()->count(2)->create(['site_id' => $b->id]);

    $this->getJson("/api/devices?site_id={$b->id}")->assertOk()->assertJsonCount(2, 'data');
});

it('refuses to delete a site that still has children', function () {
    signIn();
    $site = Site::factory()->create();
    Vlan::factory()->create(['site_id' => $site->id]);

    $this->deleteJson("/api/sites/{$site->id}")->assertStatus(409);
    expect(Site::find($site->id))->not->toBeNull();
});
