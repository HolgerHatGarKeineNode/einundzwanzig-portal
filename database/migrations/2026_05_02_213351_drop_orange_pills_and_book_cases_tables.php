<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('media')
            ->whereIn('model_type', [
                'App\\Models\\OrangePill',
                'App\\Models\\BookCase',
            ])
            ->delete();

        Schema::dropIfExists('orange_pills');
        Schema::dropIfExists('book_cases');
    }

    public function down(): void
    {
        throw new RuntimeException(
            'OrangePill and BookCase features were removed permanently; this migration is not reversible.'
        );
    }
};
