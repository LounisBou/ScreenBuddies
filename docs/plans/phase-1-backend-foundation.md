# Phase 1: Backend Foundation

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Set up Laravel 11 project with database schema, models, and JWT authentication.

**Architecture:** Fresh Laravel 11 API-only project with JWT auth via tymon/jwt-auth. All 8 database tables with migrations, models, and relationships. Pest for testing.

**Tech Stack:** Laravel 11, PHP 8.3+, MySQL, JWT (tymon/jwt-auth), Pest

**Prerequisites:** PHP 8.3+, Composer, MySQL running locally

---

## Task 1: Create Laravel Project

**Files:**
- Create: `backend/` (new Laravel project)

**Step 1: Create new Laravel project**

```bash
cd /Users/lounis/dev/ScreenBuddies
composer create-project laravel/laravel backend
```

**Step 2: Verify installation**

```bash
cd backend && php artisan --version
```
Expected: `Laravel Framework 11.x.x`

**Step 3: Configure environment**

Edit `backend/.env`:
```env
APP_NAME=ScreenBuddies
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=screenbuddies
DB_USERNAME=root
DB_PASSWORD=
```

**Step 4: Create database**

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS screenbuddies;"
```

**Step 5: Commit**

```bash
cd /Users/lounis/dev/ScreenBuddies/backend
git init
git add .
git commit -m "chore: initialize Laravel 11 project"
```

---

## Task 2: Install and Configure JWT

**Files:**
- Modify: `backend/composer.json`
- Modify: `backend/config/auth.php`
- Create: `backend/config/jwt.php`

**Step 1: Install jwt-auth package**

```bash
cd /Users/lounis/dev/ScreenBuddies/backend
composer require tymon/jwt-auth
```

**Step 2: Publish JWT config**

```bash
php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"
```

**Step 3: Generate JWT secret**

```bash
php artisan jwt:secret
```

**Step 4: Configure auth guards**

Edit `backend/config/auth.php`:
```php
'defaults' => [
    'guard' => 'api',
    'passwords' => 'users',
],

'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    'api' => [
        'driver' => 'jwt',
        'provider' => 'users',
    ],
],
```

**Step 5: Commit**

```bash
git add .
git commit -m "chore: install and configure JWT authentication"
```

---

## Task 3: Install Pest Testing Framework

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

## Task 4: Create User Migration and Model

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
        ->toContain('display_name')
        ->toContain('locale');
});

test('user has jwt methods', function () {
    $user = new User();

    expect(method_exists($user, 'getJWTIdentifier'))->toBeTrue();
    expect(method_exists($user, 'getJWTCustomClaims'))->toBeTrue();
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
            $table->string('locale', 5)->default('en');
            $table->boolean('notif_email')->default(true);
            $table->boolean('notif_push')->default(true);
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
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'email',
        'password',
        'display_name',
        'avatar_url',
        'locale',
        'notif_email',
        'notif_push',
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
            'notif_email' => 'boolean',
            'notif_push' => 'boolean',
        ];
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
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
git commit -m "feat: add User model with JWT support"
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
        ->toContain('label_en')
        ->toContain('label_fr')
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
            $table->string('label_en', 100);
            $table->string('label_fr', 100);
            $table->string('api_source', 50);
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
        'label_en',
        'label_fr',
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

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function duels(): HasMany
    {
        return $this->hasMany(Duel::class);
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

    public function duelsAsA(): HasMany
    {
        return $this->hasMany(Duel::class, 'candidate_a_id');
    }

    public function duelsAsB(): HasMany
    {
        return $this->hasMany(Duel::class, 'candidate_b_id');
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

test('voter has duels relationship', function () {
    $voter = new Voter();

    expect(method_exists($voter, 'duels'))->toBeTrue();
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
        'joined_at',
        'completed',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'completed' => 'boolean',
        ];
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function duels(): HasMany
    {
        return $this->hasMany(Duel::class);
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

## Task 10: Create Invitation Migration and Model

**Files:**
- Create: `backend/database/migrations/2024_01_01_000006_create_invitations_table.php`
- Create: `backend/app/Models/Invitation.php`
- Create: `backend/tests/Unit/Models/InvitationTest.php`

**Step 1: Write Invitation model test**

Create `backend/tests/Unit/Models/InvitationTest.php`:
```php
<?php

use App\Models\Invitation;

test('invitation belongs to election', function () {
    $invitation = new Invitation();

    expect(method_exists($invitation, 'election'))->toBeTrue();
});

