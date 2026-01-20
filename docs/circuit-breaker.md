# Circuit Breaker Pattern

**Implementation:** ackintosh/ganesha
**Date:** 2026-01-20

---

## Overview

The circuit breaker pattern prevents cascading failures when external services (TMDB, RAWG) become slow or unavailable. Instead of waiting for timeouts and retrying failing requests, the circuit breaker "opens" and fails fast, returning cached results or graceful errors.

---

## How It Works

### States

```
     ┌──────────────────────────────────────────┐
     │                                          │
     ▼                                          │
┌─────────┐  failure rate    ┌────────┐  timeout  │
│ CLOSED  │  exceeds 50%     │  OPEN  │──────────┘
│ (normal)│─────────────────►│(failing)│
└────┬────┘                  └────┬───┘
     │                            │
     │                            │ after 30s
     │                            ▼
     │                      ┌───────────┐
     │    success           │ HALF-OPEN │
     └──────────────────────│ (testing) │
                            └───────────┘
```

| State | Behavior |
|-------|----------|
| **Closed** | Normal operation. Requests pass through. Failures are counted. |
| **Open** | Circuit is tripped. Requests fail immediately with 503. No calls to external API. |
| **Half-Open** | After timeout, one test request is allowed. Success closes circuit; failure reopens it. |

### Transition Rules

- **Closed → Open:** When failure rate exceeds 50% within a 60-second window (minimum 10 requests)
- **Open → Half-Open:** After 30 seconds timeout
- **Half-Open → Closed:** On successful request
- **Half-Open → Open:** On failed request

---

## Configuration

Located in `config/ganesha.php`:

```php
return [
    'adapter' => 'redis',
    'redis' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => env('REDIS_PORT', 6379),
    ],
    'services' => [
        'tmdb' => [
            'failure_rate_threshold' => 50,  // % failures to trip
            'interval_to_half_open' => 30,   // seconds before retry
            'minimum_requests' => 10,        // min requests before measuring
            'time_window' => 60,             // rolling window in seconds
        ],
        'rawg' => [
            'failure_rate_threshold' => 50,
            'interval_to_half_open' => 30,
            'minimum_requests' => 10,
            'time_window' => 60,
        ],
    ],
];
```

### Parameters Explained

| Parameter | Description | Default |
|-----------|-------------|---------|
| `failure_rate_threshold` | Percentage of failures that triggers the circuit to open | 50% |
| `interval_to_half_open` | Seconds to wait before testing if service recovered | 30s |
| `minimum_requests` | Minimum requests needed before failure rate is calculated | 10 |
| `time_window` | Rolling window for counting requests/failures | 60s |

---

## Integration

### Guzzle Middleware

Ganesha integrates with Guzzle HTTP client via middleware. All HTTP calls are automatically tracked.

```php
// In GaneshaService.php
$stack = HandlerStack::create();
$stack->push(new GuzzleMiddleware($ganesha));
return new Client(['handler' => $stack]);
```

### Provider Usage

Media providers use the GaneshaService to get a circuit-protected HTTP client:

```php
class TmdbProvider
{
    public function search(string $query): PaginatedResults
    {
        try {
            $client = $this->ganeshaService->getHttpClient('tmdb');
            $response = $client->get($this->baseUrl . '/search/movie', [...]);
            // Process response
        } catch (RequestException $e) {
            throw new ApiException('SERVICE_UNAVAILABLE', 'TMDB unavailable.', 503);
        }
    }
}
```

---

## Error Handling

### When Circuit Opens

When the circuit is open, requests immediately throw a `RequestException`. The provider catches this and returns:

```json
{
  "error": {
    "code": "SERVICE_UNAVAILABLE",
    "message": "TMDB service is temporarily unavailable."
  }
}
```

HTTP Status: **503 Service Unavailable**

### Graceful Degradation

The caching layer provides a fallback:

1. **Cache hit:** Return cached results even if circuit is open
2. **Cache miss + circuit open:** Return 503 error
3. **Cache miss + circuit closed:** Call external API, cache result

---

## Monitoring

### Check Circuit Status

```php
$ganeshaService = app(GaneshaService::class);

// Check if service is available
$ganeshaService->isAvailable('tmdb');  // true/false

// Get status string
$ganeshaService->getStatus('tmdb');    // 'closed' or 'open'
```

### Health Endpoint (Future)

A `/health` endpoint can report circuit states:

```json
{
  "status": "ok",
  "circuits": {
    "tmdb": "closed",
    "rawg": "closed"
  }
}
```

---

## Storage

Circuit breaker state is stored in **Redis** for:
- Persistence across restarts
- Sharing state across multiple app instances
- Fast read/write performance

Key pattern: `ganesha:*`

---

## Testing

### Mocking in Tests

For unit tests, mock the GaneshaService or use Laravel's HTTP fake:

```php
test('handles circuit open gracefully', function () {
    $mock = Mockery::mock(GaneshaService::class);
    $mock->shouldReceive('getHttpClient')
        ->andThrow(new RequestException('Circuit open', new Request('GET', '/')));

    $this->app->instance(GaneshaService::class, $mock);

    $response = $this->getJson('/api/v1/media/search?type=movie&query=test');

    $response->assertStatus(503)
        ->assertJsonPath('error.code', 'SERVICE_UNAVAILABLE');
});
```

---

## Why Ganesha?

| Feature | Ganesha | Custom |
|---------|---------|--------|
| Guzzle Middleware | Built-in | Manual |
| Redis adapter | Built-in | Manual |
| Rate-based strategy | Yes | Manual |
| Production-proven | Yes (Yousign, etc.) | Unknown |
| Maintenance | Active | Your team |

Selected over alternatives (leyton/laravel-circuit-breaker, custom) because:
1. **Guzzle Middleware** - Transparent integration with existing HTTP calls
2. **Multiple storage backends** - Redis fits our stack
3. **Battle-tested** - Used in production at scale

---

## References

- [Ganesha GitHub](https://github.com/ackintosh/ganesha)
- [Circuit Breaker Pattern (Martin Fowler)](https://martinfowler.com/bliki/CircuitBreaker.html)
- [Release It! (Michael Nygard)](https://pragprog.com/titles/mnee2/release-it-second-edition/)
