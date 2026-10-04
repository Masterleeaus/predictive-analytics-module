<?php

use App\Http\Controllers\PredictiveAnalyticsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Customer churn predictive analytics endpoint.
// Authenticated application mode only; this repository has no public demo mode.
Route::post('/predict-churn', [PredictiveAnalyticsController::class, 'predictChurn'])
    ->middleware('auth:sanctum');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
