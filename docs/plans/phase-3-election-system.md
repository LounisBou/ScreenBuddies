# Phase 3: Election System

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implement election creation, candidate management, invitation system, and media search integration.

**Architecture:** ElectionService handles business logic. MediaSearchService provides unified interface for external APIs (TMDB, RAWG, BGG). Magic links via signed URLs.

**Tech Stack:** Laravel 11, Guzzle HTTP, Laravel Cache, API Resources

**Prerequisites:** Phase 2 complete (auth, user management)

---

## Task 1: Create Election Config

**Files:**
- Create: `backend/config/election.php`
- Create: `backend/config/media.php`

**Step 1: Create election config**

Create `backend/config/election.php`:
```php
<?php

return [
    'candidates' => [
        'min' => 2,
        'max' => 30,
    ],
    'winners' => [
        'min' => 1,
        'max' => 5,
    ],
    'archive_after_hours' => 24,
];
```

**Step 2: Create media config**

Create `backend/config/media.php`:
```php
<?php

return [
    'tmdb' => [
        'api_key' => env('TMDB_API_KEY'),
        'base_url' => 'https://api.themoviedb.org/3',
        'image_base_url' => 'https://image.tmdb.org/t/p/w500',
    ],
    'rawg' => [
        'api_key' => env('RAWG_API_KEY'),
        'base_url' => 'https://api.rawg.io/api',
    ],
    'bgg' => [
        'base_url' => 'https://boardgamegeek.com/xmlapi2',
    ],
    'cache_ttl' => [
        'search' => 3600, // 1 hour
        'details' => 86400, // 24 hours
    ],
];
```

**Step 3: Add env variables**

Add to `.env.example`:
```env
TMDB_API_KEY=your-tmdb-key
RAWG_API_KEY=your-rawg-key
```

**Step 4: Commit**

```bash
git add .
git commit -m "chore: add election and media config"
```

---

## Task 2: Create Media Provider Interface and DTOs

**Files:**
- Create: `backend/app/Services/Media/Contracts/MediaProviderInterface.php`
- Create: `backend/app/Services/Media/DTOs/MediaItem.php`
- Create: `backend/app/Services/Media/DTOs/PaginatedResults.php`

**Step 1: Create MediaItem DTO**

Create `backend/app/Services/Media/DTOs/MediaItem.php`:
```php
<?php

namespace App\Services\Media\DTOs;

class MediaItem
{
    public function __construct(
        public string $externalId,
        public string $title,
        public ?string $posterUrl,
        public ?int $year,
        public array $metadata = []
    ) {}

    public function toArray(): array
    {
        return [
            'external_id' => $this->externalId,
            'title' => $this->title,
            'poster_url' => $this->posterUrl,
            'year' => $this->year,
            'metadata' => $this->metadata,
        ];
    }
}
```

**Step 2: Create PaginatedResults DTO**

Create `backend/app/Services/Media/DTOs/PaginatedResults.php`:
```php
<?php

namespace App\Services\Media\DTOs;

class PaginatedResults
{
    public function __construct(
        public array $items,
        public int $currentPage,
        public int $totalPages,
        public int $totalResults
    ) {}

    public function toArray(): array
    {
        return [
            'items' => array_map(fn (MediaItem $item) => $item->toArray(), $this->items),
            'current_page' => $this->currentPage,
            'total_pages' => $this->totalPages,
            'total_results' => $this->totalResults,
        ];
    }
}
```

**Step 3: Create MediaProviderInterface**

Create `backend/app/Services/Media/Contracts/MediaProviderInterface.php`:
```php
<?php

namespace App\Services\Media\Contracts;

use App\Services\Media\DTOs\MediaItem;
use App\Services\Media\DTOs\PaginatedResults;

interface MediaProviderInterface
{
    public function search(string $query, int $page = 1): PaginatedResults;

    public function getById(string $externalId): ?MediaItem;

    public function getType(): string;
}
```

**Step 4: Commit**

```bash
git add .
git commit -m "feat: add media provider interface and DTOs"
```

---

## Task 3: Create TMDB Provider

**Files:**
- Create: `backend/app/Services/Media/Providers/TmdbProvider.php`
- Create: `backend/tests/Unit/Services/Media/TmdbProviderTest.php`

**Step 1: Write TMDB provider test**

Create `backend/tests/Unit/Services/Media/TmdbProviderTest.php`:
```php
<?php

use App\Services\Media\Providers\TmdbProvider;
use App\Services\Media\DTOs\PaginatedResults;
use Illuminate\Support\Facades\Http;

test('tmdb provider returns movie type', function () {
    $provider = new TmdbProvider('movie');

    expect($provider->getType())->toBe('movie');
});

test('tmdb provider returns tvshow type', function () {
    $provider = new TmdbProvider('tvshow');

    expect($provider->getType())->toBe('tvshow');
});

test('tmdb provider can search movies', function () {
    Http::fake([
        'api.themoviedb.org/*' => Http::response([
            'page' => 1,
            'total_pages' => 1,
            'total_results' => 1,
            'results' => [
                [
                    'id' => 603,
                    'title' => 'The Matrix',
                    'poster_path' => '/poster.jpg',
                    'release_date' => '1999-03-30',
                    'overview' => 'A computer hacker learns...',
                    'vote_average' => 8.7,
                    'genre_ids' => [28, 878],
                ],
            ],
        ]),
    ]);

    $provider = new TmdbProvider('movie');
    $results = $provider->search('matrix');

    expect($results)->toBeInstanceOf(PaginatedResults::class);
    expect($results->items)->toHaveCount(1);
    expect($results->items[0]->title)->toBe('The Matrix');
    expect($results->items[0]->externalId)->toBe('tmdb:603');
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test tests/Unit/Services/Media/TmdbProviderTest.php
```
Expected: FAIL

