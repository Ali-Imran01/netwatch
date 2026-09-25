<?php

namespace App\Providers;

use App\Models\AlertChannel;
use App\Models\Circuit;
use App\Models\Device;
use App\Models\Incident;
use App\Models\IpAddress;
use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use App\Models\Provider;
use App\Models\Site;
use App\Models\Subnet;
use App\Models\Vlan;
use App\Policies\AlertChannelPolicy;
use App\Policies\InventoryPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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
        foreach ([Site::class, Vlan::class, Subnet::class, IpAddress::class, Device::class, Monitor::class, Provider::class, Circuit::class, MaintenanceWindow::class, Incident::class] as $model) {
            Gate::policy($model, InventoryPolicy::class);
        }
        Gate::policy(AlertChannel::class, AlertChannelPolicy::class);

        // Sign-in: slow down password guessing per account and per address.
        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(5)->by(Str::lower((string) $r->input('email')).'|'.$r->ip()),
            Limit::perMinute(20)->by($r->ip()),
        ]);
        // Everything behind a session.
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(240)->by($r->user()?->id ?: $r->ip()));
        // Actions that probe the network, render PDFs, send messages or parse uploads.
        RateLimiter::for('heavy', fn (Request $r) => Limit::perMinute(20)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('webhook', fn (Request $r) => Limit::perMinute(60)->by($r->ip()));
    }
}
