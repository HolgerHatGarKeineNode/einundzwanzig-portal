<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entfernt den EmailCampaign-Zweig. Modelle, Controller, Factories und die
 * Seeder-Aufrufe sind im selben Commit geloescht.
 *
 * Beide Tabellen waren zum Zeitpunkt der Loeschung in Produktion leer
 * (verifiziert am 2026-08-05 ueber die MCP-Super-Admin-Tools, jeweils count 0).
 *
 * Die drei alten Migrationen von 2023_11_11 bleiben absichtlich stehen: die
 * migrations-Tabelle in Produktion verweist auf sie, und ohne die Dateien
 * brechen migrate:rollback und migrate:reset ueber diese Batches hinweg ab.
 *
 * down() stellt den Stand her, der unmittelbar vor diesem Drop galt -- also
 * inklusive subject_prompt und text_prompt aus
 * 2023_11_11_101728_add_fields_to_email_campaigns_table. Deren eigenes down()
 * ist leer; ein Rollback bis dorthin allein wuerde die Spalten also nicht
 * zurueckbringen. Diese Migration ist damit die einzige verlaessliche
 * Rueckfahrkarte fuer den vollstaendigen Stand.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('email_texts');
        Schema::dropIfExists('email_campaigns');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('email_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('list_file_name');
            $table->timestamps();
            $table->text('subject_prompt')->nullable();
            $table->text('text_prompt')->nullable();
        });

        Schema::create('email_texts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_campaign_id')->constrained();
            $table->string('sender_md5');
            $table->string('subject');
            $table->longText('text');
            $table->timestamps();
        });
    }
};
