<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHighscoreRequest;
use App\Models\Highscore;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class HighscoreController extends Controller
{
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
}
