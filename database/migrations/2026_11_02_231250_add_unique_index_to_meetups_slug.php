<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #167: the slug is now typed by people, so uniqueness needs a floor
 * that does not depend on the form.
 *
 * {@see \App\Rules\UniqueMeetupSlug} gives the readable message; this index is
 * what holds when two saves race past the rule, or when a slug arrives by a
 * path that never runs the rule (restore point, tinker).
 *
 * Measured on production before writing this, 2026-11-03, read-only probe on
 * the portal server: 325 meetups, 0 duplicate slugs (also 0 case-insensitive),
 * 0 NULL slugs, 0 slugs outside ^[a-z0-9]+(-[a-z0-9]+)*$, and no index on the
 * column at all. The index therefore builds without touching a single row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetups', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('meetups', function (Blueprint $table) {
            $table->dropUnique(['slug']);
        });
    }
};
