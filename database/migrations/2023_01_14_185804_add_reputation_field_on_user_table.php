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
        if (Schema::hasColumn('users', 'reputation')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('reputation')->default(0)->after('remember_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'reputation')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('reputation');
        });
    }
};
