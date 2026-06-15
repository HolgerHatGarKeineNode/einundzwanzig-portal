<?php

namespace App\Enums;

/**
 * RSVP-Status eines Nutzers für einen Meetup-Termin. `None` bildet den Zustand
 * ab, dass der Nutzer in keiner der beiden Teilnehmer-Listen steht (= abgesagt
 * bzw. nie zugesagt) und dient zugleich als Eingabewert zum Austragen.
 */
enum RsvpStatus: string
{
    case Attending = 'attending';
    case Maybe = 'maybe';
    case None = 'none';
}
