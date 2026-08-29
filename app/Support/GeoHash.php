<?php

namespace App\Support;

/**
 * Standard-Geohash-Encoder (Gustavo Niemeyer, 2008) fuer den `g`-Tag aus NIP-52.
 *
 * Kein Composer-Paket dafuer noetig — der Algorithmus ist ~30 Zeilen und die
 * einzige Nostr-Abhaengigkeit dieses Features (swentel/nostr-php) ist bereits im
 * Projekt. Ein zusaetzliches Paket allein fuer diese eine Funktion waere teurer
 * als sie selbst zu schreiben.
 */
class GeoHash
{
    private const BASE32 = '0123456789bcdefghjkmnpqrstuvwxyz';

    /**
     * Kodiert Koordinaten in einen Geohash-String.
     *
     * `$precision` ist die Zeichenlaenge, nicht die Anzahl Bits — 5 Zeichen
     * (der von der Issue-Checkliste gewuenschte Wert) entsprechen rund 5 km
     * Kantenlaenge der Bounding-Box, passend fuer Stadt-Ebene.
     */
    public static function encode(float $latitude, float $longitude, int $precision = 5): string
    {
        $latRange = [-90.0, 90.0];
        $lonRange = [-180.0, 180.0];

        $geohash = '';
        $isEvenBit = true;
        $bit = 0;
        $charBits = 0;

        while (strlen($geohash) < $precision) {
            if ($isEvenBit) {
                $mid = ($lonRange[0] + $lonRange[1]) / 2;
                if ($longitude >= $mid) {
                    $charBits = ($charBits << 1) | 1;
                    $lonRange[0] = $mid;
                } else {
                    $charBits <<= 1;
                    $lonRange[1] = $mid;
                }
            } else {
                $mid = ($latRange[0] + $latRange[1]) / 2;
                if ($latitude >= $mid) {
                    $charBits = ($charBits << 1) | 1;
                    $latRange[0] = $mid;
                } else {
                    $charBits <<= 1;
                    $latRange[1] = $mid;
                }
            }

            $isEvenBit = ! $isEvenBit;
            $bit++;

            if ($bit === 5) {
                $geohash .= self::BASE32[$charBits];
                $bit = 0;
                $charBits = 0;
            }
        }

        return $geohash;
    }
}
