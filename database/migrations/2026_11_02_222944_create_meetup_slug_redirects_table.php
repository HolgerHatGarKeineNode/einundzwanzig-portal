<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #167: a meetup's URL slug may now be changed, so every slug it gave up
 * has to keep answering.
 *
 * Until now the slug was frozen (`doNotGenerateSlugsOnUpdate()`), because it is
 * the route key of `/{country}/meetup/{meetup:slug}` and links to it are shared
 * in Telegram groups, on flyers and on other sites. A meetup created under a test
 * name or with a typo was stuck with that URL for good.
 *
 * Each row is one slug a meetup used to have. The landing page answers a request
 * for it with a permanent redirect to the meetup's current URL, so a link printed
 * on a flyer survives any number of renames.
 *
 * `slug` is unique across the table, not per meetup: a retired slug points at
 * exactly one meetup, otherwise the redirect would have to guess. The rule
 * {@see \App\Rules\UniqueMeetupSlug} keeps a meetup from taking a slug another
 * meetup has retired, for the same reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetup_slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meetup_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetup_slug_redirects');
    }
};
