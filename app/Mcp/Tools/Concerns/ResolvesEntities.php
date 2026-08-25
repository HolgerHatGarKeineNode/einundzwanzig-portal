<?php

namespace App\Mcp\Tools\Concerns;

use App\Models\Concerns\DescribesItselfForDisambiguation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * Löst Entitäten über ihren Namen (oder optional ihre ID) auf, damit Nutzer keine
 * internen IDs kennen müssen. Bei Mehrdeutigkeit oder fehlendem Treffer wird eine
 * Auswahlliste der passenden Einträge als Fehlertext zurückgegeben.
 */
trait ResolvesEntities
{
    /**
     * Löst einen vom authentifizierten Nutzer erstellten Datensatz auf.
     *
     * Die ID-Variante sucht über alle Datensätze (die Ownership-Prüfung übernimmt die
     * aufrufende Gate-Policy); die Namens-Variante ist auf die eigenen Datensätze
     * beschränkt und dient damit zugleich als Auswahlliste.
     *
     * @param  class-string<Model>  $modelClass
     */
    protected function resolveOwnedByName(Request $request, string $modelClass, string $label, string $nameParam, string $column = 'name'): Model|Response
    {
        $id = $request->get('id');

        if ($this->present($id)) {
            $found = $modelClass::query()->whereKey($id)->first();

            if ($found !== null) {
                return $found;
            }
        }

        $owned = $modelClass::query()->where('created_by', $request->user()?->getAuthIdentifier());
        $name = $request->get($nameParam);

        if ($this->present($name)) {
            $matches = $this->matchByName($owned, (string) $name, $column);

            if ($matches->count() === 1) {
                return $matches->first();
            }

            if ($matches->count() > 1) {
                return Response::error("Mehrere {$label} passen zu \"{$name}\": ".$matches->pluck($column)->join('; ').'. Bitte den genauen Namen angeben.');
            }
        }

        return $this->optionsError($owned, $label, $column);
    }

    /**
     * Löst einen Datensatz per ID oder Name STRIKT innerhalb des übergebenen Scopes auf
     * (der Scope ist zugleich die Autorisierung). Bei Mehrdeutigkeit oder fehlendem Treffer
     * wird eine Auswahlliste der Einträge des Scopes zurückgegeben.
     */
    protected function resolveInScope(Builder $scope, Request $request, string $label, string $nameParam, string $column = 'name'): Model|Response
    {
        $id = $request->get('id');

        if ($this->present($id)) {
            $byId = (clone $scope)->whereKey($id)->first();

            if ($byId !== null) {
                return $byId;
            }
        }

        $name = $request->get($nameParam);

        if ($this->present($name)) {
            $matches = $this->matchByName(clone $scope, (string) $name, $column);

            if ($matches->count() === 1) {
                return $matches->first();
            }

            if ($matches->count() > 1) {
                return Response::error("Mehrere {$label} passen zu \"{$name}\": ".$matches->pluck($column)->join('; ').'. Bitte den genauen Namen angeben.');
            }
        }

        return $this->optionsError(clone $scope, $label, $column);
    }

    /**
     * Löst einen Fremdschlüssel über den Namen auf und schreibt die ID in den Request,
     * damit die nachgelagerte Validierung sie sieht. Gibt null zurück, wenn nichts zu tun
     * ist (ID bereits gesetzt, oder optionaler FK ohne Namen), sonst eine Fehler-Response.
     */
    protected function mergeForeignKey(Request $request, string $nameParam, string $idKey, Builder $query, string $label, bool $required = true): ?Response
    {
        if ($this->present($request->get($idKey))) {
            return null;
        }

        if (! $this->present($request->get($nameParam))) {
            return $required ? Response::error("Bitte einen Namen für {$label} angeben.") : null;
        }

        $model = $this->resolveGlobalByName($query, $request->get($nameParam), $label);

        if ($model instanceof Response) {
            return $model;
        }

        $request->merge([$idKey => $model->id]);

        return null;
    }

    /**
     * Löst einen global sichtbaren Datensatz (z. B. Stadt, Land, Referent, Kurs, Ort)
     * über seinen Namen auf.
     */
    protected function resolveGlobalByName(Builder $query, ?string $name, string $label, string $column = 'name'): Model|Response
    {
        if (! $this->present($name)) {
            return Response::error("Bitte einen Namen für {$label} angeben.");
        }

        $matches = $this->matchByName($query, (string) $name, $column);

        if ($matches->count() === 1) {
            return $matches->first();
        }

        if ($matches->isEmpty()) {
            return Response::error("{$label} \"{$name}\" wurde nicht gefunden. Nutze das passende search-Tool, um den genauen Namen zu ermitteln.");
        }

        /*
         * Die Trefferliste muss UNTERSCHEIDBAR sein, nicht nur vollstaendig.
         *
         * Bis 2026-08-25 stand hier `pluck($column)` — bei acht Gemeinden namens
         * Neuenkirchen also achtmal dasselbe Wort. Die Meldung war formal korrekt und
         * praktisch wertlos: der Empfaenger konnte „praeziser angeben" nicht befolgen,
         * weil er nicht wusste, wodurch sich die Kandidaten unterscheiden. Seit die
         * Namens-Unique gefallen ist (Issue #33), ist das kein Randfall mehr.
         *
         * Models, die `DescribesItselfForDisambiguation` erfuellen, liefern ihre eigene
         * Unterscheidung — bei einer Stadt id, Region und Koordinaten. Alle anderen
         * bekommen id plus Name, was immer noch besser ist als der Name allein.
         */
        $kandidaten = $matches->take(15)->map(
            fn (Model $model): string => $model instanceof DescribesItselfForDisambiguation
                ? $model->disambiguationLabel()
                : '#'.$model->getKey().' '.$model->getAttribute($column)
        )->join('; ');

        return Response::error("Mehrere {$label} passen zu \"{$name}\": {$kandidaten}. Bitte praeziser angeben — die Kandidaten sind oben mit ihrer id genannt.");
    }

    /**
     * Case-insensitive Treffer in einer einzigen Abfrage: Teilstring-Suche, exakte
     * Treffer nach vorne sortiert (und dadurch nie vom Limit abgeschnitten). Existiert
     * mindestens ein exakter Treffer, gewinnt dieser; sonst zählen die Teiltreffer.
     * DB-portabel über LOWER().
     *
     * @return Collection<int, Model>
     */
    private function matchByName(Builder $query, string $name, string $column): Collection
    {
        $needle = mb_strtolower(trim($name));

        $matches = (clone $query)
            ->whereRaw('LOWER('.$column.') LIKE ?', ['%'.$needle.'%'])
            ->orderByRaw('CASE WHEN LOWER('.$column.') = ? THEN 0 ELSE 1 END', [$needle])
            ->limit(25)
            ->get();

        $exact = $matches->filter(
            fn (Model $model): bool => mb_strtolower((string) $model->getAttribute($column)) === $needle
        )->values();

        return $exact->isNotEmpty() ? $exact : $matches;
    }

    private function optionsError(Builder $owned, string $label, string $column): Response
    {
        $names = (clone $owned)->orderBy($column)->limit(50)->pluck($column);

        if ($names->isEmpty()) {
            return Response::error("Du hast noch keine {$label} angelegt.");
        }

        return Response::error("{$label} nicht gefunden. Deine {$label}: ".$names->join('; ').'.');
    }

    private function present(mixed $value): bool
    {
        return $value !== null && $value !== '';
    }
}
