<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Verwaltungsebene 1 (ISO 3166-2): US-Bundesstaaten, Bundeslaender, Provinzen.
     *
     * `code` ist das Suffix hinter dem Bindestrich in Kleinschreibung ("in" aus "US-IN") —
     * dieselbe Form, die als URL-Segment unter dem Land steht (/us/in/meetups). Der volle
     * ISO-Code ergibt sich aus `countries.code` plus `code` und wird deshalb nicht doppelt
     * gespeichert.
     */
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')
                ->constrained()
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('code', 10);
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['country_id', 'code']);
            $table->unique(['country_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
