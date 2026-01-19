# Phase 4: Voting & Results

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implement duel voting system, Condorcet ranking algorithm, results display, and scheduled commands for election lifecycle management.

**Architecture:** DuelGeneratorService creates random pairs for voters. CondorcetService builds preference graph and calculates rankings. Scheduled commands handle deadline enforcement and archiving.

**Tech Stack:** Laravel 11, Laravel Scheduler, Pest

**Prerequisites:** Phase 3 complete (elections, candidates, invitations)

---

## Task 1: Create DuelGeneratorService

**Files:**
- Create: `backend/app/Services/Election/DuelGeneratorService.php`
- Create: `backend/tests/Unit/Services/Election/DuelGeneratorServiceTest.php`

**Step 1: Write DuelGeneratorService test**

Create `backend/tests/Unit/Services/Election/DuelGeneratorServiceTest.php`:
```php
<?php

use App\Models\User;
use App\Models\Election;
use App\Models\Candidate;
use App\Models\Voter;
use App\Models\Duel;
use App\Models\MediaType;
use App\Services\Election\DuelGeneratorService;

beforeEach(function () {
    $this->mediaType = MediaType::create([
        'code' => 'movie',
        'label_en' => 'Movie',
        'label_fr' => 'Film',
        'api_source' => 'tmdb',
    ]);
});

test('generates next duel for voter', function () {
    $user = User::factory()->create();
    $election = Election::factory()->create(['media_type_id' => $this->mediaType->id]);

    $c1 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:1', 'title' => 'Movie 1']);
    $c2 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:2', 'title' => 'Movie 2']);
    $c3 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:3', 'title' => 'Movie 3']);

    $voter = Voter::create(['election_id' => $election->id, 'user_id' => $user->id, 'joined_at' => now()]);

    $service = new DuelGeneratorService();
    $duel = $service->getNextDuel($voter);

    expect($duel)->not->toBeNull();
    expect($duel['candidate_a'])->toBeInstanceOf(Candidate::class);
    expect($duel['candidate_b'])->toBeInstanceOf(Candidate::class);
});

test('returns null when all duels completed', function () {
    $user = User::factory()->create();
    $election = Election::factory()->create(['media_type_id' => $this->mediaType->id]);

    $c1 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:1', 'title' => 'Movie 1']);
    $c2 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:2', 'title' => 'Movie 2']);

    $voter = Voter::create(['election_id' => $election->id, 'user_id' => $user->id, 'joined_at' => now()]);

    // Create the only possible duel
    Duel::create([
        'election_id' => $election->id,
        'voter_id' => $voter->id,
        'candidate_a_id' => $c1->id,
        'candidate_b_id' => $c2->id,
        'winner_id' => $c1->id,
        'voted_at' => now(),
    ]);

    $service = new DuelGeneratorService();
    $duel = $service->getNextDuel($voter);

    expect($duel)->toBeNull();
});

test('calculates progress correctly', function () {
    $user = User::factory()->create();
    $election = Election::factory()->create(['media_type_id' => $this->mediaType->id]);

    $c1 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:1', 'title' => 'Movie 1']);
    $c2 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:2', 'title' => 'Movie 2']);
    $c3 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:3', 'title' => 'Movie 3']);

    $voter = Voter::create(['election_id' => $election->id, 'user_id' => $user->id, 'joined_at' => now()]);

    $service = new DuelGeneratorService();
    $progress = $service->getProgress($voter);

    expect($progress['completed'])->toBe(0);
    expect($progress['total'])->toBe(3); // 3 candidates = 3 pairs
    expect($progress['percentage'])->toBe(0.0);
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test tests/Unit/Services/Election/DuelGeneratorServiceTest.php
```
Expected: FAIL

**Step 3: Create DuelGeneratorService**

