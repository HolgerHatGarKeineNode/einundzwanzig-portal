<?php

namespace App\Policies;

use App\Models\SelfHostedService;
use App\Models\User;
use App\Policies\Concerns\ChecksCreatorOwnership;

/**
 * Aufgeloest ueber die Namenskonvention (App\Models\SelfHostedService →
 * App\Policies\SelfHostedServicePolicy). Kein Eintrag in einem Provider noetig, und es
 * gibt hier auch keinen: `app/Providers` kennt kein `Gate::policy`.
 */
class SelfHostedServicePolicy
{
    use ChecksCreatorOwnership;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SelfHostedService $service): bool
    {
        return $this->owns($user, $service);
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Aendern darf der Ersteller — oder ein Super-Admin.
     *
     * Bewusste Verschaerfung gegenueber der bisherigen Inline-Bedingung im Formular:
     * dort galt `created_by === null` als Freigabe fuer JEDEN Angemeldeten. Ein anonym
     * eingestellter Dienst gehoerte damit faktisch allen; wer ihn umbenannte, seine URL
     * austauschte oder ihn auf eine fremde IP zeigen liess, brauchte nur ein Konto.
     *
     * `ChecksCreatorOwnership::owns()` beantwortet `created_by = null` mit `false`
     * (`(int) null === 0` trifft keine Nutzer-Id) — anonyme Dienste sind ab hier allein
     * fuer Super-Admins editierbar. Das ist die getroffene Entscheidung, kein Nebeneffekt:
     * lieber ein Datensatz, der einen Zustaendigen braucht, als einer, den jeder
     * uebernehmen kann.
     */
    public function update(User $user, SelfHostedService $service): bool
    {
        return $this->owns($user, $service);
    }

    /**
     * Loeschen darf, wer auch aendern darf — aber als EIGENE Frage.
     *
     * Der Loeschknopf auf der Landingpage lief bisher gegen
     * `auth()->id() === $service->created_by`, waehrend die Policy den Super-Admin
     * kennt. Den Knopf auf `update` umzuhaengen haette funktioniert und waere trotzdem
     * falsch gewesen: die Blade-Zeile haette dann eine Ability gefragt, die sie nicht
     * ausuebt. Wird das Loeschen spaeter enger gefasst als das Aendern, ist hier der
     * Ort dafuer — und nicht eine Fundstelle, die niemand sucht.
     */
    public function delete(User $user, SelfHostedService $service): bool
    {
        return $this->owns($user, $service);
    }
}
