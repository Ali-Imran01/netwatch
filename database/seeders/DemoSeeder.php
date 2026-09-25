<?php

namespace Database\Seeders;

use App\Enums\DeviceType;
use App\Enums\UserRole;
use App\Models\Circuit;
use App\Models\Device;
use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use App\Models\Provider;
use App\Models\Site;
use App\Models\Subnet;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Public demo data for "Straits Link Networks", a fictional regional carrier: 4 sites, 12 devices, 6 subnets,
 * 5 circuits across 3 providers. Every monitor uses the network-free simulator, so the demo runs anywhere and
 * shows a flapping link, a latency spike, a maintenance window and a recurring self-healing outage.
 * Two real monitors (HTTP and DNS against example.com, which is reserved for documentation) prove the engine works live.
 * Visitors cannot create monitors, so the demo never probes anything but these fixed targets.
 * Subnets come from the RFC 5737 documentation ranges. Safe to re-run.
 *
 *   php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public const VIEWER_EMAIL = 'demo@netwatch.example';

    public const VIEWER_PASSWORD = 'read-only-demo';

    private const SITES = [
        ['SIN-01', 'Singapore Core POP', 'Singapore', 'SG', 1.3521, 103.8198],
        ['KUL-01', 'Kuala Lumpur Core POP', 'Kuala Lumpur', 'MY', 3.1390, 101.6869],
        ['JKT-01', 'Jakarta Edge POP', 'Jakarta', 'ID', -6.2088, 106.8456],
        ['HKG-01', 'Hong Kong Edge POP', 'Hong Kong', 'HK', 22.3193, 114.1694],
    ];

    /** suffix, type, vendor, model */
    private const DEVICES = [
        ['core-01', DeviceType::Router, 'Juniper', 'MX204'],
        ['sw-01', DeviceType::Switch, 'Arista', '7050SX3'],
        ['fw-01', DeviceType::Firewall, 'Fortinet', 'FG-200F'],
    ];

    /** site, cidr, description */
    private const SUBNETS = [
        ['SIN-01', '192.0.2.0/26', 'SIN management'],
        ['KUL-01', '192.0.2.64/26', 'KUL management'],
        ['JKT-01', '198.51.100.0/26', 'JKT management'],
        ['HKG-01', '198.51.100.64/26', 'HKG management'],
        ['SIN-01', '203.0.113.0/25', 'SIN customer pool'],
        ['KUL-01', '203.0.113.128/25', 'KUL customer pool'],
    ];

    private const PROVIDERS = [
        ['Pacific Transit Carrier', 'noc@pacific-transit.example', '+65 5550 0101'],
        ['Meridian Backbone', 'noc@meridian-backbone.example', '+60 3 5550 0102'],
        ['Coral Bay Telecom', 'noc@coralbay.example', '+62 21 5550 0103'],
    ];

    /** provider, ref, name, type, Mbps, A-end, Z-end, SLA target, simulator scenario */
    private const CIRCUITS = [
        ['Pacific Transit Carrier', 'PTC-IPLC-0001', 'SIN–HKG IPLC', 'iplc', 10000, 'SIN-01', 'HKG-01', 99.95, 'outage_cycle'],
        ['Meridian Backbone', 'MB-IPLC-0007', 'SIN–KUL IPLC', 'iplc', 10000, 'SIN-01', 'KUL-01', 99.95, 'stable'],
        ['Meridian Backbone', 'MB-IEPL-0112', 'KUL–JKT IEPL', 'iepl', 1000, 'KUL-01', 'JKT-01', 99.9, 'latency_spike'],
        ['Coral Bay Telecom', 'CBT-IEPL-0245', 'SIN–JKT IEPL', 'iepl', 1000, 'SIN-01', 'JKT-01', 99.9, 'stable'],
        ['Coral Bay Telecom', 'CBT-DIA-0931', 'SIN Internet (DIA)', 'dia', 2000, 'SIN-01', null, 99.5, 'flapping'],
    ];

    public function run(): void
    {
        User::firstOrCreate(['email' => self::VIEWER_EMAIL], [
            'name' => 'Demo viewer', 'password' => self::VIEWER_PASSWORD, 'role' => UserRole::Viewer,
        ]);

        $sites = collect(self::SITES)->mapWithKeys(fn ($s) => [$s[0] => Site::firstOrCreate(['code' => $s[0]], [
            'name' => $s[1], 'city' => $s[2], 'country' => $s[3], 'lat' => $s[4], 'lng' => $s[5],
        ])]);

        foreach ($sites as $code => $site) {
            foreach (self::DEVICES as [$suffix, $type, $vendor, $model]) {
                $name = strtolower(substr($code, 0, 3))."-{$suffix}";
                $device = Device::firstOrCreate(['site_id' => $site->id, 'name' => $name], ['type' => $type, 'vendor' => $vendor, 'model' => $model]);
                $this->monitor("{$name} reachable", 'stable', Device::class, $device->id);
            }
        }

        foreach (self::SUBNETS as [$code, $cidr, $description]) {
            Subnet::firstOrCreate(['site_id' => $sites[$code]->id, 'cidr' => $cidr], ['description' => $description]);
        }

        $providers = collect(self::PROVIDERS)->mapWithKeys(fn ($p) => [$p[0] => Provider::firstOrCreate(['name' => $p[0]], ['noc_email' => $p[1], 'noc_phone' => $p[2]])]);

        foreach (self::CIRCUITS as [$provider, $ref, $name, $type, $mbps, $a, $z, $sla, $scenario]) {
            $circuit = Circuit::firstOrCreate(['provider_id' => $providers[$provider]->id, 'circuit_ref' => $ref], [
                'name' => $name, 'type' => $type, 'bandwidth_mbps' => $mbps, 'sla_target' => $sla,
                'a_site_id' => $sites[$a]->id, 'z_site_id' => $z ? $sites[$z]->id : null,
            ]);
            $this->monitor("{$name} path", $scenario, Circuit::class, $circuit->id);
        }

        Monitor::firstOrCreate(['name' => 'Public web check (example.com)'], ['type' => 'http', 'target' => 'https://example.com', 'interval_s' => 60, 'timeout_ms' => 5000]);
        Monitor::firstOrCreate(['name' => 'Public DNS check (example.com)'], ['type' => 'dns', 'target' => 'example.com', 'interval_s' => 60, 'timeout_ms' => 5000]);

        // A planned provider change on the SIN–KUL IPLC (tomorrow 02:00 UTC, 4 hours) and a finished one from yesterday.
        $ipl = Circuit::where('circuit_ref', 'MB-IPLC-0007')->firstOrFail();
        $tomorrow = now()->addDay()->startOfDay()->addHours(2);
        MaintenanceWindow::firstOrCreate(['circuit_id' => $ipl->id, 'provider_ref' => 'MB-CHG-20411'], ['starts_at' => $tomorrow, 'ends_at' => $tomorrow->copy()->addHours(4), 'notes' => 'Provider line-card replacement.']);
        $yesterday = now()->subDay()->startOfDay()->addHours(2);
        MaintenanceWindow::firstOrCreate(['circuit_id' => $ipl->id, 'provider_ref' => 'MB-CHG-20388'], ['starts_at' => $yesterday, 'ends_at' => $yesterday->copy()->addHours(2), 'notes' => 'Firmware upgrade.']);
    }

    private function monitor(string $name, string $scenario, string $type, int $id): void
    {
        Monitor::firstOrCreate(['name' => $name], [
            'type' => 'simulator', 'target' => $scenario, 'interval_s' => 30, 'timeout_ms' => 2000,
            'monitorable_type' => $type, 'monitorable_id' => $id,
        ]);
    }
}
