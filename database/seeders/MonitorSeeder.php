<?php

namespace Database\Seeders;

use App\Enums\MonitorType;
use App\Models\Monitor;
use Illuminate\Database\Seeder;

/**
 * 50 monitors against hosts inside the Sail stack, for exercising the check engine
 * (php artisan db:seed --class=MonitorSeeder). All 30s cadence; every target answers, so a healthy engine shows 50 "Up".
 */
class MonitorSeeder extends Seeder
{
    public function run(): void
    {
        $targets = [
            [MonitorType::Ping, 'redis', null],
            [MonitorType::Ping, 'mysql', null],
            [MonitorType::Tcp, 'redis', 6379],
            [MonitorType::Tcp, 'mysql', 3306],
            [MonitorType::Http, 'http://reverb:8080/up', null],
            [MonitorType::Dns, 'laravel.test', null],
        ];

        for ($i = 0; $i < 50; $i++) {
            [$type, $target, $port] = $targets[$i % count($targets)];

            Monitor::create([
                'name' => sprintf('demo-%02d %s %s', $i + 1, $type->value, $target),
                'type' => $type,
                'target' => $target,
                'port' => $port,
                'interval_s' => 30,
                'timeout_ms' => 2000,
            ]);
        }
    }
}