**Step 3: Create TmdbProvider**

Create `backend/app/Services/Media/Providers/TmdbProvider.php`:
```php
<?php

namespace App\Services\Media\Providers;

use App\Services\Media\Contracts\MediaProviderInterface;
use App\Services\Media\DTOs\MediaItem;
use App\Services\Media\DTOs\PaginatedResults;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TmdbProvider implements MediaProviderInterface
{
    private string $baseUrl;
    private string $apiKey;
    private string $imageBaseUrl;

    public function __construct(
        private string $type = 'movie'
    ) {
        $this->baseUrl = config('media.tmdb.base_url');
        $this->apiKey = config('media.tmdb.api_key');
        $this->imageBaseUrl = config('media.tmdb.image_base_url');
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function search(string $query, int $page = 1): PaginatedResults
    {
        $cacheKey = "media:tmdb:{$this->type}:search:" . md5($query) . ":$page";

        return Cache::remember($cacheKey, config('media.cache_ttl.search'), function () use ($query, $page) {
            $endpoint = $this->type === 'movie' ? '/search/movie' : '/search/tv';

            $response = Http::get($this->baseUrl . $endpoint, [
                'api_key' => $this->apiKey,
                'query' => $query,
                'page' => $page,
            ]);

            $data = $response->json();

            $items = array_map(
                fn ($item) => $this->mapToMediaItem($item),
                $data['results'] ?? []
            );

            return new PaginatedResults(
                items: $items,
                currentPage: $data['page'] ?? 1,
                totalPages: $data['total_pages'] ?? 1,
                totalResults: $data['total_results'] ?? 0
            );
        });
    }

    public function getById(string $externalId): ?MediaItem
    {
        $id = str_replace('tmdb:', '', $externalId);
        $cacheKey = "media:tmdb:{$this->type}:item:$id";

        return Cache::remember($cacheKey, config('media.cache_ttl.details'), function () use ($id) {
            $endpoint = $this->type === 'movie' ? "/movie/$id" : "/tv/$id";

            $response = Http::get($this->baseUrl . $endpoint, [
                'api_key' => $this->apiKey,
            ]);

            if ($response->failed()) {
                return null;
            }

            return $this->mapToMediaItem($response->json());
        });
    }

    private function mapToMediaItem(array $data): MediaItem
    {
        $isMovie = $this->type === 'movie';

        return new MediaItem(
            externalId: 'tmdb:' . $data['id'],
            title: $data[$isMovie ? 'title' : 'name'] ?? 'Unknown',
            posterUrl: isset($data['poster_path'])
                ? $this->imageBaseUrl . $data['poster_path']
                : null,
            year: $this->extractYear($data[$isMovie ? 'release_date' : 'first_air_date'] ?? null),
            metadata: [
                'overview' => $data['overview'] ?? null,
                'rating' => $data['vote_average'] ?? null,
                'genre_ids' => $data['genre_ids'] ?? [],
            ]
        );
    }

    private function extractYear(?string $date): ?int
    {
        if (!$date) {
            return null;
        }

        $parts = explode('-', $date);
        return isset($parts[0]) ? (int) $parts[0] : null;
    }
}
```

**Step 4: Run tests**

```bash
php artisan test tests/Unit/Services/Media/TmdbProviderTest.php
```
Expected: PASS

**Step 5: Commit**

```bash
git add .
git commit -m "feat: add TMDB media provider"
```

---

## Task 4: Create RAWG Provider

**Files:**
- Create: `backend/app/Services/Media/Providers/RawgProvider.php`
- Create: `backend/tests/Unit/Services/Media/RawgProviderTest.php`

**Step 1: Write RAWG provider test**

Create `backend/tests/Unit/Services/Media/RawgProviderTest.php`:
```php
<?php

use App\Services\Media\Providers\RawgProvider;
use App\Services\Media\DTOs\PaginatedResults;
use Illuminate\Support\Facades\Http;

test('rawg provider returns videogame type', function () {
    $provider = new RawgProvider();

    expect($provider->getType())->toBe('videogame');
});

test('rawg provider can search games', function () {
    Http::fake([
        'api.rawg.io/*' => Http::response([
            'count' => 100,
            'results' => [
                [
                    'id' => 3498,
                    'name' => 'Grand Theft Auto V',
                    'background_image' => 'https://example.com/gta5.jpg',
                    'released' => '2013-09-17',
                    'rating' => 4.47,
                    'genres' => [['name' => 'Action']],
                ],
            ],
        ]),
    ]);

    $provider = new RawgProvider();
    $results = $provider->search('gta');

    expect($results)->toBeInstanceOf(PaginatedResults::class);
    expect($results->items)->toHaveCount(1);
    expect($results->items[0]->title)->toBe('Grand Theft Auto V');
    expect($results->items[0]->externalId)->toBe('rawg:3498');
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test tests/Unit/Services/Media/RawgProviderTest.php
```
Expected: FAIL

**Step 3: Create RawgProvider**

