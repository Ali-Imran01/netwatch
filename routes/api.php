<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\IpAddressController;
use App\Http\Controllers\Api\SiteController;
use App\Http\Controllers\Api\SubnetController;
use App\Http\Controllers\Api\VlanController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::apiResources([
        'sites' => SiteController::class,
        'vlans' => VlanController::class,
        'subnets' => SubnetController::class,
        'ip-addresses' => IpAddressController::class,
        'devices' => DeviceController::class,
    ]);
});
