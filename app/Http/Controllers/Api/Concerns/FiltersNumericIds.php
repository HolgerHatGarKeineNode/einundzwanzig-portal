<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\Request;

trait FiltersNumericIds
{
    /**
     * Reduces a query parameter to its numeric values as a list of integers.
     *
     * Protects type-sensitive whereIn('id', ...) clauses from non-numeric input.
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
