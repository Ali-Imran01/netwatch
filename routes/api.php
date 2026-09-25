<?php

use App\Http\Controllers\Api\AlertChannelController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CircuitController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\IncidentController;
use App\Http\Controllers\Api\IpAddressController;
use App\Http\Controllers\Api\MaintenanceWindowController;
use App\Http\Controllers\Api\MonitorController;
use App\Http\Controllers\Api\ProviderController;
use App\Http\Controllers\Api\SiteController;
use App\Http\Controllers\Api\SubnetController;
use App\Http\Controllers\Api\TelegramWebhookController;
use App\Http\Controllers\Api\VlanController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

// Called by Telegram, not a browser: authenticated by its secret header instead of a session.
Route::post('/telegram/webhook', TelegramWebhookController::class)->middleware('throttle:webhook');

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // One CSV per entity; registered before the resources so "import" is not read as an id.
    foreach (['sites' => SiteController::class, 'vlans' => VlanController::class, 'subnets' => SubnetController::class,
        'ip-addresses' => IpAddressController::class, 'devices' => DeviceController::class] as $path => $controller) {
        Route::post("$path/import", [$controller, 'import'])->middleware('throttle:heavy');
    }

    Route::post('monitors/{id}/run', [MonitorController::class, 'run'])->whereNumber('id')->middleware('throttle:heavy');
    Route::get('circuits/{id}/sla', [CircuitController::class, 'sla'])->whereNumber('id');
    Route::get('incidents/summary', [IncidentController::class, 'summary']);
    Route::get('incidents', [IncidentController::class, 'index']);
    Route::get('incidents/{id}', [IncidentController::class, 'show'])->whereNumber('id');
    Route::put('incidents/{id}', [IncidentController::class, 'update'])->whereNumber('id');
    Route::post('incidents/{id}/transition', [IncidentController::class, 'transition'])->whereNumber('id');
    Route::get('incidents/{id}/rfo', [IncidentController::class, 'rfo'])->whereNumber('id')->middleware('throttle:heavy');
    Route::post('alert-channels/{id}/test', [AlertChannelController::class, 'test'])->whereNumber('id')->middleware('throttle:heavy');
    Route::get('monitors/{id}/history', [MonitorController::class, 'history'])->whereNumber('id');

    Route::apiResources([
        'monitors' => MonitorController::class,
        'alert-channels' => AlertChannelController::class,
        'providers' => ProviderController::class,
        'circuits' => CircuitController::class,
        'maintenance-windows' => MaintenanceWindowController::class,
        'sites' => SiteController::class,
        'vlans' => VlanController::class,
        'subnets' => SubnetController::class,
        'ip-addresses' => IpAddressController::class,
        'devices' => DeviceController::class,
    ]);
});
