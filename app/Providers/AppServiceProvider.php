<?php

namespace App\Providers;

use App\Models\Device;
use App\Models\IpAddress;
use App\Models\Monitor;
use App\Models\Site;
use App\Models\Subnet;
use App\Models\Vlan;
use App\Policies\InventoryPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([Site::class, Vlan::class, Subnet::class, IpAddress::class, Device::class, Monitor::class] as $model) {
            Gate::policy($model, InventoryPolicy::class);
        }
    }
}