Create `backend/app/Services/Media/Providers/RawgProvider.php`:
```php
<?php

namespace App\Services\Media\Providers;

use App\Services\Media\Contracts\MediaProviderInterface;
use App\Services\Media\DTOs\MediaItem;
use App\Services\Media\DTOs\PaginatedResults;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class RawgProvider implements MediaProviderInterface
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('media.rawg.base_url');
        $this->apiKey = config('media.rawg.api_key');
    }

    public function getType(): string
    {
        return 'videogame';
    }

    public function search(string $query, int $page = 1): PaginatedResults
    {
        $cacheKey = "media:rawg:search:" . md5($query) . ":$page";
        $pageSize = 20;

        return Cache::remember($cacheKey, config('media.cache_ttl.search'), function () use ($query, $page, $pageSize) {
            $response = Http::get($this->baseUrl . '/games', [
                'key' => $this->apiKey,
                'search' => $query,
                'page' => $page,
                'page_size' => $pageSize,
            ]);

            $data = $response->json();
            $totalResults = $data['count'] ?? 0;

            $items = array_map(
                fn ($item) => $this->mapToMediaItem($item),
                $data['results'] ?? []
            );

            return new PaginatedResults(
                items: $items,
                currentPage: $page,
                totalPages: (int) ceil($totalResults / $pageSize),
                totalResults: $totalResults
            );
        });
    }

    public function getById(string $externalId): ?MediaItem
    {
        $id = str_replace('rawg:', '', $externalId);
        $cacheKey = "media:rawg:item:$id";

        return Cache::remember($cacheKey, config('media.cache_ttl.details'), function () use ($id) {
            $response = Http::get($this->baseUrl . "/games/$id", [
                'key' => $this->apiKey,
            ]);

            if ($response->failed()) {
                return null;
            }

            return $this->mapToMediaItem($response->json());
        });
    }

    private function mapToMediaItem(array $data): MediaItem
    {
        return new MediaItem(
            externalId: 'rawg:' . $data['id'],
            title: $data['name'] ?? 'Unknown',
            posterUrl: $data['background_image'] ?? null,
            year: $this->extractYear($data['released'] ?? null),
            metadata: [
                'rating' => $data['rating'] ?? null,
                'genres' => array_map(fn ($g) => $g['name'], $data['genres'] ?? []),
                'platforms' => array_map(fn ($p) => $p['platform']['name'] ?? null, $data['platforms'] ?? []),
            ]
        );
    }

    private function extractYear(?string $date): ?int
    {
        if (!$date) {
            return null;
        }

        $parts = explode('-', $date);
        return isset($parts[0]) ? (int) $parts[0] : null;
    }
}
```

**Step 4: Run tests**

```bash
php artisan test tests/Unit/Services/Media/RawgProviderTest.php
```
Expected: PASS

**Step 5: Commit**

```bash
git add .
git commit -m "feat: add RAWG media provider"
```

---

## Task 5: Create MediaSearchService

**Files:**
- Create: `backend/app/Services/Media/MediaSearchService.php`
- Create: `backend/tests/Unit/Services/Media/MediaSearchServiceTest.php`

**Step 1: Write MediaSearchService test**

Create `backend/tests/Unit/Services/Media/MediaSearchServiceTest.php`:
```php
<?php

use App\Services\Media\MediaSearchService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake([
        'api.themoviedb.org/*' => Http::response([
            'page' => 1,
            'total_pages' => 1,
            'total_results' => 1,
            'results' => [
                ['id' => 1, 'title' => 'Test Movie', 'poster_path' => null, 'release_date' => '2024-01-01'],
            ],
        ]),
    ]);
});

test('media search service can search by type', function () {
    $service = new MediaSearchService();
    $results = $service->search('movie', 'test');

    expect($results->items)->toHaveCount(1);
});

test('media search service throws for invalid type', function () {
    $service = new MediaSearchService();

    expect(fn () => $service->search('invalid', 'test'))
        ->toThrow(\InvalidArgumentException::class);
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test tests/Unit/Services/Media/MediaSearchServiceTest.php
```
Expected: FAIL

**Step 3: Create MediaSearchService**

Create `backend/app/Services/Media/MediaSearchService.php`:
```php
<?php

namespace App\Services\Media;

use App\Services\Media\Contracts\MediaProviderInterface;
use App\Services\Media\DTOs\MediaItem;
use App\Services\Media\DTOs\PaginatedResults;
use App\Services\Media\Providers\RawgProvider;
use App\Services\Media\Providers\TmdbProvider;
use InvalidArgumentException;

class MediaSearchService
{
    private array $providers = [];

    public function __construct()
    {
        $this->providers = [
            'movie' => new TmdbProvider('movie'),
            'tvshow' => new TmdbProvider('tvshow'),
            'videogame' => new RawgProvider(),
            // 'boardgame' => new BggProvider(), // TODO: implement
        ];
    }

    public function search(string $type, string $query, int $page = 1): PaginatedResults
    {
        $provider = $this->getProvider($type);

        return $provider->search($query, $page);
    }

    public function getById(string $type, string $externalId): ?MediaItem
    {
        $provider = $this->getProvider($type);

        return $provider->getById($externalId);
    }

    public function getSupportedTypes(): array
    {
        return array_keys($this->providers);
    }

    private function getProvider(string $type): MediaProviderInterface
    {
        if (!isset($this->providers[$type])) {
            throw new InvalidArgumentException("Unsupported media type: $type");
        }

        return $this->providers[$type];
    }
}
```

**Step 4: Run tests**

```bash
php artisan test tests/Unit/Services/Media/MediaSearchServiceTest.php
```
Expected: PASS

**Step 5: Commit**

```bash
git add .
git commit -m "feat: add MediaSearchService"
```

---

## Task 6: Create Media Search Controller

