<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The operator's other half of the approval gate (Issue #36 follow-up):
 * `approved_at` alone cannot tell "not yet looked at" apart from "looked at
 * and declined" — both read as null, so a rejected subscription would sit in
 * the pending list forever. `rejected_at` is a second, independent operator
 * timestamp for that, not a status enum replacing `approved_at`: the two
 * migration's-worth of columns stay orthogonal on purpose, same reasoning as
 * `approved_at` vs. `active` in the original table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_subscriptions', function (Blueprint $table) {
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_subscriptions', function (Blueprint $table) {
            $table->dropColumn('rejected_at');
        });
    }
};
