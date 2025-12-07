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
        Schema::table('self_hosted_services', function (Blueprint $table) {
            $table->string('ip', 45)->nullable()->after('url_pkdns');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('self_hosted_services', function (Blueprint $table) {
            $table->dropColumn('ip');
        });
    }
};