Create `backend/app/Services/Election/DuelGeneratorService.php`:
```php
<?php

namespace App\Services\Election;

use App\Models\Candidate;
use App\Models\Duel;
use App\Models\Voter;
use Illuminate\Support\Facades\DB;

class DuelGeneratorService
{
    /**
     * Get the next duel for a voter (random unvoted pair)
     */
    public function getNextDuel(Voter $voter): ?array
    {
        $election = $voter->election;
        $candidates = $election->candidates()
            ->where('is_approved', true)
            ->orderBy('id')
            ->get();

        if ($candidates->count() < 2) {
            return null;
        }

        // Get all voted pairs for this voter
        $votedPairs = Duel::where('voter_id', $voter->id)
            ->select('candidate_a_id', 'candidate_b_id')
            ->get()
            ->map(fn ($d) => $this->normalizePair($d->candidate_a_id, $d->candidate_b_id))
            ->toArray();

        // Generate all possible pairs
        $allPairs = [];
        for ($i = 0; $i < $candidates->count(); $i++) {
            for ($j = $i + 1; $j < $candidates->count(); $j++) {
                $pair = $this->normalizePair($candidates[$i]->id, $candidates[$j]->id);
                if (!in_array($pair, $votedPairs)) {
                    $allPairs[] = [
                        'candidate_a' => $candidates[$i],
                        'candidate_b' => $candidates[$j],
                    ];
                }
            }
        }

        if (empty($allPairs)) {
            return null;
        }

        // Return random unvoted pair
        return $allPairs[array_rand($allPairs)];
    }

    /**
     * Check if voter has completed all possible duels
     */
    public function isComplete(Voter $voter): bool
    {
        return $this->getNextDuel($voter) === null;
    }

    /**
     * Get progress stats for voter
     */
    public function getProgress(Voter $voter): array
    {
        $candidateCount = $voter->election->candidates()
            ->where('is_approved', true)
            ->count();

        $totalPairs = $this->calculateTotalPairs($candidateCount);
        $completedPairs = Duel::where('voter_id', $voter->id)->count();

        $percentage = $totalPairs > 0 ? round(($completedPairs / $totalPairs) * 100, 1) : 0;

        return [
            'completed' => $completedPairs,
            'total' => $totalPairs,
            'percentage' => $percentage,
        ];
    }

    /**
     * Calculate total number of pairs: n*(n-1)/2
     */
    private function calculateTotalPairs(int $n): int
    {
        return (int) ($n * ($n - 1) / 2);
    }

    /**
     * Normalize pair to always have smaller ID first
     */
    private function normalizePair(int $a, int $b): string
    {
        return min($a, $b) . '-' . max($a, $b);
    }
}
```

**Step 4: Run tests**

```bash
php artisan test tests/Unit/Services/Election/DuelGeneratorServiceTest.php
```
Expected: PASS

**Step 5: Commit**

```bash
git add .
git commit -m "feat: add DuelGeneratorService"
```

---

## Task 2: Create Duel Controller

**Files:**
- Create: `backend/app/Http/Controllers/Api/V1/DuelController.php`
- Create: `backend/app/Http/Resources/DuelResource.php`
- Modify: `backend/routes/api.php`
- Create: `backend/tests/Feature/Duel/DuelTest.php`

**Step 1: Write duel feature tests**

