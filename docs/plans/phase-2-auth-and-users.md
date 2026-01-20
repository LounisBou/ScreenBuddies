# Phase 2: Authentication & User Management

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implement complete authentication system with Laravel Sanctum, email verification, password reset, user profile, and friendship system.

**Architecture:** Controller → Request validation → Service layer → Response. All auth logic in dedicated services. Form requests for validation. API Resources for consistent JSON responses.

**Tech Stack:** Laravel 11, Laravel Sanctum, Laravel Mail, API Resources, Pest

**Prerequisites:** Phase 1 complete (models, migrations, Sanctum configured)

---

## Task 1: Create Base API Response Structure

**Files:**
- Create: `backend/app/Http/Resources/ApiResource.php`
- Create: `backend/app/Exceptions/ApiException.php`
- Modify: `backend/bootstrap/app.php`

**Step 1: Create ApiException**

Create `backend/app/Exceptions/ApiException.php`:
```php
<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class ApiException extends Exception
{
    public function __construct(
        public string $errorCode,
        string $message,
        public int $httpStatus = 400,
        public array $details = []
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
                'details' => $this->details ?: null,
            ],
        ], $this->httpStatus);
    }
}
```

**Step 2: Configure exception handling**

Edit `backend/bootstrap/app.php`, add to withExceptions:
```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (ValidationException $e) {
        return response()->json([
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'The given data was invalid.',
                'details' => $e->errors(),
            ],
        ], 422);
    });

    $exceptions->render(function (AuthenticationException $e) {
        return response()->json([
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => 'Unauthenticated.',
            ],
        ], 401);
    });
})
```

Add imports at top:
```php
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
```

**Step 3: Commit**

```bash
git add .
git commit -m "feat: add API exception handling"
```

---

## Task 2: Create Auth Controller - Register

**Files:**
- Create: `backend/app/Http/Controllers/Api/V1/AuthController.php`
- Create: `backend/app/Http/Requests/Auth/RegisterRequest.php`
- Create: `backend/app/Http/Resources/UserResource.php`
- Create: `backend/app/Http/Resources/AuthResource.php`
- Create: `backend/routes/api.php` routes
- Create: `backend/tests/Feature/Auth/RegisterTest.php`

**Step 1: Write register test**

Create `backend/tests/Feature/Auth/RegisterTest.php`:
```php
<?php

use App\Models\User;

test('user can register with valid data', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'display_name' => 'Test User',
        'locale' => 'en',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'user' => ['id', 'email', 'display_name', 'locale'],
                'tokens' => ['access_token', 'refresh_token', 'expires_in'],
            ],
        ]);

    $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
});

test('register fails with invalid email', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'email' => 'invalid-email',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

test('register fails with duplicate email', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $response = $this->postJson('/api/v1/auth/register', [
        'email' => 'existing@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422);
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/Auth/RegisterTest.php
```
Expected: FAIL

**Step 3: Create RegisterRequest**

Create `backend/app/Http/Requests/Auth/RegisterRequest.php`:
```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'unique:users,email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'display_name' => ['nullable', 'string', 'max:100'],
            'locale' => ['nullable', 'string', 'in:en,fr'],
        ];
    }
}
```

**Step 4: Create UserResource**

Create `backend/app/Http/Resources/UserResource.php`:
```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'display_name' => $this->display_name,
            'avatar_url' => $this->avatar_url,
            'email_verified' => $this->email_verified_at !== null,
            'locale' => $this->preference?->locale ?? 'en',
            'notif_email' => $this->preference?->notif_email ?? true,
            'notif_push' => $this->preference?->notif_push ?? true,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
```

**Step 5: Create AuthResource**

Create `backend/app/Http/Resources/AuthResource.php`:
```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthResource extends JsonResource
{
    public function __construct(
        public $user,
        public string $accessToken,
        public string $refreshToken,
        public int $expiresIn
    ) {
        parent::__construct($user);
    }

    public function toArray(Request $request): array
    {
        return [
            'user' => new UserResource($this->user),
            'tokens' => [
                'access_token' => $this->accessToken,
                'refresh_token' => $this->refreshToken,
                'expires_in' => $this->expiresIn,
            ],
        ];
    }
}
```