**Files:**
- Create: `backend/app/Http/Controllers/Api/V1/MediaSearchController.php`
- Modify: `backend/routes/api.php`
- Create: `backend/tests/Feature/Media/MediaSearchTest.php`

**Step 1: Write media search feature test**

Create `backend/tests/Feature/Media/MediaSearchTest.php`:
```php
<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tymon\JWTAuth\Facades\JWTAuth;

beforeEach(function () {
    Http::fake([
        'api.themoviedb.org/*' => Http::response([
            'page' => 1,
            'total_pages' => 5,
            'total_results' => 100,
            'results' => [
                [
                    'id' => 603,
                    'title' => 'The Matrix',
                    'poster_path' => '/poster.jpg',
                    'release_date' => '1999-03-30',
                    'overview' => 'A computer hacker learns...',
                    'vote_average' => 8.7,
                ],
            ],
        ]),
    ]);
});

test('user can search for movies', function () {
    $user = User::factory()->create();
    $token = JWTAuth::fromUser($user);

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->getJson('/api/v1/media/search?type=movie&query=matrix');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['external_id', 'title', 'poster_url', 'year', 'metadata'],
            ],
            'meta' => ['current_page', 'total_pages', 'total_results'],
        ]);
});

test('search requires type parameter', function () {
    $user = User::factory()->create();
    $token = JWTAuth::fromUser($user);

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->getJson('/api/v1/media/search?query=matrix');

    $response->assertStatus(422);
});

test('search requires authentication', function () {
    $response = $this->getJson('/api/v1/media/search?type=movie&query=matrix');

    $response->assertStatus(401);
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/Media/MediaSearchTest.php
```
Expected: FAIL

**Step 3: Create MediaSearchController**

Create `backend/app/Http/Controllers/Api/V1/MediaSearchController.php`:
```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Media\MediaSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaSearchController extends Controller
{
    public function __construct(
        private MediaSearchService $mediaSearchService
    ) {}

    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['required', 'string', 'in:movie,tvshow,videogame,boardgame'],
            'query' => ['required', 'string', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $results = $this->mediaSearchService->search(
            $request->type,
            $request->query('query'),
            $request->integer('page', 1)
        );

        return response()->json([
            'data' => collect($results->items)->map(fn ($item) => $item->toArray()),
            'meta' => [
                'current_page' => $results->currentPage,
                'total_pages' => $results->totalPages,
                'total_results' => $results->totalResults,
            ],
        ]);
    }

    public function show(Request $request, string $type, string $externalId): JsonResponse
    {
        $item = $this->mediaSearchService->getById($type, $externalId);

        if (!$item) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Media item not found.',
                ],
            ], 404);
        }

        return response()->json([
            'data' => $item->toArray(),
        ]);
    }
}
```

**Step 4: Add routes**

Add to protected routes in `backend/routes/api.php`:
```php
use App\Http\Controllers\Api\V1\MediaSearchController;

// Inside auth:api middleware:
Route::get('media/search', [MediaSearchController::class, 'search']);
Route::get('media/{type}/{externalId}', [MediaSearchController::class, 'show']);
```

**Step 5: Run tests**

```bash
php artisan test tests/Feature/Media/MediaSearchTest.php
```
Expected: PASS

**Step 6: Commit**

```bash
git add .
git commit -m "feat: add media search endpoints"
```

---

## Task 7: Create ElectionService

**Files:**
- Create: `backend/app/Services/Election/ElectionService.php`
- Create: `backend/tests/Unit/Services/Election/ElectionServiceTest.php`

**Step 1: Write ElectionService test**

Create `backend/tests/Unit/Services/Election/ElectionServiceTest.php`:
```php
<?php

use App\Models\User;
use App\Models\MediaType;
use App\Models\Election;
use App\Services\Election\ElectionService;
use App\Enums\ElectionStatus;

beforeEach(function () {
    $this->mediaType = MediaType::create([
        'code' => 'movie',
        'label_en' => 'Movie',
        'label_fr' => 'Film',
        'api_source' => 'tmdb',
    ]);
});

test('election service can create election', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $service = new ElectionService();

    $election = $service->create($user, [
        'title' => 'Movie Night',
        'media_type_code' => 'movie',
        'winner_count' => 2,
        'election_date' => now()->addDays(7),
        'deadline' => now()->addDays(6),
        'candidates' => [
            ['external_id' => 'tmdb:1', 'title' => 'Movie 1'],
            ['external_id' => 'tmdb:2', 'title' => 'Movie 2'],
        ],
    ]);

    expect($election)->toBeInstanceOf(Election::class);
    expect($election->status)->toBe(ElectionStatus::VOTING);
    expect($election->candidates)->toHaveCount(2);
});

test('election service validates candidate count', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $service = new ElectionService();

    expect(fn () => $service->create($user, [
        'title' => 'Movie Night',
        'media_type_code' => 'movie',
        'winner_count' => 2,
        'election_date' => now()->addDays(7),
        'deadline' => now()->addDays(6),
        'candidates' => [
            ['external_id' => 'tmdb:1', 'title' => 'Movie 1'],
        ],
    ]))->toThrow(\App\Exceptions\ApiException::class);
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test tests/Unit/Services/Election/ElectionServiceTest.php
```
Expected: FAIL

**Step 3: Create ElectionService**

