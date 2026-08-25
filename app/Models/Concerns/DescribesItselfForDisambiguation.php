<?php

namespace App\Models\Concerns;

/**
 * Ein Model, das sich selbst beschreiben kann, wenn sein Name nicht eindeutig ist.
 *
 * Gebraucht an genau einer Stelle mit genau einem Grund: `resolveGlobalByName()` meldet
 * bei mehreren Treffern „Mehrere Staedte passen zu X" und listet die Namen. Bei acht
 * Gemeinden namens Neuenkirchen steht dort achtmal dasselbe Wort — eine Fehlermeldung,
 * die den Empfaenger genau dort laesst, wo er war.
 *
 * Wer dieses Interface erfuellt, liefert stattdessen eine Zeile, an der man die
 * Datensaetze auseinanderhalten kann. Was das im Einzelnen ist, weiss nur das Model:
 * bei einer Stadt sind es Koordinaten, bei einem Kurs waere es etwas anderes.
 */
interface DescribesItselfForDisambiguation
{
    /**
     * Eine kurze Zeile, die diesen Datensatz von einem gleichnamigen unterscheidet.
     */
    public function disambiguationLabel(): string;
}
