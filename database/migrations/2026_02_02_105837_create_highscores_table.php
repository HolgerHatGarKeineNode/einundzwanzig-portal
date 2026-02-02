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
        Schema::create('highscores', function (Blueprint $table) {
            $table->id();
            $table->string('npub', 100);
            $table->string('name')->nullable();
            $table->unsignedBigInteger('satoshis');
            $table->unsignedInteger('blocks');
            $table->dateTime('achieved_at');
            $table->timestamps();

            $table->unique(['npub', 'achieved_at']);
            $table->index('satoshis');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('highscores');
    }
};
