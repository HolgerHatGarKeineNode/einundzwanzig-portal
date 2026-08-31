<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreWebhookSubscriptionRequest;
use App\Http\Requests\Api\UpdateWebhookSubscriptionRequest;
use App\Http\Resources\WebhookSubscriptionResource;
use App\Models\WebhookSubscription;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

#[Group(name: 'Webhooks', weight: 7)]
class WebhookSubscriptionController extends Controller
{
    /**
     * List my webhook subscriptions
     *
     * Own subscriptions only. The secret is included again only for a
     * subscription with `reveal_secret: true` — see store().
     */
    public function index(): JsonResponse
    {
        $subscriptions = WebhookSubscription::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        return WebhookSubscriptionResource::collection($subscriptions)->response();
    }

    /**
     * Create a webhook subscription
     *
     * Generates a secret (≥32 bytes of entropy) and returns it in this response.
     * With `reveal_secret: false` (the default), that is the only time it is
     * shown — rotating it then means deleting and recreating the subscription.
     * With `reveal_secret: true`, the owner can retrieve it again later via
     * index()/update(). Behind `einundzwanzig.webhooks.require_approval`
     * (default on) the subscription starts pending: an operator has to approve it
     * before any delivery is queued for it.
     */
    public function store(StoreWebhookSubscriptionRequest $request): JsonResponse
    {
        $secret = bin2hex(random_bytes(32));

        $subscription = WebhookSubscription::create([
            'user_id' => $request->user()->id,
            'url' => $request->string('url')->toString(),
            'secret' => $secret,
            'reveal_secret' => $request->boolean('reveal_secret'),
            'resources' => $request->input('resources'),
            'approved_at' => config('einundzwanzig.webhooks.require_approval', true) ? null : now(),
            // Explicit, not left to the column default: create() never re-reads a
            // DB-generated default back into the in-memory model, so the response
            // built from $subscription below would otherwise see `active` as null.
            'active' => true,
            'consecutive_failures' => 0,
        ]);

        return response()
            ->json([
                'data' => [
                    ...WebhookSubscriptionResource::make($subscription)->resolve(),
                    'secret' => $secret,
                ],
            ])
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update a webhook subscription
     *
     * Changes the URL and/or resource filter, or pauses/resumes delivery. Only
     * for the owner or a super admin. Resuming (`active: true`) a subscription
     * the system auto-disabled after repeated failures also clears its failure
     * count — the documented recovery path besides `webhook:retry`.
     */
    public function update(UpdateWebhookSubscriptionRequest $request, WebhookSubscription $webhookSubscription): WebhookSubscriptionResource
    {
        $webhookSubscription->fill($request->only(['url', 'resources', 'reveal_secret']));

        if ($request->has('active')) {
            $active = $request->boolean('active');

            if ($active && $webhookSubscription->disabled_at !== null) {
                $webhookSubscription->disabled_at = null;
                $webhookSubscription->consecutive_failures = 0;
            }

            $webhookSubscription->active = $active;
        }

        $webhookSubscription->save();

        return WebhookSubscriptionResource::make($webhookSubscription->fresh());
    }

    /**
     * Delete a webhook subscription
     *
     * Only for the owner or a super admin.
     */
    public function destroy(WebhookSubscription $webhookSubscription): Response
    {
        Gate::authorize('delete', $webhookSubscription);

        $webhookSubscription->delete();

        return response()->noContent();
    }
}
