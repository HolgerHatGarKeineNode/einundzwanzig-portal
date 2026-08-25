<?php

namespace App\Mcp\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Registry aller bearbeitbaren Eloquent-Models unter App\Models. Dient den generischen
 * Super-Admin-MCP-Tools, damit ein Super-Admin jede Entität per Name ansprechen kann –
 * ohne pro Model ein eigenes Tool zu pflegen.
 */
class SuperAdminModels
{
    /**
     * Attribute, die niemals über die generischen Super-Admin-Tools geschrieben werden
     * dürfen: Passwörter & Rollen, Auth-Credentials/-Tokens (remember_token, two_factor_*,
     * OAuth token/refresh_token/secret, lnurl-auth k1), die E-Mail-Verifizierung
     * (email_verified_at), der Nostr-Pubkey (Identitäts-Spoofing) sowie `created_by`.
     * `lecturers.lnurl` und `users.reputation` bleiben bewusst editierbar.
     *
     * `created_by` ist seit Issue #30 nicht mehr nur ein Herkunftsvermerk, sondern die
     * Achse zweier Mechanismen: `CityPolicy::updateIdentity()` (und die Ownership-Prüfung
     * jeder anderen Entität) hängt daran, und die Spalte trägt in mehreren Tabellen eine
     * Löschkaskade — `cities.created_by` nimmt bei einer Kontolöschung die Stadt mit, und
     * `meetups.city_id` die fremden Meetups darin. Umgeschrieben liesse sich damit
     * jemandem lautlos die Hoheit über einen Datensatz geben, an `cities:grant-steward`
     * vorbei und ohne Spur. Für den legitimen Fall gibt es bereits einen eigenen Weg:
     * `MergeUserAccounts` schreibt die Zeiger bewusst und nachvollziehbar um.
     *
     * @var list<string>
     */
    public const PROTECTED_ATTRIBUTES = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'role',
        'roles',
        'token',
        'refresh_token',
        'secret',
        'k1',
        'email_verified_at',
        'nostr',
        'created_by',
    ];

    /**
     * Kanonische Map Modelklasse → Tabellenname. Einmal pro Prozess ermittelt.
     *
     * @var array<class-string<Model>, string>|null
     */
    private static ?array $models = null;

    /**
     * Normalisierter Name (Kurzname ODER Tabelle) → Modelklasse. Lazy aus $models gebaut.
     *
     * @var array<string, class-string<Model>>|null
     */
    private static ?array $index = null;

    /**
     * Liste aller bearbeitbaren Models.
     *
     * @return array<int, array{key: string, class: class-string<Model>, table: string}>
     */
    public static function list(): array
    {
        $models = [];

        foreach (self::models() as $class => $table) {
            $models[] = [
                'key' => Str::kebab(class_basename($class)),
                'class' => $class,
                'table' => $table,
            ];
        }

        usort($models, fn (array $a, array $b): int => $a['key'] <=> $b['key']);

        return $models;
    }

    /**
     * Löst einen Model-Namen (Kurzname, FQCN, kebab-case oder Tabellenname) zur Klasse auf.
     *
     * @return class-string<Model>|null
     */
    public static function resolve(?string $name): ?string
    {
        if (! is_string($name) || trim($name) === '') {
            return null;
        }

        return self::index()[self::normalize(Str::of($name)->afterLast('\\')->value())] ?? null;
    }

    /**
     * Verfügbare Model-Keys (kebab-case) als Auswahlhilfe.
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_map(fn (array $m): string => $m['key'], self::list());
    }

    /**
     * Ermittelt einmalig alle ladbaren, konkreten Models unter App\Models samt Tabellenname.
     *
     * @return array<class-string<Model>, string>
     */
    private static function models(): array
    {
        if (self::$models !== null) {
            return self::$models;
        }

        $models = [];

        foreach (glob(app_path('Models').'/*.php') ?: [] as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');

            try {
                // Manche Model-Stubs erben von nicht (mehr) installierten Vendor-Klassen
                // (z. B. Jetstream) und lassen sich nicht laden – diese überspringen wir.
                if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                    continue;
                }

                if ((new ReflectionClass($class))->isAbstract()) {
                    continue;
                }

                $models[$class] = (new $class)->getTable();
            } catch (\Throwable) {
                continue;
            }
        }

        return self::$models = $models;
    }

    /**
     * Baut den Auflösungs-Index: Kurzname UND Tabellenname (jeweils normalisiert) → Klasse.
     *
     * @return array<string, class-string<Model>>
     */
    private static function index(): array
    {
        if (self::$index !== null) {
            return self::$index;
        }

        $index = [];

        foreach (self::models() as $class => $table) {
            $index[self::normalize(class_basename($class))] = $class;
            $index[self::normalize($table)] = $class;
        }

        return self::$index = $index;
    }

    private static function normalize(string $value): string
    {
        return Str::of($value)->lower()->replace(['-', '_', ' '], '')->value();
    }
}
