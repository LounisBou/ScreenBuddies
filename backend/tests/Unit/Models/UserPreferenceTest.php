<?php

declare(strict_types=1);

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

test('user preference casts booleans correctly', function () {
    $preference = new UserPreference();
    $casts = $preference->getCasts();

    expect($casts)->toHaveKey('notif_email')
        ->toHaveKey('notif_push');
});
