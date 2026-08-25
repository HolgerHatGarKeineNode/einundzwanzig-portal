<?php

use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * `users.current_language` is gone; `users.lang_country` is not.
 *
 * The two were never the same thing, but they look alike enough that a later reader could
 * "restore" the wrong one. This test nails both halves down at once: the dead column is
 * absent, and the live one — the column `stefro/laravel-lang-country` owns and the whole
 * localisation actually reads — is still there and still writable.
 */
it('has dropped the dead current_language column', function () {
    expect(Schema::hasColumn('users', 'current_language'))->toBeFalse();
});

it('keeps lang_country, which is the column the language really lives in', function () {
    expect(Schema::hasColumn('users', 'lang_country'))->toBeTrue();

    $user = User::factory()->create();
    $user->forceFill(['lang_country' => 'nl-NL'])->save();

    expect($user->fresh()->lang_country)->toBe('nl-NL');
});

it('creates a user through the factory without the dropped column', function () {
    $user = User::factory()->create();

    expect($user->exists)->toBeTrue()
        ->and(array_key_exists('current_language', $user->getAttributes()))->toBeFalse();
});

/**
 * Rolling back must land in a working database. It restores the STRUCTURE, not the values —
 * there were none worth keeping.
 */
it('restores the column structure on rollback and removes it again on re-run', function () {
    $file = 'migrations/2026_08_25_210000_drop_current_language_from_users_table.php';
    $migration = require database_path($file);

    $migration->down();
    expect(Schema::hasColumn('users', 'current_language'))->toBeTrue();

    $migration->up();
    expect(Schema::hasColumn('users', 'current_language'))->toBeFalse();
});
