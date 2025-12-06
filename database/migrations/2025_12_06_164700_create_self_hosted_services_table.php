<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('self_hosted_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('intro')->nullable();
            $table->string('url_clearnet')->nullable();
            $table->string('url_onion')->nullable();
            $table->string('url_i2p')->nullable();
            $table->string('url_pkdns')->nullable();
            $table->string('type');
            $table->text('contact')->nullable();
            $table->timestamps();

            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('self_hosted_services');
    }
};