Create `backend/tests/Feature/Duel/DuelTest.php`:
```php
<?php

use App\Models\User;
use App\Models\Election;
use App\Models\Candidate;
use App\Models\Voter;
use App\Models\MediaType;
use App\Enums\ElectionStatus;
use Tymon\JWTAuth\Facades\JWTAuth;

beforeEach(function () {
    $this->mediaType = MediaType::create([
        'code' => 'movie',
        'label_en' => 'Movie',
        'label_fr' => 'Film',
        'api_source' => 'tmdb',
    ]);
});

test('voter can get next duel', function () {
    $user = User::factory()->create();
    $election = Election::factory()->create([
        'media_type_id' => $this->mediaType->id,
        'status' => ElectionStatus::VOTING,
    ]);

    Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:1', 'title' => 'Movie 1']);
    Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:2', 'title' => 'Movie 2']);

    Voter::create(['election_id' => $election->id, 'user_id' => $user->id, 'joined_at' => now()]);

    $token = JWTAuth::fromUser($user);

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->getJson("/api/v1/elections/{$election->uuid}/duels/next");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'candidate_a' => ['id', 'title'],
                'candidate_b' => ['id', 'title'],
                'progress' => ['completed', 'total', 'percentage'],
            ],
        ]);
});

test('voter can vote on duel', function () {
    $user = User::factory()->create();
    $election = Election::factory()->create([
        'media_type_id' => $this->mediaType->id,
        'status' => ElectionStatus::VOTING,
    ]);

    $c1 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:1', 'title' => 'Movie 1']);
    $c2 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:2', 'title' => 'Movie 2']);

    Voter::create(['election_id' => $election->id, 'user_id' => $user->id, 'joined_at' => now()]);

    $token = JWTAuth::fromUser($user);

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->postJson("/api/v1/elections/{$election->uuid}/duels/vote", [
            'candidate_a_id' => $c1->id,
            'candidate_b_id' => $c2->id,
            'winner_id' => $c1->id,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.voted', true);

    $this->assertDatabaseHas('duels', [
        'election_id' => $election->id,
        'winner_id' => $c1->id,
    ]);
});

test('cannot vote on ended election', function () {
    $user = User::factory()->create();
    $election = Election::factory()->create([
        'media_type_id' => $this->mediaType->id,
        'status' => ElectionStatus::ENDED,
    ]);

    $c1 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:1', 'title' => 'Movie 1']);
    $c2 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:2', 'title' => 'Movie 2']);

    Voter::create(['election_id' => $election->id, 'user_id' => $user->id, 'joined_at' => now()]);

    $token = JWTAuth::fromUser($user);

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->postJson("/api/v1/elections/{$election->uuid}/duels/vote", [
            'candidate_a_id' => $c1->id,
            'candidate_b_id' => $c2->id,
            'winner_id' => $c1->id,
        ]);

    $response->assertStatus(400)
        ->assertJsonPath('error.code', 'ELECTION_NOT_VOTING');
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/Duel/DuelTest.php
```
Expected: FAIL

**Step 3: Create DuelResource**

Create `backend/app/Http/Resources/DuelResource.php`:
```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DuelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'candidate_a' => new CandidateResource($this->candidateA),
            'candidate_b' => new CandidateResource($this->candidateB),
            'winner_id' => $this->winner_id,
            'voted_at' => $this->voted_at?->toIso8601String(),
        ];
    }
}
```

**Step 4: Create DuelController**

Create `backend/app/Http/Controllers/Api/V1/DuelController.php`:
```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ElectionStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\CandidateResource;
use App\Http\Resources\DuelResource;
use App\Models\Candidate;
use App\Models\Duel;
use App\Models\Election;
use App\Models\Voter;
use App\Services\Election\DuelGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DuelController extends Controller
{
    public function __construct(
        private DuelGeneratorService $duelGeneratorService
    ) {}

    public function next(string $uuid): JsonResponse
    {
        $election = Election::where('uuid', $uuid)->firstOrFail();
        $voter = $this->getVoter($election);

        $duel = $this->duelGeneratorService->getNextDuel($voter);
        $progress = $this->duelGeneratorService->getProgress($voter);

        if (!$duel) {
            return response()->json([
                'data' => null,
                'meta' => [
                    'complete' => true,
                    'message' => 'You have completed all duels.',
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'candidate_a' => new CandidateResource($duel['candidate_a']),
                'candidate_b' => new CandidateResource($duel['candidate_b']),
                'progress' => $progress,
            ],
        ]);
    }

    public function vote(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'candidate_a_id' => ['required', 'integer'],
            'candidate_b_id' => ['required', 'integer'],
            'winner_id' => ['required', 'integer'],
        ]);

        $election = Election::where('uuid', $uuid)->firstOrFail();

        if ($election->status !== ElectionStatus::VOTING) {
            throw new ApiException(
                'ELECTION_NOT_VOTING',
                'Election is not in voting phase.',
                400
            );
        }

        $voter = $this->getVoter($election);

        // Validate candidates belong to election
        $candidateA = Candidate::where('id', $request->candidate_a_id)
            ->where('election_id', $election->id)
            ->firstOrFail();
        $candidateB = Candidate::where('id', $request->candidate_b_id)
            ->where('election_id', $election->id)
            ->firstOrFail();

        // Validate winner is one of the candidates
        if (!in_array($request->winner_id, [$candidateA->id, $candidateB->id])) {
            throw new ApiException(
                'INVALID_WINNER',
                'Winner must be one of the candidates.',
                400
            );
        }

        // Normalize pair order
        $aId = min($candidateA->id, $candidateB->id);
        $bId = max($candidateA->id, $candidateB->id);

        // Check if already voted
        $existing = Duel::where('voter_id', $voter->id)
            ->where('candidate_a_id', $aId)
            ->where('candidate_b_id', $bId)
            ->first();

        if ($existing) {
            throw new ApiException(
                'DUEL_ALREADY_VOTED',
                'You have already voted on this pair.',
                400
            );
        }

        // Create duel
        Duel::create([
            'election_id' => $election->id,
            'voter_id' => $voter->id,
            'candidate_a_id' => $aId,
            'candidate_b_id' => $bId,
            'winner_id' => $request->winner_id,
            'voted_at' => now(),
        ]);

        // Get next duel
        $nextDuel = $this->duelGeneratorService->getNextDuel($voter);
        $progress = $this->duelGeneratorService->getProgress($voter);

        // Mark voter as completed if no more duels
        if (!$nextDuel) {
            $voter->update(['completed' => true]);
        }

        return response()->json([
            'data' => [
                'voted' => true,
                'next_duel' => $nextDuel ? [
                    'candidate_a' => new CandidateResource($nextDuel['candidate_a']),
                    'candidate_b' => new CandidateResource($nextDuel['candidate_b']),
                ] : null,
                'progress' => $progress,
            ],
        ]);
    }

    public function history(string $uuid): JsonResponse
    {
        $election = Election::where('uuid', $uuid)->firstOrFail();
        $voter = $this->getVoter($election);

        $duels = Duel::where('voter_id', $voter->id)
            ->with(['candidateA', 'candidateB', 'winner'])
            ->orderBy('voted_at', 'desc')
            ->get();

        return response()->json([
            'data' => DuelResource::collection($duels),
        ]);
    }

    private function getVoter(Election $election): Voter
    {
        $voter = Voter::where('election_id', $election->id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$voter) {
            throw new ApiException(
                'NOT_A_VOTER',
                'You are not a voter in this election.',
                403
            );
        }

        return $voter;
    }
}
```

