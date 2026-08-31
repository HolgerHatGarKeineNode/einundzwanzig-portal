<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #36: one row per queued outbound delivery attempt sequence.
 *
 * `payload` is a full COPY of the api_changes row's envelope at the moment the
 * delivery was queued — not a foreign-key lookup at send time. That copy is
 * what makes a deletion survive: App\Jobs\DeliverWebhookJob never touches the
 * source model (Meetup/MeetupEvent) or re-derives anything from it, only this
 * already-baked column. `api_change_id` is kept for traceability only and set
 * null (not cascaded) if the source row is later pruned, so the delivery
 * history and its payload copy outlive api_changes' 30-day retention.
 *
 * `attempts` counts HTTP attempts of ONE delivery (bumped by the job on every
 * run). `failed_at` marks a delivery that exhausted every retry — the set that
 * `webhook:retry` re-queues. `delivered_at` marks a 2xx response.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('webhook_subscriptions')->cascadeOnDelete();
            $table->foreignId('api_change_id')->nullable()->constrained('api_changes')->nullOnDelete();
            $table->json('payload');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedSmallInteger('last_response_code')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            // The operator recovery path: "failed deliveries of a subscription".
            $table->index(['subscription_id', 'failed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
