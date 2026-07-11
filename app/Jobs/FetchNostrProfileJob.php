<?php

namespace App\Jobs;

use App\Models\User;
use App\Support\NostrProfilePhoto;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use swentel\nostr\Filter\Filter;
use swentel\nostr\Key\Key;
use swentel\nostr\Message\RequestMessage;
use swentel\nostr\Relay\Relay;
use swentel\nostr\Relay\RelaySet;
use swentel\nostr\Request\Request;
use swentel\nostr\Subscription\Subscription;

class FetchNostrProfileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?User $user = null,
    ) {}

    public function handle(): void
    {
        // Determine which users to process
        if ($this->user) {
            if (! $this->user->nostr) {
                \Log::info('No nostr profile for user', ['user_id' => $this->user->id]);

                return;
            }
            $users = collect([$this->user]);
        } else {
            $users = User::query()->whereNotNull('nostr')->get();
            \Log::info('Fetching nostr profiles for multiple users', ['count' => $users->count()]);
        }

        // Filter valid npub authors
        $authors = $users
            ->pluck('nostr')
            ->map(fn ($nostr) => trim($nostr))
            ->filter(fn ($nostr) => str_starts_with($nostr, 'npub1'))
            ->unique()
            ->values()
            ->toArray();

        if (empty($authors)) {
            \Log::warning('No valid nostr authors found');

            return;
        }

        // Setup filter for kind 0 (profile metadata)
        $subscription = new Subscription;
        $filter = new Filter;
        $filter->setAuthors($authors);
        $filter->setKinds([0]);
        $requestMessage = new RequestMessage($subscription->getId(), [$filter]);

        // Setup relay set
        $relays = [
            new Relay('wss://nos.lol'),
        ];
        $relaySet = new RelaySet;
        $relaySet->setRelays($relays);

        // Send request
        $request = new Request($relaySet, $requestMessage);

        try {
            \Log::info('Fetching from relays', ['relay_count' => count($relays), 'author_count' => count($authors)]);
            $response = $request->send();

            $updated = 0;
            $totalMessages = 0;

            foreach ($response as $relayUrl => $relayResponses) {
                $messageCount = count($relayResponses);
                $totalMessages += $messageCount;

                \Log::info('Received messages from relay', ['url' => $relayUrl, 'count' => $messageCount]);

                foreach ($relayResponses as $message) {
                    if (! isset($message->event)) {
                        continue;
                    }

                    try {
                        $profile = json_decode($message->event->content, true, 512, JSON_THROW_ON_ERROR);

                        if (isset($profile['picture'])) {
                            $npub = (new Key)->convertPublicKeyToBech32($message->event->pubkey);
                            $user = User::query()->where('nostr', $npub)->first();

                            // Guard first: a relay may return an unrequested
                            // author, so the lookup can miss. Touching a null user
                            // here throws \Error, which the catch blocks below do
                            // not catch and would abort the whole batch.
                            if ($user) {
                                if (isset($profile['name'])) {
                                    $user->name = $profile['name'];
                                    $user->save();
                                }

                                $this->downloadAndSaveProfilePhoto($user, $profile['picture']);
                                $updated++;
                            }
                        }
                    } catch (\JsonException $e) {
                        \Log::error('Failed to decode profile', [
                            'error' => $e->getMessage(),
                            'relay' => $relayUrl,
                            'pubkey' => $message->event->pubkey,
                        ]);
                    } catch (\Exception $e) {
                        \Log::error('Failed to download profile photo', [
                            'error' => $e->getMessage(),
                            'relay' => $relayUrl,
                            'pubkey' => $message->event->pubkey,
                        ]);
                    }
                }
            }

            \Log::info('Finished updating nostr profiles', [
                'total_messages' => $totalMessages,
                'updated_count' => $updated,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to fetch from relays', ['error' => $e->getMessage()]);
        }
    }

    private function downloadAndSaveProfilePhoto(User $user, string $photoUrl): void
    {
        $path = NostrProfilePhoto::store($photoUrl);

        if ($path === null) {
            return;
        }

        $previous = $user->profile_photo_path;

        $user->forceFill(['profile_photo_path' => $path])->save();

        if ($previous) {
            Storage::disk('public')->delete($previous);
        }

        \Log::info('Profile photo updated from Nostr', ['user_id' => $user->id]);
    }
}