**Step 5: Add routes**

Add to protected routes in `backend/routes/api.php`:
```php
use App\Http\Controllers\Api\V1\DuelController;

// Inside auth:api middleware:
Route::get('elections/{uuid}/duels/next', [DuelController::class, 'next']);
Route::post('elections/{uuid}/duels/vote', [DuelController::class, 'vote']);
Route::get('elections/{uuid}/duels/history', [DuelController::class, 'history']);
```

**Step 6: Run tests**

```bash
php artisan test tests/Feature/Duel/DuelTest.php
```
Expected: PASS

**Step 7: Commit**

```bash
git add .
git commit -m "feat: add duel voting endpoints"
```

---

## Task 3: Create CondorcetService

**Files:**
- Create: `backend/app/Services/Election/CondorcetService.php`
- Create: `backend/tests/Unit/Services/Election/CondorcetServiceTest.php`

**Step 1: Write CondorcetService test**

Create `backend/tests/Unit/Services/Election/CondorcetServiceTest.php`:
```php
<?php

use App\Models\User;
use App\Models\Election;
use App\Models\Candidate;
use App\Models\Voter;
use App\Models\Duel;
use App\Models\MediaType;
use App\Services\Election\CondorcetService;

beforeEach(function () {
    $this->mediaType = MediaType::create([
        'code' => 'movie',
        'label_en' => 'Movie',
        'label_fr' => 'Film',
        'api_source' => 'tmdb',
    ]);
});

test('builds preference graph from duels', function () {
    $user = User::factory()->create();
    $election = Election::factory()->create(['media_type_id' => $this->mediaType->id]);

    $c1 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:1', 'title' => 'Movie 1']);
    $c2 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:2', 'title' => 'Movie 2']);
    $c3 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:3', 'title' => 'Movie 3']);

    $voter = Voter::create(['election_id' => $election->id, 'user_id' => $user->id, 'joined_at' => now()]);

    // C1 beats C2, C1 beats C3, C2 beats C3
    Duel::create(['election_id' => $election->id, 'voter_id' => $voter->id, 'candidate_a_id' => $c1->id, 'candidate_b_id' => $c2->id, 'winner_id' => $c1->id, 'voted_at' => now()]);
    Duel::create(['election_id' => $election->id, 'voter_id' => $voter->id, 'candidate_a_id' => $c1->id, 'candidate_b_id' => $c3->id, 'winner_id' => $c1->id, 'voted_at' => now()]);
    Duel::create(['election_id' => $election->id, 'voter_id' => $voter->id, 'candidate_a_id' => $c2->id, 'candidate_b_id' => $c3->id, 'winner_id' => $c2->id, 'voted_at' => now()]);

    $service = new CondorcetService();
    $graph = $service->buildPreferenceGraph($election);

    expect($graph[$c1->id][$c2->id])->toBe(1);
    expect($graph[$c1->id][$c3->id])->toBe(1);
    expect($graph[$c2->id][$c3->id])->toBe(1);
});

test('calculates rankings correctly', function () {
    $user = User::factory()->create();
    $election = Election::factory()->create([
        'media_type_id' => $this->mediaType->id,
        'winner_count' => 2,
    ]);

    $c1 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:1', 'title' => 'Movie 1']);
    $c2 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:2', 'title' => 'Movie 2']);
    $c3 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:3', 'title' => 'Movie 3']);

    $voter = Voter::create(['election_id' => $election->id, 'user_id' => $user->id, 'joined_at' => now()]);

    // Clear ranking: C1 > C2 > C3
    Duel::create(['election_id' => $election->id, 'voter_id' => $voter->id, 'candidate_a_id' => $c1->id, 'candidate_b_id' => $c2->id, 'winner_id' => $c1->id, 'voted_at' => now()]);
    Duel::create(['election_id' => $election->id, 'voter_id' => $voter->id, 'candidate_a_id' => $c1->id, 'candidate_b_id' => $c3->id, 'winner_id' => $c1->id, 'voted_at' => now()]);
    Duel::create(['election_id' => $election->id, 'voter_id' => $voter->id, 'candidate_a_id' => $c2->id, 'candidate_b_id' => $c3->id, 'winner_id' => $c2->id, 'voted_at' => now()]);

    $service = new CondorcetService();
    $rankings = $service->calculateRankings($election);

    expect($rankings[0]['candidate']->id)->toBe($c1->id);
    expect($rankings[1]['candidate']->id)->toBe($c2->id);
    expect($rankings[2]['candidate']->id)->toBe($c3->id);
});

test('gets top K winners', function () {
    $user = User::factory()->create();
    $election = Election::factory()->create([
        'media_type_id' => $this->mediaType->id,
        'winner_count' => 1,
    ]);

    $c1 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:1', 'title' => 'Movie 1']);
    $c2 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:2', 'title' => 'Movie 2']);

    $voter = Voter::create(['election_id' => $election->id, 'user_id' => $user->id, 'joined_at' => now()]);

    Duel::create(['election_id' => $election->id, 'voter_id' => $voter->id, 'candidate_a_id' => $c1->id, 'candidate_b_id' => $c2->id, 'winner_id' => $c1->id, 'voted_at' => now()]);

    $service = new CondorcetService();
    $winners = $service->getWinners($election);

    expect($winners)->toHaveCount(1);
    expect($winners[0]['candidate']->id)->toBe($c1->id);
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test tests/Unit/Services/Election/CondorcetServiceTest.php
```
Expected: FAIL

