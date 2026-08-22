<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Eine OpenStreetMap-Referenz auf einem Stammdaten-Model (Stadt, Land).
 *
 * Die Spalten kommen aus der Migration; hier stehen nur die abgeleiteten Werte. Sie
 * werden bewusst NICHT gespeichert: eine zweite Kopie derselben Information kann der
 * ersten widersprechen, und genau das ist der Grund, warum der Wunsch aus Issue #11/#12
 * nach einer `osm_url`-Spalte hier als Accessor beantwortet wird statt als Feld.
 */
trait HasOsmReference
{
    /**
     * Der dauerhafte Link zum OSM-Objekt, oder null solange das Paar fehlt.
     *
     * `osm_type` und `osm_id` identifizieren ein Objekt nur gemeinsam — halb gesetzt
     * ergibt keine gueltige URL, sondern eine, die auf eine fremde Sache zeigt.
     */
    protected function osmUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->osm_type && $this->osm_id
            ? "https://www.openstreetmap.org/{$this->osm_type}/{$this->osm_id}"
            : null);
    }

    /**
     * Der Link zum Wikidata-Objekt, z. B. https://www.wikidata.org/wiki/Q64.
     */
    protected function wikidataUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->wikidata
            ? "https://www.wikidata.org/wiki/{$this->wikidata}"
            : null);
    }

    /**
     * Der Link zum Wikipedia-Artikel.
     *
     * OSM speichert den Verweis als "sprache:Titel" — "de:Berlin", "en:London",
     * "ja:日本". Der Praefix folgt der OSM-Community, nicht unserer Oberflaeche, und
     * bestimmt die Domain. Ohne Praefix ist der Wert unbrauchbar; dann lieber null als
     * ein Link ins Leere.
     */
    protected function wikipediaUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            $value = (string) ($this->wikipedia ?? '');

            if (! str_contains($value, ':')) {
                return null;
            }

            [$language, $title] = explode(':', $value, 2);
            $language = trim($language);
            $title = trim($title);

            if ($language === '' || $title === '') {
                return null;
            }

            return sprintf(
                'https://%s.wikipedia.org/wiki/%s',
                rawurlencode($language),
                rawurlencode(str_replace(' ', '_', $title)),
            );
        });
    }

    /**
     * Datensaetze ohne OSM-Referenz — die Arbeitsmenge jedes Anreicherungslaufs.
     */
    public function scopeWithoutOsmReference(Builder $query): Builder
    {
        return $query->whereNull('osm_id');
    }
}
