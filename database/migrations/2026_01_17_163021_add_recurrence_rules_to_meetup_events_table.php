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
            $table->string('recurrence_type')->nullable()->after('start');
            $table->string('recurrence_day_of_week')->nullable()->after('recurrence_type');
            $table->string('recurrence_day_position')->nullable()->after('recurrence_day_of_week');
            $table->unsignedInteger('recurrence_interval')->default(1)->after('recurrence_day_position');
            $table->dateTime('recurrence_end_date')->nullable()->after('recurrence_interval');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meetup_events', function (Blueprint $table) {
            $table->dropColumn([
                'recurrence_type',
                'recurrence_day_of_week',
                'recurrence_day_position',
                'recurrence_interval',
                'recurrence_end_date',
            ]);
        });
    }
};
