# Phase 4: Voting & Results

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implement duel voting system, Condorcet ranking algorithm (Ranked Pairs), results display, and scheduled commands for election lifecycle management.

**Architecture:**
- **Compact Storage:** Votes are stored as JSON blob in `Voter.votes` field (not separate Duel rows)
- **DuelGeneratorService:** Generates next pair for voter, reads completed pairs from JSON
- **CondorcetService:** Aggregates from all Voter.votes JSON, implements Ranked Pairs with confidence-weighting
- **VotingController:** Writes votes to Voter.votes JSON
- Scheduled commands handle deadline enforcement and archiving

**Data Model:**
```
Voter.votes JSON format: {"1_2": 1, "1_3": 3, "2_3": 2, ...}
Key = "{smaller_id}_{larger_id}", Value = winner's candidate ID
```

**Tech Stack:** Laravel 11, Laravel Scheduler, Pest

**Prerequisites:** Phase 3 complete (elections, candidates, join system)

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
use App\Models\MediaType;
use App\Services\Election\DuelGeneratorService;

beforeEach(function () {
    $this->mediaType = MediaType::create([
        'code' => 'movie',
        'label' => 'media_type.movie',
        'api_source' => 'tmdb',
    ]);
});

test('generates next duel for voter', function () {
    $user = User::factory()->create();
    $election = Election::factory()->create(['media_type_id' => $this->mediaType->id]);

    $c1 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:1', 'title' => 'Movie 1']);
    $c2 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:2', 'title' => 'Movie 2']);
    $c3 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:3', 'title' => 'Movie 3']);

    $voter = Voter::create([
        'election_id' => $election->id,
        'user_id' => $user->id,
        'joined_at' => now(),
        'votes' => [],
    ]);

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

    // Create voter with completed vote in JSON
    $voter = Voter::create([
        'election_id' => $election->id,
        'user_id' => $user->id,
        'joined_at' => now(),
        'votes' => ["{$c1->id}_{$c2->id}" => $c1->id],  // Only pair already voted
        'duel_count' => 1,
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

    $voter = Voter::create([
        'election_id' => $election->id,
        'user_id' => $user->id,
        'joined_at' => now(),
        'votes' => [],
        'duel_count' => 0,
    ]);

    $service = new DuelGeneratorService();
    $progress = $service->getProgress($voter);

    expect($progress['completed'])->toBe(0);
    expect($progress['total'])->toBe(3); // 3 candidates = 3 pairs
    expect($progress['percentage'])->toBe(0.0);
});

test('records vote in voter JSON', function () {
    $user = User::factory()->create();
    $election = Election::factory()->create(['media_type_id' => $this->mediaType->id]);

    $c1 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:1', 'title' => 'Movie 1']);
    $c2 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:2', 'title' => 'Movie 2']);

    $voter = Voter::create([
        'election_id' => $election->id,
        'user_id' => $user->id,
        'joined_at' => now(),
        'votes' => [],
        'duel_count' => 0,
    ]);

    $service = new DuelGeneratorService();
    $service->recordVote($voter, $c1->id, $c2->id, $c1->id);

    $voter->refresh();
    expect($voter->votes)->toHaveKey("{$c1->id}_{$c2->id}");
    expect($voter->votes["{$c1->id}_{$c2->id}"])->toBe($c1->id);
    expect($voter->duel_count)->toBe(1);
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
use App\Models\Voter;

class DuelGeneratorService
{
    /**
     * Get the next duel for a voter.
     * Reads completed pairs from Voter.votes JSON.
     * Uses simple random selection (advanced active selection can be added later).
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

        // Get voted pairs from JSON
        $votedPairs = array_keys($voter->votes ?? []);

        // Generate all possible pairs and find unvoted ones
        $unvotedPairs = [];
        for ($i = 0; $i < $candidates->count(); $i++) {
            for ($j = $i + 1; $j < $candidates->count(); $j++) {
                $pairKey = $this->normalizePairKey($candidates[$i]->id, $candidates[$j]->id);
                if (!in_array($pairKey, $votedPairs)) {
                    $unvotedPairs[] = [
                        'candidate_a' => $candidates[$i],
                        'candidate_b' => $candidates[$j],
                    ];
                }
            }
        }

        if (empty($unvotedPairs)) {
            return null;
        }

        // Return random unvoted pair (TODO: implement active selection)
        return $unvotedPairs[array_rand($unvotedPairs)];
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
        $completedPairs = $voter->duel_count;

        $percentage = $totalPairs > 0 ? round(($completedPairs / $totalPairs) * 100, 1) : 0;

        return [
            'completed' => $completedPairs,
            'total' => $totalPairs,
            'percentage' => $percentage,
        ];
    }

    /**
     * Record a vote in the voter's JSON blob
     *
     * @param int $candidateA Candidate A ID
     * @param int $candidateB Candidate B ID
     * @param int $winnerId The chosen winner
     */
    public function recordVote(Voter $voter, int $candidateA, int $candidateB, int $winnerId): void
    {
        $pairKey = $this->normalizePairKey($candidateA, $candidateB);

        $votes = $voter->votes ?? [];
        $votes[$pairKey] = $winnerId;

        $voter->update([
            'votes' => $votes,
            'duel_count' => count($votes),
        ]);
    }

    /**
     * Check if a pair has already been voted on
     */
    public function hasVoted(Voter $voter, int $candidateA, int $candidateB): bool
    {
        $pairKey = $this->normalizePairKey($candidateA, $candidateB);
        return isset($voter->votes[$pairKey]);
    }

    /**
     * Calculate total number of pairs: n*(n-1)/2
     */
    private function calculateTotalPairs(int $n): int
    {
        return (int) ($n * ($n - 1) / 2);
    }

    /**
     * Normalize pair key to always have smaller ID first
     */
    private function normalizePairKey(int $a, int $b): string
    {
        return min($a, $b) . '_' . max($a, $b);
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
git commit -m "feat: add DuelGeneratorService with JSON vote storage"
```

---

## Task 2: Create Voting Controller

**Files:**
- Create: `backend/app/Http/Controllers/Api/V1/VotingController.php`
- Create: `backend/app/Http/Resources/NextDuelResource.php`
- Create: `backend/app/Http/Requests/Voting/CastVoteRequest.php`
- Modify: `backend/routes/api.php`
- Create: `backend/tests/Feature/Voting/VotingTest.php`

**Step 1: Write voting feature tests**

Create `backend/tests/Feature/Voting/VotingTest.php`:
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
        'label' => 'media_type.movie',
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

    Voter::create([
        'election_id' => $election->id,
        'user_id' => $user->id,
        'joined_at' => now(),
        'votes' => [],
    ]);

    $token = JWTAuth::fromUser($user);

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->getJson("/api/v1/elections/{$election->uuid}/vote/next");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'candidate_a' => ['id', 'title'],
                'candidate_b' => ['id', 'title'],
                'progress' => ['completed', 'total', 'percentage'],
            ],
        ]);
});

test('voter can cast vote', function () {
    $user = User::factory()->create();
    $election = Election::factory()->create([
        'media_type_id' => $this->mediaType->id,
        'status' => ElectionStatus::VOTING,
    ]);

    $c1 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:1', 'title' => 'Movie 1']);
    $c2 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:2', 'title' => 'Movie 2']);

    $voter = Voter::create([
        'election_id' => $election->id,
        'user_id' => $user->id,
        'joined_at' => now(),
        'votes' => [],
    ]);

    $token = JWTAuth::fromUser($user);

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->postJson("/api/v1/elections/{$election->uuid}/vote", [
            'candidate_a_id' => $c1->id,
            'candidate_b_id' => $c2->id,
            'winner_id' => $c1->id,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.voted', true);

    // Verify vote stored in Voter.votes JSON
    $voter->refresh();
    expect($voter->votes)->toHaveKey("{$c1->id}_{$c2->id}");
    expect($voter->votes["{$c1->id}_{$c2->id}"])->toBe($c1->id);
});

test('cannot vote on same pair twice', function () {
    $user = User::factory()->create();
    $election = Election::factory()->create([
        'media_type_id' => $this->mediaType->id,
        'status' => ElectionStatus::VOTING,
    ]);

    $c1 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:1', 'title' => 'Movie 1']);
    $c2 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:2', 'title' => 'Movie 2']);

    $voter = Voter::create([
        'election_id' => $election->id,
        'user_id' => $user->id,
        'joined_at' => now(),
        'votes' => ["{$c1->id}_{$c2->id}" => $c1->id],  // Already voted
        'duel_count' => 1,
    ]);

    $token = JWTAuth::fromUser($user);

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->postJson("/api/v1/elections/{$election->uuid}/vote", [
            'candidate_a_id' => $c1->id,
            'candidate_b_id' => $c2->id,
            'winner_id' => $c2->id,
        ]);

    $response->assertStatus(400)
        ->assertJsonPath('error.code', 'PAIR_ALREADY_VOTED');
});

test('cannot vote on ended election', function () {
    $user = User::factory()->create();
    $election = Election::factory()->create([
        'media_type_id' => $this->mediaType->id,
        'status' => ElectionStatus::ENDED,
    ]);

    $c1 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:1', 'title' => 'Movie 1']);
    $c2 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:2', 'title' => 'Movie 2']);

    Voter::create([
        'election_id' => $election->id,
        'user_id' => $user->id,
        'joined_at' => now(),
        'votes' => [],
    ]);

    $token = JWTAuth::fromUser($user);

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->postJson("/api/v1/elections/{$election->uuid}/vote", [
            'candidate_a_id' => $c1->id,
            'candidate_b_id' => $c2->id,
            'winner_id' => $c1->id,
        ]);

    $response->assertStatus(400)
        ->assertJsonPath('error.code', 'ELECTION_CLOSED');
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/Voting/VotingTest.php
```
Expected: FAIL

**Step 3: Create NextDuelResource**

Create `backend/app/Http/Resources/NextDuelResource.php`:
```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NextDuelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'candidate_a' => new CandidateResource($this->resource['candidate_a']),
            'candidate_b' => new CandidateResource($this->resource['candidate_b']),
            'progress' => $this->resource['progress'],
        ];
    }
}
```

**Step 4: Create CastVoteRequest**

Create `backend/app/Http/Requests/Voting/CastVoteRequest.php`:
```php
<?php

namespace App\Http\Requests\Voting;

use Illuminate\Foundation\Http\FormRequest;

class CastVoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'candidate_a_id' => ['required', 'integer'],
            'candidate_b_id' => ['required', 'integer', 'different:candidate_a_id'],
            'winner_id' => ['required', 'integer'],
        ];
    }
}
```

**Step 5: Create VotingController**

Create `backend/app/Http/Controllers/Api/V1/VotingController.php`:
```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ElectionStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Voting\CastVoteRequest;
use App\Http\Resources\CandidateResource;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Voter;
use App\Services\Election\DuelGeneratorService;
use Illuminate\Http\JsonResponse;

class VotingController extends Controller
{
    public function __construct(
        private DuelGeneratorService $duelGeneratorService
    ) {}

    /**
     * Get next duel for voting
     */
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

    /**
     * Cast vote in duel (stores in Voter.votes JSON)
     */
    public function vote(CastVoteRequest $request, string $uuid): JsonResponse
    {
        $election = Election::where('uuid', $uuid)->firstOrFail();

        if ($election->status !== ElectionStatus::VOTING) {
            throw new ApiException(
                'ELECTION_CLOSED',
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

        // Check if already voted on this pair
        if ($this->duelGeneratorService->hasVoted($voter, $candidateA->id, $candidateB->id)) {
            throw new ApiException(
                'PAIR_ALREADY_VOTED',
                'You have already voted on this pair.',
                400
            );
        }

        // Record vote in JSON
        $this->duelGeneratorService->recordVote(
            $voter,
            $candidateA->id,
            $candidateB->id,
            $request->winner_id
        );

        // Get next duel
        $voter->refresh();
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

    /**
     * Get voter's past duels (from Voter.votes JSON)
     */
    public function history(string $uuid): JsonResponse
    {
        $election = Election::where('uuid', $uuid)->firstOrFail();
        $voter = $this->getVoter($election);

        $votes = $voter->votes ?? [];
        $candidateIds = [];

        // Collect all candidate IDs
        foreach (array_keys($votes) as $pairKey) {
            [$a, $b] = explode('_', $pairKey);
            $candidateIds[] = (int) $a;
            $candidateIds[] = (int) $b;
        }

        $candidates = Candidate::whereIn('id', array_unique($candidateIds))
            ->get()
            ->keyBy('id');

        $history = [];
        foreach ($votes as $pairKey => $winnerId) {
            [$aId, $bId] = explode('_', $pairKey);
            $history[] = [
                'pair' => $pairKey,
                'candidate_a' => new CandidateResource($candidates[(int) $aId] ?? null),
                'candidate_b' => new CandidateResource($candidates[(int) $bId] ?? null),
                'winner_id' => $winnerId,
            ];
        }

        return response()->json([
            'data' => $history,
            'meta' => [
                'total' => count($history),
            ],
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

**Step 6: Add routes**

Add to protected routes in `backend/routes/api.php`:
```php
use App\Http\Controllers\Api\V1\VotingController;

// Inside auth:api middleware:
Route::get('elections/{uuid}/vote/next', [VotingController::class, 'next']);
Route::post('elections/{uuid}/vote', [VotingController::class, 'vote']);
Route::get('elections/{uuid}/vote/history', [VotingController::class, 'history']);
```

**Step 7: Run tests**

```bash
php artisan test tests/Feature/Voting/VotingTest.php
```
Expected: PASS

**Step 8: Commit**

```bash
git add .
git commit -m "feat: add voting endpoints with JSON storage"
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
use App\Models\MediaType;
use App\Services\Election\CondorcetService;

beforeEach(function () {
    $this->mediaType = MediaType::create([
        'code' => 'movie',
        'label' => 'media_type.movie',
        'api_source' => 'tmdb',
    ]);
});

test('builds preference graph from voter JSON votes', function () {
    $user = User::factory()->create();
    $election = Election::factory()->create(['media_type_id' => $this->mediaType->id]);

    $c1 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:1', 'title' => 'Movie 1']);
    $c2 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:2', 'title' => 'Movie 2']);
    $c3 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:3', 'title' => 'Movie 3']);

    // Create voter with votes in JSON: C1 beats C2, C1 beats C3, C2 beats C3
    $voter = Voter::create([
        'election_id' => $election->id,
        'user_id' => $user->id,
        'joined_at' => now(),
        'votes' => [
            "{$c1->id}_{$c2->id}" => $c1->id,  // C1 beats C2
            "{$c1->id}_{$c3->id}" => $c1->id,  // C1 beats C3
            "{$c2->id}_{$c3->id}" => $c2->id,  // C2 beats C3
        ],
        'duel_count' => 3,
    ]);

    $service = new CondorcetService();
    $graph = $service->buildPreferenceGraph($election);

    expect($graph[$c1->id][$c2->id])->toBe(1);  // C1 beat C2 once
    expect($graph[$c1->id][$c3->id])->toBe(1);  // C1 beat C3 once
    expect($graph[$c2->id][$c3->id])->toBe(1);  // C2 beat C3 once
    expect($graph[$c2->id][$c1->id])->toBe(0);  // C2 never beat C1
});

test('aggregates votes from multiple voters', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $election = Election::factory()->create(['media_type_id' => $this->mediaType->id]);

    $c1 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:1', 'title' => 'Movie 1']);
    $c2 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:2', 'title' => 'Movie 2']);

    // Voter 1: C1 wins
    Voter::create([
        'election_id' => $election->id,
        'user_id' => $user1->id,
        'joined_at' => now(),
        'votes' => ["{$c1->id}_{$c2->id}" => $c1->id],
        'duel_count' => 1,
    ]);

    // Voter 2: C2 wins
    Voter::create([
        'election_id' => $election->id,
        'user_id' => $user2->id,
        'joined_at' => now(),
        'votes' => ["{$c1->id}_{$c2->id}" => $c2->id],
        'duel_count' => 1,
    ]);

    $service = new CondorcetService();
    $graph = $service->buildPreferenceGraph($election);

    expect($graph[$c1->id][$c2->id])->toBe(1);  // C1 beat C2 once
    expect($graph[$c2->id][$c1->id])->toBe(1);  // C2 beat C1 once
});

test('calculates rankings correctly using Ranked Pairs', function () {
    $user = User::factory()->create();
    $election = Election::factory()->create([
        'media_type_id' => $this->mediaType->id,
        'winner_count' => 2,
    ]);

    $c1 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:1', 'title' => 'Movie 1']);
    $c2 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:2', 'title' => 'Movie 2']);
    $c3 = Candidate::create(['election_id' => $election->id, 'external_id' => 'tmdb:3', 'title' => 'Movie 3']);

    // Clear ranking: C1 > C2 > C3
    Voter::create([
        'election_id' => $election->id,
        'user_id' => $user->id,
        'joined_at' => now(),
        'votes' => [
            "{$c1->id}_{$c2->id}" => $c1->id,
            "{$c1->id}_{$c3->id}" => $c1->id,
            "{$c2->id}_{$c3->id}" => $c2->id,
        ],
        'duel_count' => 3,
    ]);

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

    Voter::create([
        'election_id' => $election->id,
        'user_id' => $user->id,
        'joined_at' => now(),
        'votes' => ["{$c1->id}_{$c2->id}" => $c1->id],
        'duel_count' => 1,
    ]);

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

use App\Models\Election;
use App\Models\Voter;
use Illuminate\Support\Facades\Cache;

/**
 * Implements Ranked Pairs (Tideman) algorithm with confidence-weighting.
 * See docs/condorcet-implementation.md for algorithm details.
 */
class CondorcetService
{
    private const ALPHA = 1;           // Beta prior (Laplace smoothing)
    private const MIN_PAIR_DUELS = 5;  // Minimum data before trusting a pair

    /**
     * Build preference graph by aggregating all voters' JSON votes.
     *
     * @return array<int, array<int, int>> [candidateA][candidateB] = win count
     */
    public function buildPreferenceGraph(Election $election): array
    {
        $candidates = $election->candidates()
            ->where('is_approved', true)
            ->pluck('id')
            ->toArray();

        // Initialize graph
        $graph = [];
        foreach ($candidates as $a) {
            $graph[$a] = [];
            foreach ($candidates as $b) {
                $graph[$a][$b] = 0;
            }
        }

        // Aggregate votes from all voters' JSON blobs
        $voters = Voter::where('election_id', $election->id)->get();

        foreach ($voters as $voter) {
            $votes = $voter->votes ?? [];

            foreach ($votes as $pairKey => $winnerId) {
                [$aId, $bId] = explode('_', $pairKey);
                $aId = (int) $aId;
                $bId = (int) $bId;

                $loserId = ($winnerId === $aId) ? $bId : $aId;

                if (isset($graph[$winnerId][$loserId])) {
                    $graph[$winnerId][$loserId]++;
                }
            }
        }

        return $graph;
    }

    /**
     * Compute pairwise statistics for each pair.
     *
     * @return array<string, array{wins_ij: int, wins_ji: int, total: int}>
     */
    public function computePairwiseStats(Election $election): array
    {
        $graph = $this->buildPreferenceGraph($election);
        $candidates = array_keys($graph);
        $stats = [];

        for ($i = 0; $i < count($candidates); $i++) {
            for ($j = $i + 1; $j < count($candidates); $j++) {
                $a = $candidates[$i];
                $b = $candidates[$j];
                $key = "{$a}_{$b}";

                $stats[$key] = [
                    'wins_ij' => $graph[$a][$b],
                    'wins_ji' => $graph[$b][$a],
                    'total' => $graph[$a][$b] + $graph[$b][$a],
                ];
            }
        }

        return $stats;
    }

    /**
     * Calculate rankings using Ranked Pairs with confidence-weighting.
     */
    public function calculateRankings(Election $election): array
    {
        $cacheKey = "election:{$election->id}:rankings";

        return Cache::remember($cacheKey, 300, function () use ($election) {
            $graph = $this->buildPreferenceGraph($election);
            $candidates = $election->candidates()
                ->where('is_approved', true)
                ->get();

            // Build edges with robust strength
            $edges = $this->buildRankedPairsEdges($graph, $candidates->pluck('id')->toArray());

            // Sort by strength descending
            usort($edges, fn ($a, $b) => $b['strength'] <=> $a['strength']);

            // Lock edges without creating cycles
            $lockedGraph = [];
            foreach ($candidates as $c) {
                $lockedGraph[$c->id] = [];
            }

            foreach ($edges as $edge) {
                if (!$this->createsCycle($lockedGraph, $edge['from'], $edge['to'])) {
                    $lockedGraph[$edge['from']][] = $edge['to'];
                }
            }

            // Calculate scores based on locked graph
            $scores = [];
            foreach ($candidates as $candidate) {
                $outgoing = count($lockedGraph[$candidate->id] ?? []);
                $incoming = 0;
                foreach ($lockedGraph as $from => $tos) {
                    if (in_array($candidate->id, $tos)) {
                        $incoming++;
                    }
                }

                // Also calculate raw win stats
                $totalWins = 0;
                $totalLosses = 0;
                foreach ($candidates as $opponent) {
                    if ($candidate->id !== $opponent->id) {
                        $totalWins += $graph[$candidate->id][$opponent->id] ?? 0;
                        $totalLosses += $graph[$opponent->id][$candidate->id] ?? 0;
                    }
                }

                $scores[] = [
                    'candidate' => $candidate,
                    'rank_score' => $outgoing - $incoming,
                    'outgoing' => $outgoing,
                    'stats' => [
                        'wins' => $totalWins,
                        'losses' => $totalLosses,
                        'win_rate' => ($totalWins + $totalLosses) > 0
                            ? round(($totalWins / ($totalWins + $totalLosses)) * 100, 1)
                            : 0,
                    ],
                ];
            }

            // Sort by rank score (descending), then by total wins, then by candidate ID
            usort($scores, function ($a, $b) {
                if ($a['rank_score'] !== $b['rank_score']) {
                    return $b['rank_score'] <=> $a['rank_score'];
                }
                if ($a['stats']['wins'] !== $b['stats']['wins']) {
                    return $b['stats']['wins'] <=> $a['stats']['wins'];
                }
                return $a['candidate']->id <=> $b['candidate']->id;
            });

            // Add ranks
            $rank = 1;
            foreach ($scores as &$score) {
                $score['rank'] = $rank++;
            }

            return $scores;
        });
    }

    /**
     * Build edges with robust strength for Ranked Pairs.
     */
    private function buildRankedPairsEdges(array $graph, array $candidateIds): array
    {
        $edges = [];

        for ($i = 0; $i < count($candidateIds); $i++) {
            for ($j = $i + 1; $j < count($candidateIds); $j++) {
                $a = $candidateIds[$i];
                $b = $candidateIds[$j];

                $wins_ab = $graph[$a][$b];
                $wins_ba = $graph[$b][$a];
                $n = $wins_ab + $wins_ba;

                if ($n < self::MIN_PAIR_DUELS) {
                    // Not enough data, skip this pair
                    continue;
                }

                // Smoothed probability
                $p_ab = ($wins_ab + self::ALPHA) / ($n + 2 * self::ALPHA);

                // Robust strength with √n penalty
                $strength_ab = max(0, ($p_ab - 0.5) * sqrt($n));
                $strength_ba = max(0, ((1 - $p_ab) - 0.5) * sqrt($n));

                if ($strength_ab > 0) {
                    $edges[] = ['from' => $a, 'to' => $b, 'strength' => $strength_ab];
                }
                if ($strength_ba > 0) {
                    $edges[] = ['from' => $b, 'to' => $a, 'strength' => $strength_ba];
                }
            }
        }

        return $edges;
    }

    /**
     * Check if adding an edge would create a cycle (DFS).
     */
    private function createsCycle(array $graph, int $from, int $to): bool
    {
        // Would adding from -> to create a cycle?
        // Check if there's already a path from 'to' to 'from'
        $visited = [];
        $stack = [$to];

        while (!empty($stack)) {
            $node = array_pop($stack);

            if ($node === $from) {
                return true;  // Cycle detected
            }

            if (isset($visited[$node])) {
                continue;
            }
            $visited[$node] = true;

            foreach ($graph[$node] ?? [] as $neighbor) {
                $stack[] = $neighbor;
            }
        }

        return false;
    }

    /**
     * Get top K winners based on election winner_count.
     */
    public function getWinners(Election $election): array
    {
        $rankings = $this->calculateRankings($election);

        return array_slice($rankings, 0, $election->winner_count);
    }

    /**
     * Clear cached rankings.
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
git commit -m "feat: add CondorcetService with Ranked Pairs algorithm"
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
use App\Models\MediaType;
use App\Enums\ElectionStatus;
use Tymon\JWTAuth\Facades\JWTAuth;

beforeEach(function () {
    $this->mediaType = MediaType::create([
        'code' => 'movie',
        'label' => 'media_type.movie',
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

    // Create voter with vote in JSON (C1 wins)
    Voter::create([
        'election_id' => $election->id,
        'user_id' => $user->id,
        'joined_at' => now(),
        'votes' => ["{$c1->id}_{$c2->id}" => $c1->id],
        'duel_count' => 1,
    ]);

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

    Voter::create([
        'election_id' => $election->id,
        'user_id' => $user->id,
        'joined_at' => now(),
        'votes' => [],
    ]);

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

        // Count total duels from all voters' JSON blobs
        $totalDuels = $election->voters->sum('duel_count');

        $totalPossible = $this->calculateTotalPairs(
            $election->candidates()->where('is_approved', true)->count()
        );

        $voterParticipation = $election->voters->map(function ($voter) use ($totalPossible) {
            return [
                'voter' => [
                    'display_name' => $voter->user->display_name,
                ],
                'duels_completed' => $voter->duel_count,
                'percentage' => $totalPossible > 0
                    ? round(($voter->duel_count / $totalPossible) * 100, 1)
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
        'label' => 'media_type.movie',
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
