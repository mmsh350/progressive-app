<?php

use App\Http\Controllers\Api\v1\LocationApiController;
use App\Http\Controllers\Api\v1\SubmissionApiController;
use App\Http\Controllers\Api\v1\SpinApiController;
use App\Http\Controllers\Api\v1\AnalyticsApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Location endpoints
    Route::get('states', [LocationApiController::class, 'states']);
    Route::get('lgas', [LocationApiController::class, 'lgas']);

    // Submission endpoints
    Route::post('submit', [SubmissionApiController::class, 'submit'])->middleware('throttle:60,1');
    Route::get('status/{reference}', [SubmissionApiController::class, 'status']);

    // Spin drawing endpoint
    Route::post('spin', [SpinApiController::class, 'spin'])->middleware('throttle:30,1');

    // Analytics KPIs
    Route::get('dashboard-stats', [AnalyticsApiController::class, 'stats']);
});