**Step 3: Create CondorcetService**

Create `backend/app/Services/Election/CondorcetService.php`:
```php
<?php

namespace App\Services\Election;

use App\Models\Candidate;
use App\Models\Duel;
use App\Models\Election;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CondorcetService
{
    /**
     * Build preference graph from duels
     * Returns [candidateA][candidateB] = number of wins for A over B
     */
    public function buildPreferenceGraph(Election $election): array
    {
        $candidates = $election->candidates()
            ->where('is_approved', true)
            ->pluck('id')
            ->toArray();

        $graph = [];
        foreach ($candidates as $a) {
            $graph[$a] = [];
            foreach ($candidates as $b) {
                $graph[$a][$b] = 0;
            }
        }

        // Count wins from all duels
        $duels = Duel::where('election_id', $election->id)->get();

        foreach ($duels as $duel) {
            $winner = $duel->winner_id;
            $loser = $winner === $duel->candidate_a_id
                ? $duel->candidate_b_id
                : $duel->candidate_a_id;

            $graph[$winner][$loser]++;
        }

        return $graph;
    }

    /**
     * Calculate rankings using Copeland method (simplified Condorcet)
     * Score = number of pairwise victories - number of pairwise defeats
     */
    public function calculateRankings(Election $election): array
    {
        $cacheKey = "election:{$election->id}:rankings";

        return Cache::remember($cacheKey, 300, function () use ($election) {
            $graph = $this->buildPreferenceGraph($election);
            $candidates = $election->candidates()
                ->where('is_approved', true)
                ->get();

            $scores = [];

            foreach ($candidates as $candidate) {
                $wins = 0;
                $losses = 0;
                $totalWins = 0;
                $totalLosses = 0;

                foreach ($candidates as $opponent) {
                    if ($candidate->id === $opponent->id) {
                        continue;
                    }

                    $winsAgainst = $graph[$candidate->id][$opponent->id] ?? 0;
                    $lossesAgainst = $graph[$opponent->id][$candidate->id] ?? 0;

                    $totalWins += $winsAgainst;
                    $totalLosses += $lossesAgainst;

                    if ($winsAgainst > $lossesAgainst) {
                        $wins++;
                    } elseif ($lossesAgainst > $winsAgainst) {
                        $losses++;
                    }
                }

                $scores[] = [
                    'candidate' => $candidate,
                    'copeland_score' => $wins - $losses,
                    'stats' => [
                        'wins' => $totalWins,
                        'losses' => $totalLosses,
                        'win_rate' => ($totalWins + $totalLosses) > 0
                            ? round(($totalWins / ($totalWins + $totalLosses)) * 100, 1)
                            : 0,
                    ],
                ];
            }

            // Sort by Copeland score (descending)
            usort($scores, fn ($a, $b) => $b['copeland_score'] <=> $a['copeland_score']);

            // Add ranks
            $rank = 1;
            foreach ($scores as $index => &$score) {
                $score['rank'] = $rank++;
            }

            return $scores;
        });
    }

    /**
     * Get top K winners based on election winner_count
     */
    public function getWinners(Election $election): array
    {
        $rankings = $this->calculateRankings($election);

        return array_slice($rankings, 0, $election->winner_count);
    }

    /**
     * Clear cached rankings
     */
    public function clearCache(Election $election): void
    {
        Cache::forget("election:{$election->id}:rankings");
    }
}
```

