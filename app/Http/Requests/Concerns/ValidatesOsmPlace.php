<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

/**
 * The validation rules for an OpenStreetMap place.
 *
 * All three event types carry the same six columns, so they carry the same rules. Written
 * once because a place that validates differently depending on which endpoint it arrived
 * through is a bug waiting to happen — and because the pairing rule below is easy to forget.
 */
trait ValidatesOsmPlace
{
    /**
     * @param  bool  $partial  true for PATCH, where an absent field means "leave it alone"
     * @return array<string, array<int, mixed>>
     */
    protected function osmPlaceRules(bool $partial = false): array
    {
        $prefix = $partial ? ['sometimes'] : [];

        return [
            /*
             * osm_type and osm_id only identify a place together — neither is unique on its
             * own — so half a pair is rejected rather than silently stored.
             *
             * These two deliberately skip `sometimes` even on PATCH. `sometimes` drops the
             * whole rule set when the field is absent, taking `required_with` with it — and
             * absent is exactly the case the pairing rule exists to catch. `nullable` alone
             * already lets a request that mentions neither field pass untouched.
             */
            'osm_type' => ['nullable', 'required_with:osm_id', Rule::in(['node', 'way', 'relation'])],
            'osm_id' => ['nullable', 'required_with:osm_type', 'integer', 'min:1'],
            'osm_name' => [...$prefix, 'nullable', 'string', 'max:255'],
            'osm_address' => [...$prefix, 'nullable', 'string', 'max:255'],
            'osm_lat' => [...$prefix, 'nullable', 'numeric', 'between:-90,90'],
            'osm_lon' => [...$prefix, 'nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
