<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Set when a Lightning credential is retired by an account merge:
            // the public_key stays (to keep matching so no orphan account is
            // created) but LNURL-auth login is refused and the user is pointed
            // at Nostr.
            $table->timestamp('lightning_retired_at')->nullable()->after('nostr');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('lightning_retired_at');
        });
    }
};