Create `backend/app/Services/Election/ElectionService.php`:
```php
<?php

namespace App\Services\Election;

use App\Enums\ElectionStatus;
use App\Exceptions\ApiException;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\MediaType;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Support\Facades\DB;

class ElectionService
{
    public function create(User $maestro, array $data): Election
    {
        $this->validateCandidates($data['candidates'] ?? []);
        $this->validateWinnerCount($data['winner_count'], count($data['candidates'] ?? []));

        $mediaType = MediaType::where('code', $data['media_type_code'])->firstOrFail();

        $status = isset($data['campaign_end']) && $data['campaign_end']
            ? ElectionStatus::CAMPAIGN
            : ElectionStatus::VOTING;

        return DB::transaction(function () use ($maestro, $data, $mediaType, $status) {
            $election = Election::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'media_type_id' => $mediaType->id,
                'maestro_id' => $maestro->id,
                'winner_count' => $data['winner_count'],
                'election_date' => $data['election_date'],
                'deadline' => $data['deadline'],
                'campaign_end' => $data['campaign_end'] ?? null,
                'allow_suggestions' => $data['allow_suggestions'] ?? false,
                'auto_approve' => $data['auto_approve'] ?? false,
                'status' => $status,
            ]);

            foreach ($data['candidates'] as $candidateData) {
                Candidate::create([
                    'election_id' => $election->id,
                    'external_id' => $candidateData['external_id'],
                    'title' => $candidateData['title'],
                    'poster_url' => $candidateData['poster_url'] ?? null,
                    'year' => $candidateData['year'] ?? null,
                    'metadata' => $candidateData['metadata'] ?? null,
                    'is_approved' => true,
                ]);
            }

            // Add maestro as voter
            Voter::create([
                'election_id' => $election->id,
                'user_id' => $maestro->id,
                'joined_at' => now(),
            ]);

            return $election->load(['candidates', 'voters', 'mediaType']);
        });
    }

    public function close(Election $election): Election
    {
        if ($election->status !== ElectionStatus::VOTING) {
            throw new ApiException(
                'INVALID_STATUS',
                'Election is not in voting status.',
                400
            );
        }

        $election->update(['status' => ElectionStatus::ENDED]);

        return $election->fresh();
    }

    public function getForUser(User $user): \Illuminate\Database\Eloquent\Collection
    {
        $asMaestro = Election::where('maestro_id', $user->id)->pluck('id');
        $asVoter = Voter::where('user_id', $user->id)->pluck('election_id');

        $electionIds = $asMaestro->merge($asVoter)->unique();

        return Election::whereIn('id', $electionIds)
            ->with(['mediaType', 'maestro'])
            ->withCount(['candidates', 'voters'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    private function validateCandidates(array $candidates): void
    {
        $min = config('election.candidates.min');
        $max = config('election.candidates.max');
        $count = count($candidates);

        if ($count < $min) {
            throw new ApiException(
                'INVALID_CANDIDATE_COUNT',
                "Minimum $min candidates required.",
                400
            );
        }

        if ($count > $max) {
            throw new ApiException(
                'INVALID_CANDIDATE_COUNT',
                "Maximum $max candidates allowed.",
                400
            );
        }
    }

    private function validateWinnerCount(int $winnerCount, int $candidateCount): void
    {
        $min = config('election.winners.min');
        $max = config('election.winners.max');

        if ($winnerCount < $min || $winnerCount > $max) {
            throw new ApiException(
                'INVALID_WINNER_COUNT',
                "Winner count must be between $min and $max.",
                400
            );
        }

        if ($winnerCount >= $candidateCount) {
            throw new ApiException(
                'INVALID_WINNER_COUNT',
                'Winner count must be less than candidate count.',
                400
            );
        }
    }
}
```

**Step 4: Run tests**

```bash
php artisan test tests/Unit/Services/Election/ElectionServiceTest.php
```
Expected: PASS

**Step 5: Commit**

```bash
git add .
git commit -m "feat: add ElectionService"
```

---

## Task 8: Create Election Controller

**Files:**
- Create: `backend/app/Http/Controllers/Api/V1/ElectionController.php`
- Create: `backend/app/Http/Requests/Election/CreateElectionRequest.php`
- Create: `backend/app/Http/Resources/ElectionResource.php`
- Create: `backend/app/Http/Resources/ElectionDetailResource.php`
- Modify: `backend/routes/api.php`
- Create: `backend/tests/Feature/Election/ElectionTest.php`

**Step 1: Write election feature test**

Create `backend/tests/Feature/Election/ElectionTest.php`:
```php
<?php

use App\Models\User;
use App\Models\MediaType;
use App\Models\Election;
use App\Models\Voter;
use Tymon\JWTAuth\Facades\JWTAuth;

beforeEach(function () {
    MediaType::create([
        'code' => 'movie',
        'label_en' => 'Movie',
        'label_fr' => 'Film',
        'api_source' => 'tmdb',
    ]);
});

test('verified user can create election', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $token = JWTAuth::fromUser($user);

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->postJson('/api/v1/elections', [
            'title' => 'Movie Night',
            'media_type_code' => 'movie',
            'winner_count' => 2,
            'election_date' => now()->addDays(7)->toIso8601String(),
            'deadline' => now()->addDays(6)->toIso8601String(),
            'candidates' => [
                ['external_id' => 'tmdb:1', 'title' => 'Movie 1'],
                ['external_id' => 'tmdb:2', 'title' => 'Movie 2'],
                ['external_id' => 'tmdb:3', 'title' => 'Movie 3'],
            ],
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.title', 'Movie Night')
        ->assertJsonPath('data.status', 'voting');
});

test('unverified user cannot create election', function () {
    $user = User::factory()->unverified()->create();
    $token = JWTAuth::fromUser($user);

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->postJson('/api/v1/elections', [
            'title' => 'Movie Night',
            'media_type_code' => 'movie',
            'winner_count' => 1,
            'election_date' => now()->addDays(7)->toIso8601String(),
            'deadline' => now()->addDays(6)->toIso8601String(),
            'candidates' => [
                ['external_id' => 'tmdb:1', 'title' => 'Movie 1'],
                ['external_id' => 'tmdb:2', 'title' => 'Movie 2'],
            ],
        ]);

    $response->assertStatus(403);
});

test('user can list their elections', function () {
    $user = User::factory()->create();
    $election = Election::factory()->create(['maestro_id' => $user->id]);
    $token = JWTAuth::fromUser($user);

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->getJson('/api/v1/elections');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

test('maestro can close election', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $election = Election::factory()->create([
        'maestro_id' => $user->id,
        'status' => 'voting',
    ]);
    $token = JWTAuth::fromUser($user);

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->putJson("/api/v1/elections/{$election->uuid}/close");

    $response->assertStatus(200)
        ->assertJsonPath('data.status', 'ended');
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/Election/ElectionTest.php
```
Expected: FAIL

