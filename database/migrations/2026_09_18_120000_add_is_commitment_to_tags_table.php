<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #149: marks a tag as a promise towards attendees rather than a label.
 *
 * "Beginners" and "Families" read as promotion when an organiser browses the
 * list, but to a visitor they are commitments: somebody will have time for
 * basic questions; children are genuinely welcome. The badge that this flag
 * drives is the counterweight — it tells the organiser what choosing the tag
 * signs them up for, before the visitor arrives expecting it.
 *
 * A database column rather than a config slug list because the vocabulary is
 * multilingual and slugs are per-locale: a slug list would have to pick one
 * language's slug to match on and would silently stop matching the moment a
 * name is edited. The flag travels with the row through every locale.
 *
 * Not editable in tags.moderation on purpose (plan, open questions): the flag
 * says something about the vocabulary's conventions, not about one tag's
 * content, and it is seeded from database/seeders/data/tags.php like `featured`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->boolean('is_commitment')->default(false)->after('featured');
        });
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropColumn('is_commitment');
        });
    }
};