**Step 4: Run tests**

```bash
php artisan test tests/Unit/Services/Election/CondorcetServiceTest.php
```
Expected: PASS

**Step 5: Commit**

```bash
git add .
git commit -m "feat: add CondorcetService for ranking calculation"
```

---

## Task 4: Create Results Controller

**Files:**
- Create: `backend/app/Http/Controllers/Api/V1/ResultsController.php`
- Modify: `backend/routes/api.php`
- Create: `backend/tests/Feature/Election/ResultsTest.php`

**Step 1: Write results feature tests**

Create `backend/tests/Feature/Election/ResultsTest.php`:
```php
<?php

use App\Models\User;
use App\Models\Election;
use App\Models\Candidate;
use App\Models\Voter;
use App\Models\Duel;
use App\Models\MediaType;
use App\Enums\ElectionStatus;
use Tymon\JWTAuth\Facades\JWTAuth;

beforeEach(function () {
    $this->mediaType = MediaType::create([
        'code' => 'movie',
        'label_en' => 'Movie',
        'label_fr' => 'Film',
        'api_source' => 'tmdb',
    ]);
});

test('voter can get results after election ends', function () {
    $user = User::factory()->create();
    $election = Election::factory()->create([
        'media_type_id' => $this->mediaType->id,
        'status' => ElectionStatus::ENDED,
        'winner_count' => 1,
    ]);

    $c1 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:1', 'title' => 'Movie 1']);
    $c2 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:2', 'title' => 'Movie 2']);

    $voter = Voter::create(['election_id' => $election->id, 'user_id' => $user->id, 'joined_at' => now()]);

    Duel::create(['election_id' => $election->id, 'voter_id' => $voter->id, 'candidate_a_id' => $c1->id, 'candidate_b_id' => $c2->id, 'winner_id' => $c1->id, 'voted_at' => now()]);

    $token = JWTAuth::fromUser($user);

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->getJson("/api/v1/elections/{$election->uuid}/results");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'winners' => [
                    '*' => ['rank', 'candidate', 'stats'],
                ],
                'full_ranking',
                'total_duels',
            ],
        ]);
});

test('cannot get results while voting', function () {
    $user = User::factory()->create();
    $election = Election::factory()->create([
        'media_type_id' => $this->mediaType->id,
        'status' => ElectionStatus::VOTING,
    ]);

    Voter::create(['election_id' => $election->id, 'user_id' => $user->id, 'joined_at' => now()]);

    $token = JWTAuth::fromUser($user);

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->getJson("/api/v1/elections/{$election->uuid}/results");

    $response->assertStatus(400)
        ->assertJsonPath('error.code', 'ELECTION_NOT_ENDED');
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/Election/ResultsTest.php
```
Expected: FAIL