test('invitation has token attribute', function () {
    $invitation = new Invitation();

    expect($invitation->getFillable())->toContain('token');
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test tests/Unit/Models/InvitationTest.php
```
Expected: FAIL

**Step 3: Create migration**

```bash
php artisan make:migration create_invitations_table
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
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained('elections')->onDelete('cascade');
            $table->string('email');
            $table->string('token', 64)->unique();
            $table->timestamp('sent_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['election_id', 'email']);
            $table->index('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
```

**Step 4: Create Invitation model**

Create `backend/app/Models/Invitation.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Invitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'election_id',
        'email',
        'token',
        'sent_at',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Invitation $invitation) {
            if (empty($invitation->token)) {
                $invitation->token = Str::random(64);
            }
        });
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }
}
```

**Step 5: Run migration and tests**

```bash
php artisan migrate
php artisan test tests/Unit/Models/InvitationTest.php
```
Expected: PASS

**Step 6: Commit**

```bash
git add .
git commit -m "feat: add Invitation model"
```

---

## Task 11: Create Duel Migration and Model

**Files:**
- Create: `backend/database/migrations/2024_01_01_000007_create_duels_table.php`
- Create: `backend/app/Models/Duel.php`
- Create: `backend/tests/Unit/Models/DuelTest.php`

**Step 1: Write Duel model test**

Create `backend/tests/Unit/Models/DuelTest.php`:
```php
<?php

use App\Models\Duel;

test('duel belongs to election, voter, and candidates', function () {
    $duel = new Duel();

    expect(method_exists($duel, 'election'))->toBeTrue();
    expect(method_exists($duel, 'voter'))->toBeTrue();
    expect(method_exists($duel, 'candidateA'))->toBeTrue();
    expect(method_exists($duel, 'candidateB'))->toBeTrue();
    expect(method_exists($duel, 'winner'))->toBeTrue();
});

test('duel voted_at is cast to datetime', function () {
    $duel = new Duel();

    expect($duel->getCasts())->toHaveKey('voted_at');
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test tests/Unit/Models/DuelTest.php
```
Expected: FAIL

**Step 3: Create migration**

```bash
php artisan make:migration create_duels_table
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
        Schema::create('duels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained('elections')->onDelete('cascade');
            $table->foreignId('voter_id')->constrained('voters')->onDelete('cascade');
            $table->foreignId('candidate_a_id')->constrained('candidates')->onDelete('cascade');
            $table->foreignId('candidate_b_id')->constrained('candidates')->onDelete('cascade');
            $table->foreignId('winner_id')->constrained('candidates')->onDelete('cascade');
            $table->timestamp('voted_at');
            $table->timestamps();

            $table->unique(['voter_id', 'candidate_a_id', 'candidate_b_id']);
            $table->index('voter_id');
            $table->index('election_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duels');
    }
};
```

**Step 4: Create Duel model**

Create `backend/app/Models/Duel.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Duel extends Model
{
    use HasFactory;

    protected $fillable = [
        'election_id',
        'voter_id',
        'candidate_a_id',
        'candidate_b_id',
        'winner_id',
        'voted_at',
    ];

    protected function casts(): array
    {
        return [
            'voted_at' => 'datetime',
        ];
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function voter(): BelongsTo
    {
        return $this->belongsTo(Voter::class);
    }

    public function candidateA(): BelongsTo
    {
        return $this->belongsTo(Candidate::class, 'candidate_a_id');
    }

    public function candidateB(): BelongsTo
    {
        return $this->belongsTo(Candidate::class, 'candidate_b_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Candidate::class, 'winner_id');
    }
}
```

**Step 5: Run migration and tests**

```bash
php artisan migrate
php artisan test tests/Unit/Models/DuelTest.php
```
Expected: PASS

**Step 6: Commit**

```bash
git add .
git commit -m "feat: add Duel model"
```

---

## Task 12: Add User Relationships

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

## Task 13: Create MediaType Seeder

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
                'label_en' => 'Movie',
                'label_fr' => 'Film',
                'api_source' => 'tmdb',
            ],
            [
                'code' => 'tvshow',
                'label_en' => 'TV Show',
                'label_fr' => 'Série TV',
                'api_source' => 'tmdb',
            ],
            [
                'code' => 'videogame',
                'label_en' => 'Video Game',
                'label_fr' => 'Jeu Vidéo',
                'api_source' => 'rawg',
            ],
            [
                'code' => 'boardgame',
                'label_en' => 'Board Game',
                'label_fr' => 'Jeu de Société',
                'api_source' => 'bgg',
            ],
            [
                'code' => 'theater',
                'label_en' => 'Theater',
                'label_fr' => 'Théâtre',
                'api_source' => 'custom',
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

## Task 14: Run Full Test Suite and Final Commit

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

- [ ] Laravel 11 project created
- [ ] JWT authentication configured
- [ ] Pest testing framework installed
- [ ] All 8 migrations created and run
- [ ] All 8 models with relationships
- [ ] 2 enums (ElectionStatus, FriendshipStatus)
- [ ] MediaType seeder with 5 types
- [ ] All unit tests passing
