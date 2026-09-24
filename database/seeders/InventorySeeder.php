<?php

namespace Database\Seeders;

use App\Enums\DeviceType;
use App\Enums\IpStatus;
use App\Models\Device;
use App\Models\IpAddress;
use App\Models\Site;
use App\Models\Subnet;
use App\Models\Vlan;
use Illuminate\Database\Seeder;

/**
 * Demo inventory for a fictional regional carrier ("Nusantara Link"): six POPs, each with
 * management / voice / customer VLANs, a handful of devices and a customer pool at a different fill level.
 */
class InventorySeeder extends Seeder
{
    /** code, name, city, lat, lng, customer IPs in use (of 126 usable in the /25) */
    private const SITES = [
        ['KL-HQ', 'Kuala Lumpur HQ', 'Kuala Lumpur', 3.1390, 101.6869, 95],
        ['PJ-DC', 'Petaling Jaya Data Centre', 'Petaling Jaya', 3.1073, 101.6067, 60],
        ['PNG-01', 'Penang Georgetown POP', 'George Town', 5.4141, 100.3288, 112],
        ['JB-01', 'Johor Bahru POP', 'Johor Bahru', 1.4927, 103.7414, 30],
        ['KCH-01', 'Kuching POP', 'Kuching', 1.5535, 110.3593, 119],
        ['KK-01', 'Kota Kinabalu POP', 'Kota Kinabalu', 5.9804, 116.0735, 15],
    ];

    /** name suffix, type, vendor, model */
    private const DEVICES = [
        ['core-01', DeviceType::Router, 'Juniper', 'MX204'],
        ['dist-01', DeviceType::Switch, 'Cisco', 'C9500-24Y4C'],
        ['fw-01', DeviceType::Firewall, 'Fortinet', 'FG-200F'],
        ['acc-01', DeviceType::Switch, 'Cisco', 'C9300-48P'],
        ['acc-02', DeviceType::Switch, 'Cisco', 'C9300-48P'],
        ['srv-01', DeviceType::Server, 'Dell', 'R650'],
    ];

    public function run(): void
    {
        foreach (self::SITES as $i => [$code, $name, $city, $lat, $lng, $customersInUse]) {
            $n = $i + 1;
            $site = Site::create(['name' => $name, 'code' => $code, 'city' => $city, 'country' => 'MY', 'lat' => $lat, 'lng' => $lng]);

            $vlans = collect([10 => 'mgmt', 20 => 'voice', 30 => 'customers'])
                ->mapWithKeys(fn ($label, $vid) => [$vid => Vlan::create(['site_id' => $site->id, 'vid' => $vid, 'name' => $label])]);

            $mgmt = $this->subnet($site, $vlans[10], "10.$n.10.0/24", 'Device management');
            $this->subnet($site, $vlans[20], "10.$n.20.0/24", 'Voice');
            $customers = $this->subnet($site, $vlans[30], "10.$n.30.0/25", 'Customer pool');

            $this->ip($mgmt, "10.$n.10.1", IpStatus::Reserved, dns: "gw.$code");
            foreach (self::DEVICES as $d => [$suffix, $type, $vendor, $model]) {
                $device = Device::create([
                    'site_id' => $site->id, 'name' => strtolower("$code-$suffix"), 'type' => $type,
                    'vendor' => $vendor, 'model' => $model, 'serial' => sprintf('NL%02d%04d', $n, $d + 1),
                ]);
                $ip = $this->ip($mgmt, "10.$n.10.".(11 + $d), IpStatus::Assigned, $device, $device->name.'.nl.test');
                $device->update(['mgmt_ip_id' => $ip->id]);
            }

            // Customer pool: most in use are assigned, every tenth is reserved for a pending order.
            $this->ip($customers, "10.$n.30.1", IpStatus::Reserved, dns: "gw.cust.$code");
            for ($host = 2; $host <= $customersInUse; $host++) {
                $this->ip($customers, "10.$n.30.$host", $host % 10 === 0 ? IpStatus::Reserved : IpStatus::Assigned, dns: "cust-$n-$host.nl.test");
            }
        }
    }

    private function subnet(Site $site, Vlan $vlan, string $cidr, string $description): Subnet
    {
        return Subnet::create([
            'site_id' => $site->id, 'vlan_id' => $vlan->id, 'cidr' => $cidr, 'description' => $description,
            'gateway' => preg_replace('#\.0(/\d+)$#', '.1', $cidr),
        ]);
    }

    private function ip(Subnet $subnet, string $address, IpStatus $status, ?Device $device = null, ?string $dns = null): IpAddress
    {
        return IpAddress::create([
            'subnet_id' => $subnet->id, 'address' => $address, 'status' => $status,
            'device_id' => $device?->id, 'dns_name' => $dns,
        ]);
    }
}
