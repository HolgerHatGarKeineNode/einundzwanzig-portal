<?php

use App\Attributes\SeoDataAttribute;
use App\Http\Middleware\DomainMiddleware;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops `users.current_language` — a column nobody ever read.
 *
 * It was added in 2022 and had exactly three mentions in the whole repository: the
 * `$fillable` list, the factory that filled it with a coin flip, and its own creating
 * migration. Not one line of application code, view or API resource ever read it back.
 *
 * The language of a user is NOT this column. It lives in `users.lang_country` plus the
 * `lang_country` session key, both owned by `stefro/laravel-lang-country`
 * ({@see DomainMiddleware}, {@see SeoDataAttribute}).
 * That column is a different one and stays exactly where it is — the two were never
 * connected, which is precisely why this one could rot unnoticed.
 *
 * The values are not worth preserving: they were `de` by default or a factory guess, and
 * nothing consumed them. `down()` therefore restores the structure, not the content.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'current_language')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('current_language');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'current_language')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('current_language')->default('de');
        });
    }
};
