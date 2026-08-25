<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;
use App\Policies\Concerns\ChecksCreatorOwnership;

class CoursePolicy
{
    use ChecksCreatorOwnership;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Course $course): bool
    {
        return $this->owns($user, $course);
    }

    /**
     * Jeder angemeldete Nutzer darf einen Kurs anlegen.
     *
     * Hier stand `(bool) $user->is_lecturer`. Das war kein Gate: beide Anlagepfade
     * (`LnurlAuthController::…`, `App\Support\NostrLogin`) setzen das Flag bei JEDER
     * Registrierung auf true, es gibt keine Oberflaeche, die es entzieht — die Bedingung
     * war also fuer jeden echten Nutzer wahr und hat nur so ausgesehen, als schuetze sie
     * etwas. Was schuetzt, ist `update()`: dort entscheidet die Ersteller-Zugehoerigkeit.
     *
     * Die Spalte selbst bleibt: `Api\UserController` liefert sie als Rollen-Abzeichen aus
     * und `MobileAuthTest` prueft ihre Unveraenderlichkeit.
     */
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Course $course): bool
    {
        return $this->owns($user, $course);
    }
}
