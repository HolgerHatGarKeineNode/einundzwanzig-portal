<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE library_items
                ALTER COLUMN tweet DROP DEFAULT,
                ALTER COLUMN tweet TYPE BOOLEAN USING tweet::BOOLEAN,
                ALTER COLUMN tweet SET DEFAULT FALSE;');

            return;
        }

        Schema::table('library_items', function (Blueprint $table) {
            $table->boolean('tweet')->default(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->text('tweet')->nullable()->change();
        });
    }
};
