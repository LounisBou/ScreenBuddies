<?php

declare(strict_types=1);

use App\Http\Controllers\Api\HealthController;
use Illuminate\Support\Facades\Route;

// Health check (no auth required)
Route::get('health', HealthController::class);
