<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetups', function (Blueprint $table) {
            $table->boolean('is_active')->default(false)->after('visible_on_map');
            $table->timestamp('last_event_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('meetups', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'last_event_at']);
        });
    }
};
