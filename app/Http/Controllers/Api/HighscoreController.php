<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHighscoreRequest;
use App\Models\Highscore;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use swentel\nostr\Filter\Filter;
use swentel\nostr\Message\RequestMessage;
use swentel\nostr\Relay\Relay;
use swentel\nostr\Relay\RelaySet;
use swentel\nostr\Request\Request;
use swentel\nostr\Subscription\Subscription;

#[Group(name: 'Highscores', weight: 6)]
class HighscoreController extends Controller
{
    /**
     * Highscore-Bestenliste abrufen
     *
     * Öffentliche Bestenliste des Spiels, absteigend nach Satoshis (dann nach Zeitpunkt).
     * Die Antwort hat die Form { data: [ { npub, name, satoshis, blocks, datetime } ] }.
     */
    public function index(): JsonResponse
    {
        // npub1pt0kw36ue3w2g4haxq3wgm6a2fhtptmzsjlc2j2vphtcgle72qesgpjyc6
        $highscores = Highscore::query()
            ->orderByDesc('satoshis')
            ->orderBy('achieved_at')
            ->get()
            ->map(fn (Highscore $highscore) => [
                'npub' => $highscore->npub,
                'name' => $highscore->name,
                'satoshis' => $highscore->satoshis,
                'blocks' => $highscore->blocks,
                'datetime' => $highscore->achieved_at->toIso8601String(),
            ]);

        return response()->json([
            'data' => $highscores,
        ]);
    }

    /**
     * Highscore einreichen
     *
     * Reicht einen Highscore ein (idempotent pro npub und Zeitpunkt).
     * Zusätzlich auf 10 Anfragen pro Minute begrenzt.
     * Fehlt ein Name, versucht der Server, ihn über das Nostr-Profil zu ergänzen.
     * Antwortet mit HTTP 202.
     */
    #[Response(status: 429, description: 'Zu viele Anfragen (Limit: 10 pro Minute überschritten).')]
    public function store(StoreHighscoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $achievedAt = CarbonImmutable::parse($validated['datetime']);

        $highscore = Highscore::query()->firstOrNew([
            'npub' => $validated['npub'],
            'achieved_at' => $achievedAt,
        ]);

        $highscore->satoshis = (int) $validated['satoshis'];
        $highscore->blocks = (int) $validated['blocks'];

        if (array_key_exists('name', $validated)) {
            $highscore->name = $validated['name'];
        }

        $highscore->save();

        if (empty($highscore->name)) {
            $fetchedName = $this->fetchNostrName($highscore->npub);
            if ($fetchedName) {
                $highscore->name = $fetchedName;
                $highscore->save();
            }
        }

        Log::info('Highscore submission received', [
            'npub' => $highscore->npub,
            'name' => $highscore->name,
            'satoshis' => $highscore->satoshis,
            'blocks' => $highscore->blocks,
            'datetime' => $highscore->achieved_at->toIso8601String(),
        ]);

        return response()->json([
            'message' => 'Highscore received',
            'data' => [
                'npub' => $highscore->npub,
                'name' => $highscore->name,
                'satoshis' => $highscore->satoshis,
                'blocks' => $highscore->blocks,
                'datetime' => $highscore->achieved_at->toIso8601String(),
            ],
        ], 202);
    }

    protected function fetchNostrName(string $npub): ?string
    {
        $author = trim($npub);

        if (! str_starts_with($author, 'npub1')) {
            return null;
        }

        $subscription = new Subscription;
        $filter = new Filter;
        $filter->setAuthors([$author]);
        $filter->setKinds([0]);

        $requestMessage = new RequestMessage($subscription->getId(), [$filter]);
        $relaySet = new RelaySet;
        $relaySet->setRelays([
            new Relay('wss://nos.lol'),
        ]);

        $request = new Request($relaySet, $requestMessage);

        try {
            $response = $request->send();

            foreach ($response as $relayUrl => $relayResponses) {
                foreach ($relayResponses as $message) {
                    if (! isset($message->event)) {
                        continue;
                    }

                    try {
                        $profile = json_decode($message->event->content, true, 512, JSON_THROW_ON_ERROR);

                        if (isset($profile['name']) && is_string($profile['name']) && $profile['name'] !== '') {
                            Log::info('Fetched nostr profile name for highscore', [
                                'npub' => $author,
                                'relay' => $relayUrl,
                            ]);

                            return $profile['name'];
                        }
                    } catch (\JsonException $e) {
                        Log::warning('Failed to decode nostr profile for highscore', [
                            'npub' => $author,
                            'relay' => $relayUrl,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch nostr profile for highscore', [
                'npub' => $author,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
