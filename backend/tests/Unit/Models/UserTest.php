<?php

declare(strict_types=1);

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

test('user casts password to hashed', function () {
    $user = new User();

    expect($user->getCasts())->toHaveKey('password');
});

test('user has preference relationship', function () {
    $user = new User();

    expect(method_exists($user, 'preference'))->toBeTrue();
});
