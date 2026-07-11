<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit trail for account merges (Lightning ⇄ Nostr identity consolidation).
     * Holds a full snapshot of the absorbed (loser) account plus the moved-row
     * counts, so a merge stays reversible and traceable by hand.
     */
    public function up(): void
    {
        Schema::create('merge_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survivor_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('loser_id')->nullable();
            $table->string('direction');
            $table->string('verified_identity');
            $table->json('loser_snapshot');
            $table->json('moved_counts');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merge_audits');
    }
};
