<?php

namespace App\Policies;

use App\Models\Course;
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

    /**
     * Einen Termin an DIESEN Kurs haengen darf, wem der Kurs gehoert — oder ein
     * Super-Admin.
     *
     * Warum es diese Ability ueberhaupt gibt: `create()` muss schrankenlos bleiben (die
     * REST-API ruft sie ohne Kurs auf), aber das Terminformular kennt seinen Kurs. Ohne
     * eine kurs-bezogene Frage waere die Pruefung im Formular nach dem Abbau von
     * `is_lecturer` auf `true` zusammengefallen — und damit genau das Loch wieder offen,
     * das `courses/create-edit-events.blade.php` erst kuerzlich geschlossen hat: die
     * Route liegt nur hinter `auth`, jeder Angemeldete haette wieder Termine in fremde
     * Kurse legen koennen.
     *
     * Die Zugehoerigkeit wird ueber `ChecksCreatorOwnership` beantwortet, nicht ueber
     * `CoursePolicy` — diese Policy bleibt eigenstaendig.
     */
    public function createForCourse(User $user, Course $course): bool
    {
        return $this->owns($user, $course);
    }

    public function update(User $user, CourseEvent $courseEvent): bool
    {
        return $this->owns($user, $courseEvent);
    }
}
