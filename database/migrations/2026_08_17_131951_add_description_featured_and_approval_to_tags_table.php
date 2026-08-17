<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #4: the tag entity needs an optional description, a "featured" marker for the
 * picker's resting state, and an approval timestamp.
 *
 * `approved_at` carries the suggestion workflow: users without the tag-editor
 * permission may still create a tag, but it stays unapproved. An unapproved tag is
 * usable on its author's own event — otherwise the Czech tag requirement would be a
 * dead end for them — while staying out of everyone else's suggestions.
 *
 * Nullable rather than a boolean so the moment of approval is recorded, not just the
 * fact. Existing rows are backfilled as approved: they predate the workflow and were
 * never anyone's suggestion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->json('description')
                ->nullable()
                ->after('slug');

            $table->boolean('featured')
                ->default(false)
                ->index()
                ->after('type');

            $table->timestamp('approved_at')
                ->nullable()
                ->index()
                ->after('featured');

            /*
             * Who suggested this tag. Needed to keep an unapproved tag visible to its
             * own author while hiding it from everyone else's suggestions.
             *
             * Deliberately nullable and nullOnDelete, unlike the cascadeOnDelete used
             * elsewhere in this schema: a tag can be attached to events all over the
             * site, and none of them should disappear because the person who first
             * suggested it deleted their account.
             */
            $table->foreignId('created_by')
                ->nullable()
                ->after('approved_at')
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        // Everything that existed before the workflow counts as approved.
        DB::table('tags')->update(['approved_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropIndex(['featured']);
            $table->dropIndex(['approved_at']);
            $table->dropColumn(['description', 'featured', 'approved_at']);
        });
    }
};
