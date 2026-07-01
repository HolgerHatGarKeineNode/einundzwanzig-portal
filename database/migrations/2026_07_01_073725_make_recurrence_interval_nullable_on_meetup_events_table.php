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
        Schema::table('meetup_events', function (Blueprint $table) {
            $table->unsignedInteger('recurrence_interval')->nullable()->default(1)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meetup_events', function (Blueprint $table) {
            $table->unsignedInteger('recurrence_interval')->default(1)->change();
        });
    }
};
