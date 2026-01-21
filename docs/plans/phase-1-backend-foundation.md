# Phase 1: Backend Foundation

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Create database schema, models, and Sanctum authentication infrastructure.

**Architecture:** Laravel 11 API-only project with Laravel Sanctum for API token authentication. All database tables with migrations, models, and relationships. Pest for testing.

**Tech Stack:** Laravel 11, PHP 8.3+, PostgreSQL, Laravel Sanctum, Pest

**Prerequisites:** Phase 0 complete (Laravel project initialized, dev tools installed, CI/CD setup)

---

> **Note:** Project initialization, dev tools, CI/CD, and Sentry are set up in **Phase 0**.
> This phase assumes you have a working Laravel project with PostgreSQL and Redis configured.

---

## Task 1: Install and Configure Laravel Sanctum

**Files:**
- Modify: `backend/composer.json`
- Create: `backend/config/sanctum.php`
- Create: `backend/database/migrations/xxxx_create_personal_access_tokens_table.php`

**Step 1: Install Sanctum package**

```bash
cd /Users/lounis/dev/ScreenBuddies/backend
composer require laravel/sanctum
```

**Step 2: Publish Sanctum config and migration**

```bash
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

**Step 3: Configure token expiration**

Edit `backend/config/sanctum.php`:
```php
'expiration' => 15, // Access token expires in 15 minutes

// Custom: Refresh token expiration (7 days in minutes)
'refresh_expiration' => 10080,
```

**Step 4: Run migration for personal_access_tokens table**

```bash
php artisan migrate
```

**Step 5: Commit**

```bash
git add .
git commit -m "chore: install and configure Laravel Sanctum"
```

---

## Task 2: Install Pest Testing Framework

**Files:**
- Modify: `backend/composer.json`
- Create: `backend/tests/Pest.php`

**Step 1: Install Pest**

```bash
cd /Users/lounis/dev/ScreenBuddies/backend
composer require pestphp/pest --dev --with-all-dependencies
composer require pestphp/pest-plugin-laravel --dev
```

**Step 2: Initialize Pest**

```bash
php artisan pest:install
```

**Step 3: Run tests to verify**

```bash
php artisan test
```
Expected: Tests pass (default Laravel tests)

**Step 4: Commit**

```bash
git add .
git commit -m "chore: install Pest testing framework"
```

---

## Task 3: Create User Migration and Model

**Files:**
- Modify: `backend/database/migrations/0001_01_01_000000_create_users_table.php`
- Modify: `backend/app/Models/User.php`
- Create: `backend/tests/Unit/Models/UserTest.php`

**Step 1: Write User model test**

Create `backend/tests/Unit/Models/UserTest.php`:
```php
<?php

use App\Models\User;

test('user has required fillable attributes', function () {
    $user = new User();

    expect($user->getFillable())->toContain('email')
        ->toContain('password')
        ->toContain('display_name');
});

test('user has Sanctum tokens trait', function () {
    $user = new User();

    expect(method_exists($user, 'tokens'))->toBeTrue();
    expect(method_exists($user, 'createToken'))->toBeTrue();
});

test('user password is hidden', function () {
    $user = new User();

    expect($user->getHidden())->toContain('password');
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test tests/Unit/Models/UserTest.php
```
Expected: FAIL (attributes not configured yet)

**Step 3: Update User migration**

Replace `backend/database/migrations/0001_01_01_000000_create_users_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('display_name', 100)->nullable();
            $table->string('avatar_url', 500)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_banned')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
```

**Step 4: Update User model**

Replace `backend/app/Models/User.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'email',
        'password',
        'display_name',
        'avatar_url',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_banned' => 'boolean',
        ];
    }

    public function preference(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }
}
```

**Step 5: Run migration**

```bash
php artisan migrate:fresh
```

**Step 6: Run test to verify it passes**

```bash
php artisan test tests/Unit/Models/UserTest.php
```
Expected: PASS

**Step 7: Commit**

```bash
git add .
git commit -m "feat: add User model with Sanctum support"
```

---

## Task 4: Create UserPreference Migration and Model

**Files:**
- Create: `backend/database/migrations/2024_01_01_000001_create_user_preferences_table.php`
- Create: `backend/app/Models/UserPreference.php`
- Create: `backend/tests/Unit/Models/UserPreferenceTest.php`

**Step 1: Write UserPreference model test**

Create `backend/tests/Unit/Models/UserPreferenceTest.php`:
```php
<?php

