<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\Request;

trait FiltersNumericIds
{
    /**
     * Reduziert einen Query-Parameter auf seine numerischen Werte als Integer-Liste.
     *
     * Schuetzt typsensitive whereIn('id', ...)-Klauseln vor nicht-numerischer Eingabe.
     *
     * @return array<int, int>
     */
    protected function numericIds(Request $request, string $key = 'selected'): array
    {
        return $request->collect($key)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
