<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner-controlled exposure of a subscription's own secret after creation
 * (Issue #36 follow-up): off by default keeps today's one-time-reveal
 * behaviour unchanged; on, the owner's own secret is included in their
 * index/update responses instead of only at store() time. Storage stays
 * `Crypt`-encrypted either way — this only gates API exposure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_subscriptions', function (Blueprint $table) {
            $table->boolean('reveal_secret')->default(false)->after('secret');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_subscriptions', function (Blueprint $table) {
            $table->dropColumn('reveal_secret');
        });
    }
};