use App\Models\UserPreference;

test('user preference belongs to user', function () {
    $preference = new UserPreference();

    expect(method_exists($preference, 'user'))->toBeTrue();
});

test('user preference has notification settings', function () {
    $preference = new UserPreference();

    expect($preference->getFillable())->toContain('locale')
        ->toContain('notif_email')
        ->toContain('notif_push');
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test tests/Unit/Models/UserPreferenceTest.php
```
Expected: FAIL

**Step 3: Create migration**

```bash
php artisan make:migration create_user_preferences_table
```

Edit the created migration:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
            $table->string('locale', 5)->default('en');
            $table->boolean('notif_email')->default(true);
            $table->boolean('notif_push')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
```

**Step 4: Create UserPreference model**

Create `backend/app/Models/UserPreference.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'locale',
        'notif_email',
        'notif_push',
    ];

    protected function casts(): array
    {
        return [
            'notif_email' => 'boolean',
            'notif_push' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

**Step 5: Run migration and tests**

```bash
php artisan migrate
php artisan test tests/Unit/Models/UserPreferenceTest.php
```
Expected: PASS

**Step 6: Commit**

```bash
git add .
git commit -m "feat: add UserPreference model"
```

---

## Task 5: Create MediaType Migration and Model

**Files:**
- Create: `backend/database/migrations/2024_01_01_000001_create_media_types_table.php`
- Create: `backend/app/Models/MediaType.php`
- Create: `backend/tests/Unit/Models/MediaTypeTest.php`

**Step 1: Write MediaType model test**

Create `backend/tests/Unit/Models/MediaTypeTest.php`:
```php
<?php

use App\Models\MediaType;

test('media type has required fillable attributes', function () {
    $mediaType = new MediaType();

    expect($mediaType->getFillable())->toContain('code')
        ->toContain('label')
        ->toContain('api_source');
});

test('media type casts is_active to boolean', function () {
    $mediaType = new MediaType();

    expect($mediaType->getCasts())->toHaveKey('is_active');
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test tests/Unit/Models/MediaTypeTest.php
```
Expected: FAIL (model doesn't exist)

**Step 3: Create migration**

```bash
php artisan make:migration create_media_types_table
```

Edit the created migration:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('label', 100);
            $table->enum('api_source', ['tmdb', 'rawg', 'bgg', 'custom']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_types');
    }
};
```

**Step 4: Create MediaType model**

Create `backend/app/Models/MediaType.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'label',
        'api_source',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function elections(): HasMany
    {
        return $this->hasMany(Election::class);
    }
}
```

**Step 5: Run migration**

```bash
php artisan migrate
```

**Step 6: Run test to verify it passes**

```bash
php artisan test tests/Unit/Models/MediaTypeTest.php
```
Expected: PASS

**Step 7: Commit**

```bash
git add .
git commit -m "feat: add MediaType model"
```

---

## Task 6: Create Friendship Migration and Model

**Files:**
- Create: `backend/database/migrations/2024_01_01_000002_create_friendships_table.php`
- Create: `backend/app/Models/Friendship.php`
- Create: `backend/app/Enums/FriendshipStatus.php`
- Create: `backend/tests/Unit/Models/FriendshipTest.php`

**Step 1: Write Friendship model test**

Create `backend/tests/Unit/Models/FriendshipTest.php`:
```php
<?php

use App\Models\Friendship;
use App\Models\User;
use App\Enums\FriendshipStatus;

test('friendship belongs to requester and addressee', function () {
    $friendship = new Friendship();

    expect(method_exists($friendship, 'requester'))->toBeTrue();
    expect(method_exists($friendship, 'addressee'))->toBeTrue();
});

test('friendship status is cast to enum', function () {
    $friendship = new Friendship();

    expect($friendship->getCasts())->toHaveKey('status');
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test tests/Unit/Models/FriendshipTest.php
```
Expected: FAIL

**Step 3: Create FriendshipStatus enum**

Create `backend/app/Enums/FriendshipStatus.php`:
```php
<?php

namespace App\Enums;

enum FriendshipStatus: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case DECLINED = 'declined';
}
```

**Step 4: Create migration**

```bash
php artisan make:migration create_friendships_table
```

Edit the created migration:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('friendships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('addressee_id')->constrained('users')->onDelete('cascade');
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->unique(['requester_id', 'addressee_id']);
            $table->index(['requester_id', 'addressee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('friendships');
    }
};
```

**Step 5: Create Friendship model**

Create `backend/app/Models/Friendship.php`:
```php
<?php

namespace App\Models;

use App\Enums\FriendshipStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Friendship extends Model
{
    use HasFactory;

    protected $fillable = [
        'requester_id',
        'addressee_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => FriendshipStatus::class,
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function addressee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'addressee_id');
    }
}
```

**Step 6: Run migration and tests**

```bash
php artisan migrate
php artisan test tests/Unit/Models/FriendshipTest.php
```
Expected: PASS

**Step 7: Commit**

```bash
git add .
git commit -m "feat: add Friendship model with status enum"
```

---

## Task 7: Create Election Migration and Model

**Files:**
- Create: `backend/database/migrations/2024_01_01_000003_create_elections_table.php`
- Create: `backend/app/Models/Election.php`
- Create: `backend/app/Enums/ElectionStatus.php`
- Create: `backend/tests/Unit/Models/ElectionTest.php`

**Step 1: Write Election model test**

Create `backend/tests/Unit/Models/ElectionTest.php`:
```php
<?php

use App\Models\Election;
use App\Enums\ElectionStatus;

test('election has required relationships', function () {
    $election = new Election();

    expect(method_exists($election, 'maestro'))->toBeTrue();
    expect(method_exists($election, 'mediaType'))->toBeTrue();
    expect(method_exists($election, 'candidates'))->toBeTrue();
    expect(method_exists($election, 'voters'))->toBeTrue();
});

test('election status is cast to enum', function () {
    $election = new Election();

    expect($election->getCasts())->toHaveKey('status');
});

test('election dates are cast to datetime', function () {
    $election = new Election();
    $casts = $election->getCasts();

    expect($casts)->toHaveKey('election_date');
    expect($casts)->toHaveKey('deadline');
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test tests/Unit/Models/ElectionTest.php
```
Expected: FAIL

**Step 3: Create ElectionStatus enum**

Create `backend/app/Enums/ElectionStatus.php`:
```php
<?php

namespace App\Enums;

enum ElectionStatus: string
{
    case DRAFT = 'draft';
    case CAMPAIGN = 'campaign';
    case VOTING = 'voting';
    case ENDED = 'ended';
    case ARCHIVED = 'archived';
}
```

**Step 4: Create migration**

```bash
php artisan make:migration create_elections_table
```

Edit the created migration:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('invite_token', 64)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('media_type_id')->constrained('media_types');
            $table->foreignId('maestro_id')->constrained('users')->onDelete('cascade');
            $table->tinyInteger('winner_count')->default(1);
            $table->dateTime('election_date');
            $table->dateTime('deadline');
            $table->dateTime('campaign_end')->nullable();
            $table->boolean('allow_suggestions')->default(false);
            $table->boolean('auto_approve')->default(false);
            $table->string('status')->default('voting');
            $table->timestamps();

            $table->index('status');
            $table->index('deadline');
            $table->index('maestro_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elections');
    }
};
```

**Step 5: Create Election model**

Create `backend/app/Models/Election.php`:
```php
<?php

namespace App\Models;

use App\Enums\ElectionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Election extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'invite_token',
        'title',
        'description',
        'media_type_id',
        'maestro_id',
        'winner_count',
        'election_date',
        'deadline',
        'campaign_end',
        'allow_suggestions',
        'auto_approve',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'election_date' => 'datetime',
            'deadline' => 'datetime',
            'campaign_end' => 'datetime',
            'allow_suggestions' => 'boolean',
            'auto_approve' => 'boolean',
            'status' => ElectionStatus::class,
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Election $election) {
            if (empty($election->uuid)) {
                $election->uuid = Str::uuid();
            }
            if (empty($election->invite_token)) {
                $election->invite_token = Str::random(64);
            }
        });
    }

    public function maestro(): BelongsTo
    {
        return $this->belongsTo(User::class, 'maestro_id');
    }

    public function mediaType(): BelongsTo
    {
        return $this->belongsTo(MediaType::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }

    public function voters(): HasMany
    {
        return $this->hasMany(Voter::class);
    }
}
```

**Step 6: Run migration and tests**

```bash
php artisan migrate
php artisan test tests/Unit/Models/ElectionTest.php
```
Expected: PASS

**Step 7: Commit**

```bash
git add .
git commit -m "feat: add Election model with status enum"
```

---

## Task 8: Create Candidate Migration and Model

**Files:**
- Create: `backend/database/migrations/2024_01_01_000004_create_candidates_table.php`
- Create: `backend/app/Models/Candidate.php`
- Create: `backend/tests/Unit/Models/CandidateTest.php`

**Step 1: Write Candidate model test**

Create `backend/tests/Unit/Models/CandidateTest.php`:
```php
<?php

use App\Models\Candidate;

test('candidate belongs to election', function () {
    $candidate = new Candidate();

    expect(method_exists($candidate, 'election'))->toBeTrue();
});

test('candidate can have suggester', function () {
    $candidate = new Candidate();

    expect(method_exists($candidate, 'suggestedBy'))->toBeTrue();
});

test('candidate metadata is cast to array', function () {
    $candidate = new Candidate();

    expect($candidate->getCasts())->toHaveKey('metadata');
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test tests/Unit/Models/CandidateTest.php
```
Expected: FAIL

**Step 3: Create migration**

```bash
php artisan make:migration create_candidates_table
```

Edit the created migration:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained('elections')->onDelete('cascade');
            $table->string('external_id', 100);
            $table->string('title');
            $table->string('poster_url', 500)->nullable();
            $table->smallInteger('year')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('suggested_by')->nullable()->constrained('users')->onDelete('set null');
            $table->boolean('is_approved')->default(true);
            $table->timestamps();

            $table->unique(['election_id', 'external_id']);
            $table->index('election_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
```

**Step 4: Create Candidate model**

Create `backend/app/Models/Candidate.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Candidate extends Model
{
    use HasFactory;

    protected $fillable = [
        'election_id',
        'external_id',
        'title',
        'poster_url',
        'year',
        'metadata',
        'suggested_by',
        'is_approved',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_approved' => 'boolean',
            'year' => 'integer',
        ];
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function suggestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suggested_by');
    }
}
```

**Step 5: Run migration and tests**

```bash
php artisan migrate
php artisan test tests/Unit/Models/CandidateTest.php
```
Expected: PASS

**Step 6: Commit**

```bash
git add .
git commit -m "feat: add Candidate model"
```

---

## Task 9: Create Voter Migration and Model

**Files:**
- Create: `backend/database/migrations/2024_01_01_000005_create_voters_table.php`
- Create: `backend/app/Models/Voter.php`
- Create: `backend/tests/Unit/Models/VoterTest.php`

**Step 1: Write Voter model test**

Create `backend/tests/Unit/Models/VoterTest.php`:
```php
<?php

use App\Models\Voter;

test('voter belongs to election and user', function () {
    $voter = new Voter();

    expect(method_exists($voter, 'election'))->toBeTrue();
    expect(method_exists($voter, 'user'))->toBeTrue();
});

test('voter has votes JSON and duel_count', function () {
    $voter = new Voter();

    expect($voter->getCasts())->toHaveKey('votes');
    expect($voter->getFillable())->toContain('duel_count');
});

test('voter validates votes JSON format', function () {
    $voter = new Voter();

    // Valid votes
    $voter->votes = ['1_3' => 1, '2_5' => null];
    expect($voter->votes)->toHaveKey('1_3');

    // Invalid key format
    expect(fn() => $voter->votes = ['abc' => 1])
        ->toThrow(InvalidArgumentException::class);

    // Invalid key ordering (larger_smaller)
    expect(fn() => $voter->votes = ['3_1' => 1])
        ->toThrow(InvalidArgumentException::class);

    // Invalid winner (not in pair)
    expect(fn() => $voter->votes = ['1_3' => 5])
        ->toThrow(InvalidArgumentException::class);
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test tests/Unit/Models/VoterTest.php
```
Expected: FAIL

**Step 3: Create migration**

```bash
php artisan make:migration create_voters_table
```

Edit the created migration:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained('elections')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->json('votes')->default('{}');  // Compact duel storage: {"1_2": 1, "1_3": 3, ...}
            $table->smallInteger('duel_count')->default(0);
            $table->timestamp('joined_at');
            $table->boolean('completed')->default(false);
            $table->timestamps();

            $table->unique(['election_id', 'user_id']);
            $table->index('election_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voters');
    }
};
```

**Step 4: Create Voter model**

Create `backend/app/Models/Voter.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voter extends Model
{
    use HasFactory;

    protected $fillable = [
        'election_id',
        'user_id',
        'votes',
        'duel_count',
        'joined_at',
        'completed',
    ];

    protected function casts(): array
    {
        return [
            'votes' => 'array',  // JSON blob: {"1_2": 1, "1_3": 3, ...}
            'duel_count' => 'integer',
            'joined_at' => 'datetime',
            'completed' => 'boolean',
        ];
    }

    /**
     * Validate and set votes JSON.
     *
     * @throws \InvalidArgumentException if votes format is invalid
     */
    public function setVotesAttribute(array $votes): void
    {
        foreach ($votes as $key => $winnerId) {
            // 1. Validate key format (digits_digits)
            if (!preg_match('/^\d+_\d+$/', $key)) {
                throw new \InvalidArgumentException("Invalid vote key format: $key");
            }

            // 2. Validate smaller_larger ordering
            [$a, $b] = explode('_', $key);
            if ((int)$a >= (int)$b) {
                throw new \InvalidArgumentException("Vote key must be smaller_larger: $key");
            }

            // 3. Validate winner is one of the pair (or null for skip)
            if ($winnerId !== null && $winnerId != $a && $winnerId != $b) {
                throw new \InvalidArgumentException("Winner must be one of the candidates in pair $key: got $winnerId");
            }
        }

        $this->attributes['votes'] = json_encode($votes);
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

**Step 5: Run migration and tests**

```bash
php artisan migrate
php artisan test tests/Unit/Models/VoterTest.php
```
Expected: PASS

**Step 6: Commit**

```bash
git add .
git commit -m "feat: add Voter model"
```

---

## Task 10: Add User Relationships

**Files:**
- Modify: `backend/app/Models/User.php`
- Create: `backend/tests/Unit/Models/UserRelationshipsTest.php`

**Step 1: Write relationship tests**

Create `backend/tests/Unit/Models/UserRelationshipsTest.php`:
```php
<?php

use App\Models\User;

test('user has elections as maestro', function () {
    $user = new User();

    expect(method_exists($user, 'elections'))->toBeTrue();
});

test('user has voters (participations)', function () {
    $user = new User();

    expect(method_exists($user, 'voters'))->toBeTrue();
});

test('user has friendships sent and received', function () {
    $user = new User();

    expect(method_exists($user, 'sentFriendRequests'))->toBeTrue();
    expect(method_exists($user, 'receivedFriendRequests'))->toBeTrue();
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test tests/Unit/Models/UserRelationshipsTest.php
```
Expected: FAIL

**Step 3: Add relationships to User model**

Add to `backend/app/Models/User.php` (after existing methods):
```php
use Illuminate\Database\Eloquent\Relations\HasMany;

// Add these methods to the User class:

public function elections(): HasMany
{
    return $this->hasMany(Election::class, 'maestro_id');
}

public function voters(): HasMany
{
    return $this->hasMany(Voter::class);
}

public function sentFriendRequests(): HasMany
{
    return $this->hasMany(Friendship::class, 'requester_id');
}

public function receivedFriendRequests(): HasMany
{
    return $this->hasMany(Friendship::class, 'addressee_id');
}
```

**Step 4: Run tests**

```bash
php artisan test tests/Unit/Models/UserRelationshipsTest.php
```
Expected: PASS

**Step 5: Run all tests**

```bash
php artisan test
```
Expected: All PASS

**Step 6: Commit**

```bash
git add .
git commit -m "feat: add User relationships"
```

---

## Task 11: Create MediaType Seeder

**Files:**
- Create: `backend/database/seeders/MediaTypeSeeder.php`
- Modify: `backend/database/seeders/DatabaseSeeder.php`

**Step 1: Create seeder**

```bash
php artisan make:seeder MediaTypeSeeder
```

Edit `backend/database/seeders/MediaTypeSeeder.php`:
```php
<?php

namespace Database\Seeders;

use App\Models\MediaType;
use Illuminate\Database\Seeder;

class MediaTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'code' => 'movie',
                'label' => 'media_type.movie',
                'api_source' => 'tmdb',
                'is_active' => true,
            ],
            [
                'code' => 'tvshow',
                'label' => 'media_type.tvshow',
                'api_source' => 'tmdb',
                'is_active' => true,
            ],
            [
                'code' => 'videogame',
                'label' => 'media_type.videogame',
                'api_source' => 'rawg',
                'is_active' => true,
            ],
            // Placeholders - set is_active = false until implementation complete
            // See docs/future-ideas.md for details
            [
                'code' => 'boardgame',
                'label' => 'media_type.boardgame',
                'api_source' => 'bgg',
                'is_active' => false,  // Placeholder
            ],
            [
                'code' => 'theater',
                'label' => 'media_type.theater',
                'api_source' => 'custom',
                'is_active' => false,  // Placeholder
            ],
        ];

        foreach ($types as $type) {
            MediaType::updateOrCreate(
                ['code' => $type['code']],
                $type
            );
        }
    }
}
```

**Step 2: Register seeder**

Edit `backend/database/seeders/DatabaseSeeder.php`:
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            MediaTypeSeeder::class,
        ]);
    }
}
```

**Step 3: Run seeder**

```bash
php artisan db:seed
```

**Step 4: Verify**

```bash
php artisan tinker --execute="App\Models\MediaType::count()"
```
Expected: `5`

**Step 5: Commit**

```bash
git add .
git commit -m "feat: add MediaType seeder with 5 types"
```

---

## Task 12: Run Full Test Suite and Final Commit

**Step 1: Run all tests**

```bash
php artisan test
```
Expected: All PASS

**Step 2: Fresh migration with seed**

```bash
php artisan migrate:fresh --seed
```

**Step 3: Verify database structure**

```bash
php artisan tinker --execute="Schema::getColumnListing('users')"
php artisan tinker --execute="Schema::getColumnListing('elections')"
```

**Step 4: Final commit**

```bash
git add .
git commit -m "chore: phase 1 complete - backend foundation"
```

---

## Phase 1 Completion Checklist

> **Prerequisite:** Phase 0 complete (Laravel project, dev tools, CI/CD, Sentry)

- [ ] Laravel Sanctum configured
- [ ] Pest testing framework installed
- [ ] All 7 migrations created and run (User, UserPreference, MediaType, Friendship, Election, Candidate, Voter)
- [ ] All 7 models with relationships (Voter includes votes JSON for compact duel storage)
- [ ] 2 enums (ElectionStatus, FriendshipStatus)
- [ ] MediaType seeder with 5 types
- [ ] All unit tests passing

**Note:** Duel votes are stored as JSON in the Voter table (not a separate Duel table) for scalability.
