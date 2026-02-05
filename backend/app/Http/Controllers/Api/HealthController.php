<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
        ];

        $healthy = $checks['database'] && $checks['redis'];
        $status = $healthy ? 'ok' : 'degraded';

        return response()->json([
            'status' => $status,
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }

    private function checkDatabase(): bool
    {
        try {
            DB::select('SELECT 1');

            return true;
        } catch (\Throwable $e) {
            Log::error('Health check: Database connection failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'connection' => config('database.default'),
            ]);

            return false;
        }
    }

    private function checkRedis(): bool
    {
        try {
            Cache::store('redis')->put('health_check', true, 10);
            $retrieved = Cache::store('redis')->get('health_check');

            if ($retrieved !== true) {
                Log::warning('Health check: Redis write-read verification failed', [
                    'written' => true,
                    'retrieved' => $retrieved,
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Health check: Redis connection failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'store' => 'redis',
            ]);

            return false;
        }
    }
}