**Step 3: Create ResultsController**

Create `backend/app/Http/Controllers/Api/V1/ResultsController.php`:
```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ElectionStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\CandidateResource;
use App\Models\Duel;
use App\Models\Election;
use App\Models\Voter;
use App\Services\Election\CondorcetService;
use Illuminate\Http\JsonResponse;

class ResultsController extends Controller
{
    public function __construct(
        private CondorcetService $condorcetService
    ) {}

    public function show(string $uuid): JsonResponse
    {
        $election = Election::where('uuid', $uuid)
            ->with(['voters.user'])
            ->firstOrFail();

        $this->authorizeView($election);

        if (!in_array($election->status, [ElectionStatus::ENDED, ElectionStatus::ARCHIVED])) {
            throw new ApiException(
                'ELECTION_NOT_ENDED',
                'Results are only available after voting ends.',
                400
            );
        }

        $winners = $this->condorcetService->getWinners($election);
        $fullRanking = $this->condorcetService->calculateRankings($election);
        $totalDuels = Duel::where('election_id', $election->id)->count();

        $voterParticipation = $election->voters->map(function ($voter) {
            $duelsCompleted = Duel::where('voter_id', $voter->id)->count();
            $totalPossible = $this->calculateTotalPairs(
                $voter->election->candidates()->where('is_approved', true)->count()
            );

            return [
                'voter' => [
                    'display_name' => $voter->user->display_name,
                ],
                'duels_completed' => $duelsCompleted,
                'percentage' => $totalPossible > 0
                    ? round(($duelsCompleted / $totalPossible) * 100, 1)
                    : 0,
            ];
        });

        return response()->json([
            'data' => [
                'winners' => $this->formatRankings($winners),
                'full_ranking' => $this->formatRankings($fullRanking),
                'total_duels' => $totalDuels,
                'voter_participation' => $voterParticipation,
            ],
        ]);
    }

    private function formatRankings(array $rankings): array
    {
        return array_map(fn ($r) => [
            'rank' => $r['rank'],
            'candidate' => new CandidateResource($r['candidate']),
            'stats' => $r['stats'],
        ], $rankings);
    }

    private function calculateTotalPairs(int $n): int
    {
        return (int) ($n * ($n - 1) / 2);
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

**Step 4: Add route**

Add to protected routes in `backend/routes/api.php`:
```php
use App\Http\Controllers\Api\V1\ResultsController;

// Inside auth:api middleware:
Route::get('elections/{uuid}/results', [ResultsController::class, 'show']);
```

**Step 5: Run tests**

```bash
php artisan test tests/Feature/Election/ResultsTest.php
```
Expected: PASS

**Step 6: Commit**

```bash
git add .
git commit -m "feat: add election results endpoint"
```

---

## Task 5: Create Scheduled Commands

**Files:**
- Create: `backend/app/Console/Commands/CloseExpiredElections.php`
- Create: `backend/app/Console/Commands/ArchiveOldElections.php`
- Modify: `backend/routes/console.php`
- Create: `backend/tests/Feature/Commands/ElectionCommandsTest.php`

**Step 1: Write command tests**

Create `backend/tests/Feature/Commands/ElectionCommandsTest.php`:
```php
<?php

use App\Models\Election;
use App\Models\MediaType;
use App\Enums\ElectionStatus;
use Illuminate\Support\Carbon;

beforeEach(function () {
    MediaType::create([
        'code' => 'movie',
        'label_en' => 'Movie',
        'label_fr' => 'Film',
        'api_source' => 'tmdb',
    ]);
});