**Step 3: Create Election factory**

Create `backend/database/factories/ElectionFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Enums\ElectionStatus;
use App\Models\MediaType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ElectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'media_type_id' => MediaType::first()?->id ?? MediaType::factory(),
            'maestro_id' => User::factory(),
            'winner_count' => fake()->numberBetween(1, 3),
            'election_date' => now()->addDays(7),
            'deadline' => now()->addDays(6),
            'campaign_end' => null,
            'allow_suggestions' => false,
            'auto_approve' => false,
            'status' => ElectionStatus::VOTING,
        ];
    }
}
```

**Step 4: Create CreateElectionRequest**

Create `backend/app/Http/Requests/Election/CreateElectionRequest.php`:
```php
<?php

namespace App\Http\Requests\Election;

use Illuminate\Foundation\Http\FormRequest;

class CreateElectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'media_type_code' => ['required', 'string', 'exists:media_types,code'],
            'winner_count' => ['required', 'integer', 'min:1', 'max:5'],
            'election_date' => ['required', 'date', 'after:deadline'],
            'deadline' => ['required', 'date', 'after:now'],
            'campaign_end' => ['nullable', 'date', 'before:deadline', 'after:now'],
            'allow_suggestions' => ['nullable', 'boolean'],
            'auto_approve' => ['nullable', 'boolean'],
            'candidates' => ['required', 'array', 'min:2', 'max:30'],
            'candidates.*.external_id' => ['required', 'string'],
            'candidates.*.title' => ['required', 'string'],
            'candidates.*.poster_url' => ['nullable', 'string', 'url'],
            'candidates.*.year' => ['nullable', 'integer'],
            'candidates.*.metadata' => ['nullable', 'array'],
        ];
    }
}
```

**Step 5: Create ElectionResource**

Create `backend/app/Http/Resources/ElectionResource.php`:
```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ElectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'title' => $this->title,
            'media_type' => [
                'code' => $this->mediaType->code,
                'label' => $this->mediaType->{'label_' . app()->getLocale()} ?? $this->mediaType->label_en,
            ],
            'status' => $this->status->value,
            'is_maestro' => $request->user()?->id === $this->maestro_id,
            'election_date' => $this->election_date->toIso8601String(),
            'deadline' => $this->deadline->toIso8601String(),
            'winner_count' => $this->winner_count,
            'candidate_count' => $this->candidates_count ?? $this->candidates->count(),
            'voter_count' => $this->voters_count ?? $this->voters->count(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
```

**Step 6: Create ElectionDetailResource**

Create `backend/app/Http/Resources/ElectionDetailResource.php`:
```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ElectionDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'title' => $this->title,
            'description' => $this->description,
            'media_type' => [
                'code' => $this->mediaType->code,
                'label' => $this->mediaType->{'label_' . app()->getLocale()} ?? $this->mediaType->label_en,
            ],
            'maestro' => [
                'id' => $this->maestro->id,
                'display_name' => $this->maestro->display_name,
                'avatar_url' => $this->maestro->avatar_url,
            ],
            'status' => $this->status->value,
            'is_maestro' => $request->user()?->id === $this->maestro_id,
            'election_date' => $this->election_date->toIso8601String(),
            'deadline' => $this->deadline->toIso8601String(),
            'campaign_end' => $this->campaign_end?->toIso8601String(),
            'allow_suggestions' => $this->allow_suggestions,
            'auto_approve' => $this->auto_approve,
            'winner_count' => $this->winner_count,
            'candidates' => CandidateResource::collection($this->candidates),
            'voters' => VoterResource::collection($this->voters),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
```

**Step 7: Create supporting resources**

Create `backend/app/Http/Resources/CandidateResource.php`:
```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CandidateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'title' => $this->title,
            'poster_url' => $this->poster_url,
            'year' => $this->year,
            'metadata' => $this->metadata,
            'is_approved' => $this->is_approved,
        ];
    }
}
```

Create `backend/app/Http/Resources/VoterResource.php`:
```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user->id,
                'display_name' => $this->user->display_name,
                'avatar_url' => $this->user->avatar_url,
            ],
            'joined_at' => $this->joined_at->toIso8601String(),
            'completed' => $this->completed,
        ];
    }
}
```

**Step 8: Create ElectionController**