**Step 6: Create AuthController with register**

Create `backend/app/Http/Controllers/Api/V1/AuthController.php`:
```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\AuthResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'email' => $request->email,
            'password' => $request->password,
            'display_name' => $request->display_name,
        ]);

        // Create user preferences
        $user->preference()->create([
            'locale' => $request->locale ?? 'en',
        ]);

        $user->load('preference');

        // Create Sanctum tokens
        $accessToken = $user->createToken('access', ['access'], now()->addMinutes(config('sanctum.expiration')))->plainTextToken;
        $refreshToken = $user->createToken('refresh', ['refresh'], now()->addMinutes(config('sanctum.refresh_expiration')))->plainTextToken;

        return response()->json([
            'data' => new AuthResource(
                $user,
                $accessToken,
                $refreshToken,
                config('sanctum.expiration') * 60
            ),
        ], 201);
    }
}
```

**Step 7: Create User factory**

Create `backend/database/factories/UserFactory.php` (if not exists, update):
```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'display_name' => fake()->name(),
            'avatar_url' => null,
            'email_verified_at' => now(),
            'is_admin' => false,
            'is_banned' => false,
            'remember_token' => Str::random(10),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->preference()->create([
                'locale' => 'en',
                'notif_email' => true,
                'notif_push' => true,
            ]);
        });
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
        ]);
    }
}
```

**Step 8: Add routes**

Edit `backend/routes/api.php`:
```php
<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Auth routes (public)
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
    });
});
```

**Step 9: Run tests**

```bash
php artisan test tests/Feature/Auth/RegisterTest.php
```
Expected: PASS

**Step 10: Commit**

```bash
git add .
git commit -m "feat: add user registration endpoint"
```

---

## Task 3: Create Auth Controller - Login

**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/AuthController.php`
- Create: `backend/app/Http/Requests/Auth/LoginRequest.php`
- Modify: `backend/routes/api.php`
- Create: `backend/tests/Feature/Auth/LoginTest.php`

**Step 1: Write login test**

Create `backend/tests/Feature/Auth/LoginTest.php`:
```php
<?php

use App\Models\User;

test('user can login with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'user' => ['id', 'email'],
                'tokens' => ['access_token', 'refresh_token', 'expires_in'],
            ],
        ]);
});

test('login fails with invalid credentials', function () {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'wrongpassword',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
});

