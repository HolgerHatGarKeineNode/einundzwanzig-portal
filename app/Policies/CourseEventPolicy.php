<?php

namespace App\Policies;

use App\Models\CourseEvent;
use App\Models\User;
use App\Policies\Concerns\ChecksCreatorOwnership;

class CourseEventPolicy
{
    use ChecksCreatorOwnership;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CourseEvent $courseEvent): bool
    {
        return $this->owns($user, $courseEvent);
    }

    /**
     * Jeder angemeldete Nutzer darf einen Kurstermin anlegen — ohne Bezug auf einen
     * bestimmten Kurs.
     *
     * Hier stand `(bool) $user->is_lecturer`, dieselbe Schein-Bedingung wie in
     * `CoursePolicy::create()`: das Flag wird bei jeder Registrierung gesetzt und nie
     * entzogen. Diese Ability bleibt bewusst schrankenlos, weil die REST-API sie ohne
     * Kurs-Kontext aufruft (`Api\StoreCourseEventRequest`) und dort seit jeher gilt:
     * einen Termin darf jeder anlegen, aendern nur sein Ersteller.
     *
     * Wer einen Termin an EINEN bestimmten Kurs haengen darf, beantwortet
     * `createForCourse()`.
     */
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, CourseEvent $courseEvent): bool
    {
        return $this->owns($user, $courseEvent);
    }
}