Create `backend/app/Http/Controllers/Api/V1/ElectionController.php`:
```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Election\CreateElectionRequest;
use App\Http\Resources\ElectionDetailResource;
use App\Http\Resources\ElectionResource;
use App\Models\Election;
use App\Services\Election\ElectionService;
use App\Exceptions\ApiException;
use Illuminate\Http\JsonResponse;

class ElectionController extends Controller
{
    public function __construct(
        private ElectionService $electionService
    ) {}

    public function index(): JsonResponse
    {
        $elections = $this->electionService->getForUser(auth()->user());

        return response()->json([
            'data' => ElectionResource::collection($elections),
        ]);
    }

    public function store(CreateElectionRequest $request): JsonResponse
    {
        $election = $this->electionService->create(
            auth()->user(),
            $request->validated()
        );

        return response()->json([
            'data' => new ElectionDetailResource($election),
        ], 201);
    }

    public function show(string $uuid): JsonResponse
    {
        $election = Election::where('uuid', $uuid)
            ->with(['mediaType', 'maestro', 'candidates', 'voters.user'])
            ->firstOrFail();

        $this->authorizeView($election);

        return response()->json([
            'data' => new ElectionDetailResource($election),
        ]);
    }

    public function close(string $uuid): JsonResponse
    {
        $election = Election::where('uuid', $uuid)->firstOrFail();

        if ($election->maestro_id !== auth()->id()) {
            throw new ApiException(
                'FORBIDDEN',
                'Only the maestro can close the election.',
                403
            );
        }

        $election = $this->electionService->close($election);

        return response()->json([
            'data' => new ElectionDetailResource($election->load(['mediaType', 'maestro', 'candidates', 'voters.user'])),
        ]);
    }

    private function authorizeView(Election $election): void
    {
        $user = auth()->user();

        if ($election->maestro_id === $user->id) {
            return;
        }

        $isVoter = $election->voters()->where('user_id', $user->id)->exists();

        if (!$isVoter) {
            throw new ApiException(
                'FORBIDDEN',
                'You do not have access to this election.',
                403
            );
        }
    }
}
```

**Step 9: Add routes**

Add to `backend/routes/api.php`:
```php
use App\Http\Controllers\Api\V1\ElectionController;

// Inside auth:api middleware, verified group:
Route::get('elections', [ElectionController::class, 'index'])->withoutMiddleware('verified');
Route::post('elections', [ElectionController::class, 'store']);
Route::get('elections/{uuid}', [ElectionController::class, 'show'])->withoutMiddleware('verified');
Route::put('elections/{uuid}/close', [ElectionController::class, 'close']);
```

**Step 10: Run tests**

```bash
php artisan test tests/Feature/Election/ElectionTest.php
```
Expected: PASS

**Step 11: Commit**

```bash
git add .
git commit -m "feat: add election CRUD endpoints"
```

---

## Task 9: Create Invitation System

**Files:**
- Create: `backend/app/Services/Election/InvitationService.php`
- Create: `backend/app/Http/Controllers/Api/V1/InvitationController.php`
- Modify: `backend/routes/api.php`
- Create: `backend/tests/Feature/Election/InvitationTest.php`

**Step 1: Write invitation tests**

Create `backend/tests/Feature/Election/InvitationTest.php`:
```php
<?php

use App\Models\User;
use App\Models\Election;
use App\Models\MediaType;
use App\Models\Invitation;
use Tymon\JWTAuth\Facades\JWTAuth;

beforeEach(function () {
    MediaType::create([
        'code' => 'movie',
        'label_en' => 'Movie',
        'label_fr' => 'Film',
        'api_source' => 'tmdb',
    ]);
});

test('maestro can invite by email', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $election = Election::factory()->create(['maestro_id' => $user->id]);
    $token = JWTAuth::fromUser($user);

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->postJson("/api/v1/elections/{$election->uuid}/invitations", [
            'emails' => ['friend@example.com'],
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.invited', 1)
        ->assertJsonStructure(['data' => ['magic_link']]);
});

test('user can get election info via magic link', function () {
    $election = Election::factory()->create();
    $invitation = Invitation::create([
        'election_id' => $election->id,
        'email' => 'test@example.com',
        'sent_at' => now(),
    ]);

    $response = $this->getJson("/api/v1/elections/join/{$invitation->token}");

    $response->assertStatus(200)
        ->assertJsonPath('data.election.uuid', $election->uuid);
});

test('user can join election via magic link', function () {
    $user = User::factory()->create();
    $election = Election::factory()->create();
    $invitation = Invitation::create([
        'election_id' => $election->id,
        'email' => $user->email,
        'sent_at' => now(),
    ]);
    $token = JWTAuth::fromUser($user);

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->postJson("/api/v1/elections/join/{$invitation->token}");

    $response->assertStatus(200);

    $this->assertDatabaseHas('voters', [
        'election_id' => $election->id,
        'user_id' => $user->id,
    ]);
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/Election/InvitationTest.php
```
Expected: FAIL

**Step 3: Create InvitationService**

