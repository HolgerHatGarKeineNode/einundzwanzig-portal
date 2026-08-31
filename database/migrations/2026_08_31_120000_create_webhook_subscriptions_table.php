<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #36: self-service outbound webhook subscriptions.
 *
 * `approved_at` and `active` are deliberately separate columns, not one status
 * field: `approved_at` is an OPERATOR gate (require_approval), set outside the
 * self-service API — nothing in the 4 owner-facing routes may set it, or the
 * abuse brake the config comment describes would be pointless. `active` is the
 * OWNER's own pause/resume switch via PATCH. A subscription only receives
 * deliveries with both conditions met and `disabled_at` still null.
 *
 * `disabled_at` is set by the system after `auto_disable_after` consecutive
 * failed deliveries; only the owner clears it again (PATCH), per the issue's
 * "leave re-enabling to the owner".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            // ≥32 bytes of entropy, encrypted at rest (App\Models\WebhookSubscription
            // casts this via Laravel's built-in `encrypted` cast, i.e. Crypt).
            // Long enough for the encrypted+base64 form of a 64-hex-char secret.
            $table->text('secret');
            // Restricted at validation time to ChangeRecorder::resourceNames() intersected
            // with einundzwanzig.webhooks.allowed_resources — meetup + meetup-event today.
            $table->json('resources');
            $table->timestamp('approved_at')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_subscriptions');
    }
};
