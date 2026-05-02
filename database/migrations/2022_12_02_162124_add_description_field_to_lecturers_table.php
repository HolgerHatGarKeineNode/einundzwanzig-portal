<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $supportsFulltext = in_array(DB::getDriverName(), ['mysql', 'pgsql'], true);

        Schema::table('lecturers', function (Blueprint $table) use ($supportsFulltext) {
            $column = $table->longText('description')->nullable();

            if ($supportsFulltext) {
                $column->fulltext();
            }
        });
    }

    public function down(): void
    {
        Schema::table('lecturers', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