Create `backend/app/Services/Election/InvitationService.php`:
```php
<?php

namespace App\Services\Election;

use App\Exceptions\ApiException;
use App\Models\Election;
use App\Models\Invitation;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Support\Facades\DB;

class InvitationService
{
    public function invite(Election $election, array $emails, array $friendIds = []): array
    {
        $invited = 0;

        DB::transaction(function () use ($election, $emails, $friendIds, &$invited) {
            foreach ($emails as $email) {
                $existing = Invitation::where('election_id', $election->id)
                    ->where('email', $email)
                    ->first();

                if (!$existing) {
                    Invitation::create([
                        'election_id' => $election->id,
                        'email' => $email,
                        'sent_at' => now(),
                    ]);
                    $invited++;
                }
            }

            foreach ($friendIds as $friendId) {
                $friend = User::find($friendId);
                if ($friend) {
                    $existingVoter = Voter::where('election_id', $election->id)
                        ->where('user_id', $friendId)
                        ->first();

                    if (!$existingVoter) {
                        Voter::create([
                            'election_id' => $election->id,
                            'user_id' => $friendId,
                            'joined_at' => now(),
                        ]);
                        $invited++;
                    }
                }
            }
        });

        $magicLink = $this->generateMagicLink($election);

        return [
            'invited' => $invited,
            'magic_link' => $magicLink,
        ];
    }

    public function getElectionByToken(string $token): ?Election
    {
        $invitation = Invitation::where('token', $token)->first();

        if (!$invitation) {
            return null;
        }

        return $invitation->election;
    }

    public function joinByToken(User $user, string $token, bool $addFriend = false): Election
    {
        $invitation = Invitation::where('token', $token)->first();

        if (!$invitation) {
            throw new ApiException(
                'INVALID_TOKEN',
                'Invalid invitation token.',
                404
            );
        }

        $election = $invitation->election;

        $existingVoter = Voter::where('election_id', $election->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$existingVoter) {
            Voter::create([
                'election_id' => $election->id,
                'user_id' => $user->id,
                'joined_at' => now(),
            ]);
        }

        $invitation->update(['accepted_at' => now()]);

        return $election->load(['mediaType', 'maestro', 'candidates', 'voters.user']);
    }

    private function generateMagicLink(Election $election): string
    {
        $invitation = Invitation::where('election_id', $election->id)->first();

        if (!$invitation) {
            $invitation = Invitation::create([
                'election_id' => $election->id,
                'email' => 'public@' . $election->uuid,
                'sent_at' => now(),
            ]);
        }

        return config('app.frontend_url', config('app.url')) . '/join/' . $invitation->token;
    }
}
```

**Step 4: Create InvitationController**

Create `backend/app/Http/Controllers/Api/V1/InvitationController.php`:
```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ElectionDetailResource;
use App\Models\Election;
use App\Services\Election\InvitationService;
use App\Exceptions\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function __construct(
        private InvitationService $invitationService
    ) {}

    public function store(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'emails' => ['nullable', 'array'],
            'emails.*' => ['email'],
            'friend_ids' => ['nullable', 'array'],
            'friend_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $election = Election::where('uuid', $uuid)->firstOrFail();

        if ($election->maestro_id !== auth()->id()) {
            throw new ApiException(
                'FORBIDDEN',
                'Only the maestro can invite voters.',
                403
            );
        }

        $result = $this->invitationService->invite(
            $election,
            $request->input('emails', []),
            $request->input('friend_ids', [])
        );

        return response()->json([
            'data' => $result,
        ], 201);
    }

    public function showByToken(string $token): JsonResponse
    {
        $election = $this->invitationService->getElectionByToken($token);

        if (!$election) {
            throw new ApiException(
                'NOT_FOUND',
                'Election not found.',
                404
            );
        }

        return response()->json([
            'data' => [
                'election' => [
                    'uuid' => $election->uuid,
                    'title' => $election->title,
                    'media_type' => [
                        'code' => $election->mediaType->code,
                        'label' => $election->mediaType->label_en,
                    ],
                    'maestro' => [
                        'display_name' => $election->maestro->display_name,
                    ],
                    'status' => $election->status->value,
                    'candidate_count' => $election->candidates()->count(),
                    'voter_count' => $election->voters()->count(),
                ],
                'requires_auth' => true,
            ],
        ]);
    }

    public function joinByToken(Request $request, string $token): JsonResponse
    {
        $request->validate([
            'add_friend' => ['nullable', 'boolean'],
        ]);

        $election = $this->invitationService->joinByToken(
            auth()->user(),
            $token,
            $request->boolean('add_friend', false)
        );

        return response()->json([
            'data' => [
                'election' => new ElectionDetailResource($election),
                'friendship_created' => false, // TODO: implement
            ],
        ]);
    }
}
```

**Step 5: Add routes**

Add to `backend/routes/api.php`:
```php
use App\Http\Controllers\Api\V1\InvitationController;

// Public route (no auth)
Route::get('elections/join/{token}', [InvitationController::class, 'showByToken']);

// Inside auth:api middleware, verified group:
Route::post('elections/{uuid}/invitations', [InvitationController::class, 'store']);
Route::post('elections/join/{token}', [InvitationController::class, 'joinByToken'])->withoutMiddleware('verified');
```

**Step 6: Run tests**

```bash
php artisan test tests/Feature/Election/InvitationTest.php
```
Expected: PASS

**Step 7: Commit**

```bash
git add .
git commit -m "feat: add invitation system with magic links"
```

---

## Task 10: Run Full Test Suite

**Step 1: Run all tests**

```bash
php artisan test
```
Expected: All PASS

**Step 2: Final commit**

```bash
git add .
git commit -m "chore: phase 3 complete - election system"
```

---

## Phase 3 Completion Checklist

- [ ] Election and media config files
- [ ] Media provider interface and DTOs
- [ ] TMDB provider (movies & TV shows)
- [ ] RAWG provider (video games)
- [ ] MediaSearchService facade
- [ ] Media search endpoints
- [ ] ElectionService with create/close logic
- [ ] Election CRUD endpoints
- [ ] ElectionResource and ElectionDetailResource
- [ ] Invitation system with magic links
- [ ] All feature tests passing