test('login fails for banned user', function () {
    User::factory()->create([
        'email' => 'banned@example.com',
        'password' => bcrypt('password123'),
        'is_banned' => true,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'banned@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(403)
        ->assertJsonPath('error.code', 'ACCOUNT_BANNED');
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/Auth/LoginTest.php
```
Expected: FAIL

**Step 3: Create LoginRequest**

Create `backend/app/Http/Requests/Auth/LoginRequest.php`:
```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
```

**Step 4: Add login method to AuthController**

Add to `backend/app/Http/Controllers/Api/V1/AuthController.php`:
```php
use App\Http\Requests\Auth\LoginRequest;
use App\Exceptions\ApiException;

public function login(LoginRequest $request): JsonResponse
{
    $credentials = $request->only('email', 'password');

    if (!auth()->attempt($credentials)) {
        throw new ApiException(
            'INVALID_CREDENTIALS',
            'Invalid email or password.',
            401
        );
    }

    $user = auth()->user();

    if ($user->is_banned) {
        auth()->logout();
        throw new ApiException(
            'ACCOUNT_BANNED',
            'Your account has been banned.',
            403
        );
    }

    // Create Sanctum tokens
    $accessToken = $user->createToken('access', ['access'], now()->addMinutes(config('sanctum.expiration')))->plainTextToken;
    $refreshToken = $user->createToken('refresh', ['refresh'], now()->addMinutes(config('sanctum.refresh_expiration')))->plainTextToken;

    return response()->json([
        'data' => new AuthResource(
            $user,
            $accessToken,
            $refreshToken,
            config('sanctum.expiration') * 60
        ),
    ]);
}
```

**Step 5: Add route**

Add to `backend/routes/api.php` auth group:
```php
Route::post('login', [AuthController::class, 'login']);
```

**Step 6: Run tests**

```bash
php artisan test tests/Feature/Auth/LoginTest.php
```
Expected: PASS

**Step 7: Commit**

```bash
git add .
git commit -m "feat: add login endpoint"
```

---

## Task 4: Create Auth Controller - Refresh & Logout

**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/AuthController.php`
- Modify: `backend/routes/api.php`
- Create: `backend/tests/Feature/Auth/TokenTest.php`

**Step 1: Write token tests**

Create `backend/tests/Feature/Auth/TokenTest.php`:
```php
<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

test('user can refresh token', function () {
    $user = User::factory()->create();
    $refreshToken = $user->createToken('refresh', ['refresh'], now()->addMinutes(config('sanctum.refresh_expiration')))->plainTextToken;

    $response = $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $refreshToken,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'access_token',
                'refresh_token',
                'expires_in',
            ],
        ]);
});

test('user can logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('access', ['access'])->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->postJson('/api/v1/auth/logout');

    $response->assertStatus(204);
});

test('logout fails without token', function () {
    $response = $this->postJson('/api/v1/auth/logout');

    $response->assertStatus(401);
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/Auth/TokenTest.php
```
Expected: FAIL

**Step 3: Add refresh and logout methods**

Add to `backend/app/Http/Controllers/Api/V1/AuthController.php`:
```php
use Illuminate\Http\Request;

public function refresh(Request $request): JsonResponse
{
    $request->validate([
        'refresh_token' => ['required', 'string'],
    ]);

    try {
        // Find the refresh token
        $token = PersonalAccessToken::findToken($request->refresh_token);

        if (!$token || !in_array('refresh', $token->abilities)) {
            throw new ApiException(
                'INVALID_TOKEN',
                'Invalid refresh token.',
                401
            );
        }

        // Check if token is expired
        if ($token->expires_at && $token->expires_at->isPast()) {
            $token->delete();
            throw new ApiException(
                'INVALID_TOKEN',
                'Refresh token has expired.',
                401
            );
        }

        $user = $token->tokenable;

        // Delete old tokens
        $token->delete();

        // Create new tokens
        $accessToken = $user->createToken('access', ['access'], now()->addMinutes(config('sanctum.expiration')))->plainTextToken;
        $refreshToken = $user->createToken('refresh', ['refresh'], now()->addMinutes(config('sanctum.refresh_expiration')))->plainTextToken;

        return response()->json([
            'data' => [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_in' => config('sanctum.expiration') * 60,
            ],
        ]);
    } catch (ApiException $e) {
        throw $e;
    } catch (\Exception $e) {
        throw new ApiException(
            'INVALID_TOKEN',
            'Invalid or expired refresh token.',
            401
        );
    }
}

public function logout(): JsonResponse
{
    // Delete current access token
    auth()->user()->currentAccessToken()->delete();

    return response()->json(null, 204);
}
```

**Step 4: Add routes**

Update `backend/routes/api.php`:
```php
Route::prefix('v1')->group(function () {
    // Auth routes (public)
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('refresh', [AuthController::class, 'refresh']);
    });

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
    });
});
```

**Step 5: Run tests**

```bash
php artisan test tests/Feature/Auth/TokenTest.php
```
Expected: PASS

**Step 6: Commit**

```bash
git add .
git commit -m "feat: add refresh and logout endpoints"
```

---

## Task 5: Create User Profile Endpoints

**Files:**
- Create: `backend/app/Http/Controllers/Api/V1/UserController.php`
- Create: `backend/app/Http/Requests/User/UpdateProfileRequest.php`
- Modify: `backend/routes/api.php`
- Create: `backend/tests/Feature/User/ProfileTest.php`

**Step 1: Write profile tests**

Create `backend/tests/Feature/User/ProfileTest.php`:
```php
<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

test('user can get their profile', function () {
    $user = User::factory()->create();
    $token = $user->createToken('access', ['access'])->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->getJson('/api/v1/me');

    $response->assertStatus(200)
        ->assertJsonPath('data.email', $user->email);
});

test('user can update their profile', function () {
    $user = User::factory()->create();
    $token = $user->createToken('access', ['access'])->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->putJson('/api/v1/me', [
            'display_name' => 'New Name',
            'locale' => 'fr',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.display_name', 'New Name')
        ->assertJsonPath('data.locale', 'fr');
});

test('user can update notification preferences', function () {
    $user = User::factory()->create();
    $user->load('preference');
    $token = $user->createToken('access', ['access'])->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->putJson('/api/v1/me', [
            'notif_email' => false,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.notif_email', false);
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/User/ProfileTest.php
```
Expected: FAIL

**Step 3: Create UpdateProfileRequest**

Create `backend/app/Http/Requests/User/UpdateProfileRequest.php`:
```php
<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'display_name' => ['nullable', 'string', 'max:100'],
            'locale' => ['nullable', 'string', 'in:en,fr'],
            'notif_email' => ['nullable', 'boolean'],
            'notif_push' => ['nullable', 'boolean'],
        ];
    }
}
```

**Step 4: Create UserController**

Create `backend/app/Http/Controllers/Api/V1/UserController.php`:
```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function show(): JsonResponse
    {
        $user = auth()->user();
        $user->load('preference');

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = auth()->user();

        // Update user fields
        $user->update($request->only(['display_name']));

        // Update preference fields
        $preferenceData = $request->only(['locale', 'notif_email', 'notif_push']);
        if (!empty($preferenceData)) {
            $user->preference()->updateOrCreate(
                ['user_id' => $user->id],
                $preferenceData
            );
        }

        return response()->json([
            'data' => new UserResource($user->fresh()->load('preference')),
        ]);
    }
}
```

**Step 5: Add routes**

Add to protected routes in `backend/routes/api.php`:
```php
use App\Http\Controllers\Api\V1\UserController;

// Inside middleware('auth:sanctum') group:
Route::get('me', [UserController::class, 'show']);
Route::put('me', [UserController::class, 'update']);
```

**Step 6: Run tests**

```bash
php artisan test tests/Feature/User/ProfileTest.php
```
Expected: PASS

**Step 7: Commit**

```bash
git add .
git commit -m "feat: add user profile endpoints"
```

---

## Task 6: Create Password Change Endpoint

**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/UserController.php`
- Create: `backend/app/Http/Requests/User/ChangePasswordRequest.php`
- Modify: `backend/routes/api.php`
- Create: `backend/tests/Feature/User/PasswordTest.php`

**Step 1: Write password change tests**

Create `backend/tests/Feature/User/PasswordTest.php`:
```php
<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

test('user can change password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('oldpassword'),
    ]);
    $token = $user->createToken('access', ['access'])->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->putJson('/api/v1/me/password', [
            'current_password' => 'oldpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

    $response->assertStatus(200);

    // Verify new password works
    expect(Hash::check('newpassword123', $user->fresh()->password))->toBeTrue();
});

test('password change fails with wrong current password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('oldpassword'),
    ]);
    $token = $user->createToken('access', ['access'])->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->putJson('/api/v1/me/password', [
            'current_password' => 'wrongpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

    $response->assertStatus(400)
        ->assertJsonPath('error.code', 'INVALID_PASSWORD');
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/User/PasswordTest.php
```
Expected: FAIL

**Step 3: Create ChangePasswordRequest**

Create `backend/app/Http/Requests/User/ChangePasswordRequest.php`:
```php
<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
```

**Step 4: Add changePassword method**

Add to `backend/app/Http/Controllers/Api/V1/UserController.php`:
```php
use App\Http\Requests\User\ChangePasswordRequest;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Hash;

public function changePassword(ChangePasswordRequest $request): JsonResponse
{
    $user = auth()->user();

    if (!Hash::check($request->current_password, $user->password)) {
        throw new ApiException(
            'INVALID_PASSWORD',
            'Current password is incorrect.',
            400
        );
    }

    $user->update([
        'password' => $request->password,
    ]);

    return response()->json([
        'data' => ['message' => 'Password updated successfully.'],
    ]);
}
```

**Step 5: Add route**

Add to protected routes:
```php
Route::put('me/password', [UserController::class, 'changePassword']);
```

**Step 6: Run tests**

```bash
php artisan test tests/Feature/User/PasswordTest.php
```
Expected: PASS

**Step 7: Commit**

```bash
git add .
git commit -m "feat: add password change endpoint"
```

---

## Task 7: Create Friendship Endpoints

**Files:**
- Create: `backend/app/Http/Controllers/Api/V1/FriendshipController.php`
- Create: `backend/app/Http/Resources/FriendshipResource.php`
- Create: `backend/app/Services/FriendshipService.php`
- Modify: `backend/routes/api.php`
- Create: `backend/tests/Feature/Friendship/FriendshipTest.php`

**Step 1: Write friendship tests**

Create `backend/tests/Feature/Friendship/FriendshipTest.php`:
```php
<?php

use App\Models\User;
use App\Models\Friendship;
use App\Enums\FriendshipStatus;
use Laravel\Sanctum\PersonalAccessToken;

test('user can send friend request', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $friend = User::factory()->create();
    $token = $user->createToken('access', ['access'])->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->postJson('/api/v1/friends', [
            'user_id' => $friend->id,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.status', 'pending');

    $this->assertDatabaseHas('friendships', [
        'requester_id' => $user->id,
        'addressee_id' => $friend->id,
        'status' => 'pending',
    ]);
});

test('user can accept friend request', function () {
    $requester = User::factory()->create();
    $addressee = User::factory()->create(['email_verified_at' => now()]);
    $friendship = Friendship::create([
        'requester_id' => $requester->id,
        'addressee_id' => $addressee->id,
        'status' => FriendshipStatus::PENDING,
    ]);
    $token = $addressee->createToken('access', ['access'])->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->putJson("/api/v1/friends/{$friendship->id}/accept");

    $response->assertStatus(200)
        ->assertJsonPath('data.status', 'accepted');
});

test('user can list friends', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $friend = User::factory()->create();
    Friendship::create([
        'requester_id' => $user->id,
        'addressee_id' => $friend->id,
        'status' => FriendshipStatus::ACCEPTED,
    ]);
    $token = $user->createToken('access', ['access'])->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->getJson('/api/v1/friends');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

test('unverified user cannot send friend request', function () {
    $user = User::factory()->unverified()->create();
    $friend = User::factory()->create();
    $token = $user->createToken('access', ['access'])->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->postJson('/api/v1/friends', [
            'user_id' => $friend->id,
        ]);

    $response->assertStatus(403)
        ->assertJsonPath('error.code', 'EMAIL_NOT_VERIFIED');
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/Friendship/FriendshipTest.php
```
Expected: FAIL

**Step 3: Create EnsureEmailVerified middleware**

Create `backend/app/Http/Middleware/EnsureEmailVerified.php`:
```php
<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;

class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !$request->user()->email_verified_at) {
            throw new ApiException(
                'EMAIL_NOT_VERIFIED',
                'Email verification required.',
                403
            );
        }

        return $next($request);
    }
}
```

Register in `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'verified' => \App\Http\Middleware\EnsureEmailVerified::class,
    ]);
})
```

**Step 4: Create FriendshipService**

Create `backend/app/Services/FriendshipService.php`:
```php
<?php

namespace App\Services;

use App\Enums\FriendshipStatus;
use App\Exceptions\ApiException;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class FriendshipService
{
    public function getFriends(User $user): Collection
    {
        $sent = $user->sentFriendRequests()
            ->where('status', FriendshipStatus::ACCEPTED)
            ->with('addressee')
            ->get();

        $received = $user->receivedFriendRequests()
            ->where('status', FriendshipStatus::ACCEPTED)
            ->with('requester')
            ->get();

        return $sent->merge($received);
    }

    public function getPendingRequests(User $user): Collection
    {
        return $user->receivedFriendRequests()
            ->where('status', FriendshipStatus::PENDING)
            ->with('requester')
            ->get();
    }

    public function sendRequest(User $requester, int $addresseeId): Friendship
    {
        if ($requester->id === $addresseeId) {
            throw new ApiException(
                'INVALID_REQUEST',
                'Cannot send friend request to yourself.',
                400
            );
        }

        $existing = Friendship::where(function ($q) use ($requester, $addresseeId) {
            $q->where('requester_id', $requester->id)
                ->where('addressee_id', $addresseeId);
        })->orWhere(function ($q) use ($requester, $addresseeId) {
            $q->where('requester_id', $addresseeId)
                ->where('addressee_id', $requester->id);
        })->first();

        if ($existing) {
            throw new ApiException(
                'FRIENDSHIP_EXISTS',
                'Friendship already exists.',
                400
            );
        }

        return Friendship::create([
            'requester_id' => $requester->id,
            'addressee_id' => $addresseeId,
            'status' => FriendshipStatus::PENDING,
        ]);
    }

    public function accept(Friendship $friendship, User $user): Friendship
    {
        if ($friendship->addressee_id !== $user->id) {
            throw new ApiException(
                'FORBIDDEN',
                'Cannot accept this request.',
                403
            );
        }

        $friendship->update(['status' => FriendshipStatus::ACCEPTED]);

        return $friendship->fresh();
    }

    public function decline(Friendship $friendship, User $user): void
    {
        if ($friendship->addressee_id !== $user->id) {
            throw new ApiException(
                'FORBIDDEN',
                'Cannot decline this request.',
                403
            );
        }

        $friendship->delete();
    }

    public function remove(Friendship $friendship, User $user): void
    {
        if ($friendship->requester_id !== $user->id && $friendship->addressee_id !== $user->id) {
            throw new ApiException(
                'FORBIDDEN',
                'Cannot remove this friendship.',
                403
            );
        }

        $friendship->delete();
    }
}
```

**Step 5: Create FriendshipResource**

Create `backend/app/Http/Resources/FriendshipResource.php`:
```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FriendshipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentUser = $request->user();
        $friend = $this->requester_id === $currentUser->id
            ? $this->addressee
            : $this->requester;

        return [
            'id' => $this->id,
            'friend' => $friend ? [
                'id' => $friend->id,
                'display_name' => $friend->display_name,
                'avatar_url' => $friend->avatar_url,
            ] : null,
            'status' => $this->status->value,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
```

**Step 6: Create FriendshipController**

Create `backend/app/Http/Controllers/Api/V1/FriendshipController.php`:
```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\FriendshipResource;
use App\Models\Friendship;
use App\Services\FriendshipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FriendshipController extends Controller
{
    public function __construct(
        private FriendshipService $friendshipService
    ) {}

    public function index(): JsonResponse
    {
        $friends = $this->friendshipService->getFriends(auth()->user());

        return response()->json([
            'data' => FriendshipResource::collection($friends),
        ]);
    }

    public function requests(): JsonResponse
    {
        $requests = $this->friendshipService->getPendingRequests(auth()->user());

        return response()->json([
            'data' => FriendshipResource::collection($requests),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $friendship = $this->friendshipService->sendRequest(
            auth()->user(),
            $request->user_id
        );

        return response()->json([
            'data' => new FriendshipResource($friendship),
        ], 201);
    }

    public function accept(Friendship $friendship): JsonResponse
    {
        $friendship = $this->friendshipService->accept($friendship, auth()->user());

        return response()->json([
            'data' => new FriendshipResource($friendship),
        ]);
    }

    public function decline(Friendship $friendship): JsonResponse
    {
        $this->friendshipService->decline($friendship, auth()->user());

        return response()->json(null, 204);
    }

    public function destroy(Friendship $friendship): JsonResponse
    {
        $this->friendshipService->remove($friendship, auth()->user());

        return response()->json(null, 204);
    }
}
```

**Step 7: Add routes**

Add to `backend/routes/api.php`:
```php
use App\Http\Controllers\Api\V1\FriendshipController;

// Inside auth:sanctum middleware, add verified middleware for friends:
Route::middleware('verified')->group(function () {
    Route::get('friends', [FriendshipController::class, 'index']);
    Route::get('friends/requests', [FriendshipController::class, 'requests']);
    Route::post('friends', [FriendshipController::class, 'store']);
    Route::put('friends/{friendship}/accept', [FriendshipController::class, 'accept']);
    Route::put('friends/{friendship}/decline', [FriendshipController::class, 'decline']);
    Route::delete('friends/{friendship}', [FriendshipController::class, 'destroy']);
});
```

**Step 8: Run tests**

```bash
php artisan test tests/Feature/Friendship/FriendshipTest.php
```
Expected: PASS

**Step 9: Commit**

```bash
git add .
git commit -m "feat: add friendship endpoints"
```

---

## Task 8: Create Email Verification

**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/AuthController.php`
- Create: `backend/app/Notifications/VerifyEmailNotification.php`
- Modify: `backend/routes/api.php`
- Create: `backend/tests/Feature/Auth/EmailVerificationTest.php`

**Step 1: Write verification tests**

Create `backend/tests/Feature/Auth/EmailVerificationTest.php`:
```php
<?php

use App\Models\User;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\PersonalAccessToken;

test('user can verify email', function () {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->getJson($verificationUrl);

    $response->assertStatus(200);
    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

test('user can resend verification email', function () {
    $user = User::factory()->unverified()->create();
    $token = $user->createToken('access', ['access'])->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->postJson('/api/v1/auth/resend-verification');

    $response->assertStatus(200);
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/Auth/EmailVerificationTest.php
```
Expected: FAIL

**Step 3: Create VerifyEmailNotification**

Create `backend/app/Notifications/VerifyEmailNotification.php`:
```php
<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Verify Email Address')
            ->line('Please click the button below to verify your email address.')
            ->action('Verify Email Address', $verificationUrl)
            ->line('If you did not create an account, no further action is required.');
    }

    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $notifiable->id, 'hash' => sha1($notifiable->email)]
        );
    }
}
```

**Step 4: Add verification methods to AuthController**

Add to `backend/app/Http/Controllers/Api/V1/AuthController.php`:
```php
use App\Notifications\VerifyEmailNotification;

public function verifyEmail(Request $request, int $id, string $hash): JsonResponse
{
    $user = User::findOrFail($id);

    if (!hash_equals($hash, sha1($user->email))) {
        throw new ApiException(
            'INVALID_VERIFICATION',
            'Invalid verification link.',
            400
        );
    }

    if ($user->email_verified_at) {
        return response()->json([
            'data' => ['message' => 'Email already verified.'],
        ]);
    }

    $user->update(['email_verified_at' => now()]);

    return response()->json([
        'data' => ['message' => 'Email verified successfully.'],
    ]);
}

public function resendVerification(): JsonResponse
{
    $user = auth()->user();

    if ($user->email_verified_at) {
        return response()->json([
            'data' => ['message' => 'Email already verified.'],
        ]);
    }

    $user->notify(new VerifyEmailNotification());

    return response()->json([
        'data' => ['message' => 'Verification email sent.'],
    ]);
}
```

**Step 5: Update register to send verification**

In AuthController::register, add after creating user:
```php
$user->notify(new VerifyEmailNotification());
```

**Step 6: Add routes**

Add to `backend/routes/api.php`:
```php
// Public verification route
Route::get('auth/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->name('verification.verify')
    ->middleware('signed');

// Protected route
Route::post('auth/resend-verification', [AuthController::class, 'resendVerification']);
```

**Step 7: Run tests**

```bash
php artisan test tests/Feature/Auth/EmailVerificationTest.php
```
Expected: PASS

**Step 8: Commit**

```bash
git add .
git commit -m "feat: add email verification"
```

---

## Task 9: Run Full Test Suite

**Step 1: Run all tests**

```bash
php artisan test
```
Expected: All PASS

**Step 2: Final commit**

```bash
git add .
git commit -m "chore: phase 2 complete - auth and user management"
```

---

## Phase 2 Completion Checklist

- [ ] API exception handling
- [ ] User registration with Sanctum
- [ ] User login with Sanctum
- [ ] Token refresh
- [ ] Logout
- [ ] User profile (get/update)
- [ ] Password change
- [ ] Friendship system (send/accept/decline/remove)
- [ ] Email verification
- [ ] EnsureEmailVerified middleware
- [ ] All feature tests passing