test('close expired elections command', function () {
    // Past deadline election
    $expiredElection = Election::factory()->create([
        'status' => ElectionStatus::VOTING,
        'deadline' => Carbon::now()->subHour(),
        'election_date' => Carbon::now()->addDay(),
    ]);

    // Future deadline election
    $activeElection = Election::factory()->create([
        'status' => ElectionStatus::VOTING,
        'deadline' => Carbon::now()->addHour(),
        'election_date' => Carbon::now()->addDays(2),
    ]);

    $this->artisan('election:close-expired')
        ->assertSuccessful();

    expect($expiredElection->fresh()->status)->toBe(ElectionStatus::ENDED);
    expect($activeElection->fresh()->status)->toBe(ElectionStatus::VOTING);
});

test('archive old elections command', function () {
    // Past election date (> 24h)
    $oldElection = Election::factory()->create([
        'status' => ElectionStatus::ENDED,
        'election_date' => Carbon::now()->subDays(2),
        'deadline' => Carbon::now()->subDays(3),
    ]);

    // Recent election
    $recentElection = Election::factory()->create([
        'status' => ElectionStatus::ENDED,
        'election_date' => Carbon::now()->subHours(12),
        'deadline' => Carbon::now()->subHours(13),
    ]);

    $this->artisan('election:archive')
        ->assertSuccessful();

    expect($oldElection->fresh()->status)->toBe(ElectionStatus::ARCHIVED);
    expect($recentElection->fresh()->status)->toBe(ElectionStatus::ENDED);
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/Commands/ElectionCommandsTest.php
```
Expected: FAIL

**Step 3: Create CloseExpiredElections command**

Create `backend/app/Console/Commands/CloseExpiredElections.php`:
```php
<?php

namespace App\Console\Commands;

use App\Enums\ElectionStatus;
use App\Models\Election;
use Illuminate\Console\Command;

class CloseExpiredElections extends Command
{
    protected $signature = 'election:close-expired';
    protected $description = 'Close elections that have passed their deadline';

    public function handle(): int
    {
        $elections = Election::where('status', ElectionStatus::VOTING)
            ->where('deadline', '<', now())
            ->get();

        $count = 0;
        foreach ($elections as $election) {
            $election->update(['status' => ElectionStatus::ENDED]);
            $count++;
            $this->info("Closed election: {$election->title}");
        }

        $this->info("Closed $count elections.");

        return Command::SUCCESS;
    }
}
```

**Step 4: Create ArchiveOldElections command**

Create `backend/app/Console/Commands/ArchiveOldElections.php`:
```php
<?php

namespace App\Console\Commands;

use App\Enums\ElectionStatus;
use App\Models\Election;
use Illuminate\Console\Command;

class ArchiveOldElections extends Command
{
    protected $signature = 'election:archive';
    protected $description = 'Archive elections 24 hours after election date';

    public function handle(): int
    {
        $archiveAfterHours = config('election.archive_after_hours', 24);

        $elections = Election::where('status', ElectionStatus::ENDED)
            ->where('election_date', '<', now()->subHours($archiveAfterHours))
            ->get();

        $count = 0;
        foreach ($elections as $election) {
            $election->update(['status' => ElectionStatus::ARCHIVED]);
            $count++;
            $this->info("Archived election: {$election->title}");
        }

        $this->info("Archived $count elections.");

        return Command::SUCCESS;
    }
}
```

**Step 5: Schedule commands**

Edit `backend/routes/console.php`:
```php
<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('election:close-expired')->everyMinute();
Schedule::command('election:archive')->hourly();
```

**Step 6: Run tests**

```bash
php artisan test tests/Feature/Commands/ElectionCommandsTest.php
```
Expected: PASS

**Step 7: Commit**

```bash
git add .
git commit -m "feat: add scheduled commands for election lifecycle"
```

---

## Task 6: Run Full Test Suite

**Step 1: Run all tests**

```bash
php artisan test
```
Expected: All PASS

**Step 2: Final commit**

```bash
git add .
git commit -m "chore: phase 4 complete - voting and results"
```

---

## Phase 4 Completion Checklist

- [ ] DuelGeneratorService
- [ ] Duel voting endpoints (next, vote, history)
- [ ] CondorcetService (preference graph, rankings, winners)
- [ ] Results endpoint
- [ ] CloseExpiredElections command
- [ ] ArchiveOldElections command
- [ ] Scheduled command registration
- [ ] All feature tests passing
