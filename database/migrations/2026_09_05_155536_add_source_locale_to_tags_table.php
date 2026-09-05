<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records the language a tag name was actually written in.
 *
 * The picker used to write the typed name into all nine tag locales, which made
 * `Tag::isDisplayNameSubstituted()` permanently false for every tag created that way:
 * the tag carried a name in the reader's language, so nothing looked substituted, and
 * the picker's "only available in :lang" line could never fire. Measured on production
 * on 2026-09-05, three of the ninety-four tags were in that state — among them the
 * Czech `Rodiny s dětmi`, stored as the German, English, Spanish, Hungarian, Latvian,
 * Dutch, Polish and Portuguese name too.
 *
 * WHAT NULL MEANS HERE, AND WHY EVERY EXISTING ROW KEEPS IT: null is "no source
 * language was recorded", nothing more. It is deliberately NOT backfilled.
 *
 *   - A seeded row (`created_by` is null) carries nine curated translations from
 *     database/seeders/data/tags.php. Its source language is German by construction,
 *     but no code reads the column for those rows, so writing it would only add a
 *     claim nobody checks.
 *   - A runtime row (`created_by` is set) carries nine copies of one string, and which
 *     of the nine languages that string is in exists nowhere in the database — the
 *     picker never recorded it. That is the defect itself. Any backfill would have to
 *     guess, and the guess is not silent: `displayLocale()` shows the recorded language
 *     to every reader ("only available in DE"), so a wrong guess turns today's missing
 *     warning into a confident false one.
 *
 * The eight copied names on those runtime rows are therefore left alone too. They are
 * repaired by a human through the per-locale name editor in tags.moderation, which is
 * the only place the missing fact — which language the word is in — is available.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->string('source_locale', 8)
                ->nullable()
                ->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropColumn('source_locale');
        });
    }
};
