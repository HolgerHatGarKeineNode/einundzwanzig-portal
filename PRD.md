# PRD: Sicherheitshärtung der Datei-Uploads

## Projektübersicht

**Ziel:** Alle Datei-Uploads in der Anwendung absichern durch:
1. Migration aller Medien vom öffentlichen zum privaten Storage
2. Implementierung von Signed URLs für alle Medienzugriffe
3. Strikte MIME-Type Validierung für alle Uploads
4. Absicherung des `public/storage` Ordners

**Risikobewertung:** KRITISCH - Derzeit sind alle hochgeladenen Dateien ohne Authentifizierung öffentlich zugänglich.

---

## Aktuelle Sicherheitslücken

### Kritische Probleme
- [ ] Alle Medien werden auf dem `public` Disk gespeichert und sind über `/storage/` direkt zugänglich
- [ ] Keine Autorisierungsprüfung für Medienzugriffe
- [ ] API-Endpunkte geben ungeschützte Media-URLs zurück
- [ ] Keine strikte MIME-Type Validierung beim Upload

### Betroffene Modelle (10)
| Modell | Collections | Conversions |
|--------|-------------|-------------|
| Meetup | logo | preview, thumb |
| Course | logo, images | preview, thumb |
| Lecturer | avatar, images | preview, thumb |
| SelfHostedService | logo | preview, thumb |
| BitcoinEvent | logo | preview, thumb |
| BookCase | images | preview, seo, thumb |
| OrangePill | images | preview, thumb |
| Venue | images | preview, thumb |
| ProjectProposal | main | preview, thumb |
| LibraryItem | main, single_file, images | preview, seo, thumb |

---

## Milestone 1: Vorbereitung & Konfiguration

### 1.1 Media Library Konfiguration publizieren
- [ ] `php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-config"`
- [ ] Konfigurationsdatei `config/media-library.php` überprüfen

### 1.2 Storage Konfiguration anpassen
- [ ] Neuen privaten Disk in `config/filesystems.php` erstellen:
  ```php
  'media' => [
      'driver' => 'local',
      'root' => storage_path('app/media'),
      'visibility' => 'private',
  ],
  ```
- [ ] `.env` Variable hinzufügen: `MEDIA_DISK=media`

### 1.3 Media Library Konfiguration aktualisieren
- [ ] Default Disk auf `media` setzen
- [ ] Temporary URL Expiration auf 5 Minuten setzen
- [ ] Path Generator konfigurieren (falls custom benötigt)

### 1.4 Backup erstellen
- [ ] Vollständiges Backup des aktuellen `storage/app/public` Ordners
- [ ] Datenbank-Backup der `media` Tabelle

---

## Milestone 2: Upload-Härtung implementieren

### 2.1 Custom Upload-Validator erstellen
- [ ] `app/Rules/SecureImageUpload.php` erstellen:
  ```php
  // Prüft:
  // - Erlaubte MIME-Types (image/jpeg, image/png, image/gif, image/webp)
  // - Tatsächlicher Dateiinhalt (Magic Bytes)
  // - Keine doppelten Erweiterungen (.php.jpg)
  // - Keine eingebetteten PHP-Tags
  ```

### 2.2 Erlaubte MIME-Types definieren
- [ ] Für Bilder: `image/jpeg`, `image/png`, `image/gif`, `image/webp`, `image/svg+xml`
- [ ] Für Dokumente (LibraryItem): `application/pdf`, `application/zip`
- [ ] SVG-Upload deaktivieren oder mit Sanitizer versehen (XSS-Risiko)

### 2.3 Livewire Upload-Komponenten härten

#### Lecturers Create (`app/Livewire/Lecturers/Create.php`)
- [ ] Validierung auf SecureImageUpload Rule aktualisieren
- [ ] MIME-Type Whitelist hinzufügen
- [ ] Disk auf `media` umstellen

#### Lecturers Edit (`app/Livewire/Lecturers/Edit.php`)
- [ ] Validierung auf SecureImageUpload Rule aktualisieren
- [ ] MIME-Type Whitelist hinzufügen
- [ ] Disk auf `media` umstellen

#### Courses Create (`app/Livewire/Courses/Create.php`)
- [ ] Validierung auf SecureImageUpload Rule aktualisieren
- [ ] MIME-Type Whitelist hinzufügen
- [ ] Disk auf `media` umstellen

#### Courses Edit (`app/Livewire/Courses/Edit.php`)
- [ ] Validierung auf SecureImageUpload Rule aktualisieren
- [ ] MIME-Type Whitelist hinzufügen
- [ ] Disk auf `media` umstellen

#### Meetups Create (`app/Livewire/Meetups/Create.php`)
- [ ] Validierung auf SecureImageUpload Rule aktualisieren
- [ ] MIME-Type Whitelist hinzufügen
- [ ] Disk auf `media` umstellen

#### Meetups Edit (`app/Livewire/Meetups/Edit.php`)
- [ ] Validierung auf SecureImageUpload Rule aktualisieren
- [ ] MIME-Type Whitelist hinzufügen
- [ ] Disk auf `media` umstellen

### 2.4 Weitere Upload-Stellen prüfen und härten
- [ ] Services Create/Edit prüfen (derzeit keine Uploads)
- [ ] BitcoinEvent Upload-Stellen identifizieren und härten
- [ ] BookCase Upload-Stellen identifizieren und härten
- [ ] OrangePill Upload-Stellen identifizieren und härten
- [ ] Venue Upload-Stellen identifizieren und härten
- [ ] ProjectProposal Upload-Stellen identifizieren und härten
- [ ] LibraryItem Upload-Stellen identifizieren und härten

---

## Milestone 3: Modelle aktualisieren

### 3.1 Meetup Model
- [ ] `registerMediaCollections()` aktualisieren - Disk auf `media` setzen
- [ ] `logoSquare` Accessor auf Signed URL umstellen
- [ ] Tests schreiben

### 3.2 Course Model
- [ ] `registerMediaCollections()` aktualisieren - Disk auf `media` setzen
- [ ] Tests schreiben

### 3.3 Lecturer Model
- [ ] `registerMediaCollections()` aktualisieren - Disk auf `media` setzen
- [ ] Tests schreiben

### 3.4 SelfHostedService Model
- [ ] `registerMediaCollections()` aktualisieren - Disk auf `media` setzen
- [ ] Tests schreiben

### 3.5 BitcoinEvent Model
- [ ] `registerMediaCollections()` aktualisieren - Disk auf `media` setzen
- [ ] Tests schreiben

### 3.6 BookCase Model
- [ ] `registerMediaCollections()` aktualisieren - Disk auf `media` setzen
- [ ] Tests schreiben

### 3.7 OrangePill Model
- [ ] `registerMediaCollections()` aktualisieren - Disk auf `media` setzen
- [ ] Tests schreiben

### 3.8 Venue Model
- [ ] `registerMediaCollections()` aktualisieren - Disk auf `media` setzen
- [ ] Tests schreiben

### 3.9 ProjectProposal Model
- [ ] `registerMediaCollections()` aktualisieren - Disk auf `media` setzen
- [ ] Tests schreiben

### 3.10 LibraryItem Model
- [ ] `registerMediaCollections()` aktualisieren - Disk auf `media` setzen
- [ ] `toFeedItem()` Methode auf Signed URLs umstellen
- [ ] MIME-Type Validierung für single_file Collection verschärfen
- [ ] Tests schreiben

---

## Milestone 4: Signed URL Service implementieren

### 4.1 Media URL Helper erstellen
- [ ] `app/Services/MediaUrlService.php` erstellen:
  ```php
  class MediaUrlService
  {
      public function getSignedUrl(
          Media $media,
          string $conversion = '',
          int $expirationMinutes = 60
      ): string;

      public function getSignedMediaUrl(
          Model $model,
          string $collection,
          string $conversion = ''
      ): ?string;
  }
  ```

### 4.2 Blade Directive erstellen
- [ ] Custom Blade Directive `@mediaUrl($model, 'collection', 'conversion')` erstellen
- [ ] In `AppServiceProvider` registrieren

### 4.3 API Response Trait erstellen
- [ ] `app/Traits/HasSignedMediaUrls.php` erstellen für konsistente API-Responses

---

## Milestone 5: Controller aktualisieren

### 5.1 ImageController überarbeiten
- [ ] Authentifizierung/Autorisierung hinzufügen
- [ ] Signed URL Validierung implementieren
- [ ] Rate Limiting hinzufügen (z.B. 100 Anfragen/Minute)
- [ ] Nur erlaubte Pfade bedienen
- [ ] Cache-Header für private Inhalte anpassen

### 5.2 API MeetupController
- [ ] `getFirstMediaUrl()` durch Signed URL ersetzen (Zeile 51)
- [ ] Tests aktualisieren

### 5.3 API LecturerController
- [ ] `getFirstMediaUrl()` durch Signed URL ersetzen (Zeile 37)
- [ ] Tests aktualisieren

### 5.4 API CourseController
- [ ] `getFirstMediaUrl()` durch Signed URL ersetzen (Zeile 36)
- [ ] Tests aktualisieren

### 5.5 routes/api.php aktualisieren
- [ ] Zeile 55: `/api/bindles` - Signed URL für main collection
- [ ] Zeile 88: `/api/meetups` - Signed URL für logos
- [ ] Zeile 131: `/api/meetup-events/{date}` - Signed URL für logos

---

## Milestone 6: Views aktualisieren

### 6.1 Lecturer Views
- [ ] `resources/views/livewire/lecturers/index.blade.php` (Zeile 88)
- [ ] `resources/views/livewire/lecturers/edit.blade.php` (Zeile 119)

### 6.2 Course Views
- [ ] `resources/views/livewire/courses/index.blade.php`
- [ ] `resources/views/livewire/courses/edit.blade.php` (Zeile 101)
- [ ] `resources/views/livewire/courses/landingpage.blade.php`

### 6.3 Meetup Views
- [ ] `resources/views/livewire/meetups/index.blade.php` (Zeile 88)
- [ ] `resources/views/livewire/meetups/edit.blade.php` (Zeile 119)
- [ ] `resources/views/livewire/meetups/landingpage.blade.php`
- [ ] `resources/views/livewire/meetups/landingpage-event.blade.php`

### 6.4 Dashboard Views
- [ ] `resources/views/livewire/dashboard/top-meetups.blade.php`
- [ ] `resources/views/livewire/dashboard/activities.blade.php`

### 6.5 Weitere Views durchsuchen
- [ ] Alle verbleibenden `getFirstMediaUrl()` Aufrufe in Blade-Templates finden
- [ ] Alle auf Signed URLs umstellen

---

## Milestone 7: Datenmigration

### 7.1 Migration Command erstellen
- [ ] `app/Console/Commands/MigrateMediaToPrivateStorage.php` erstellen:
  ```php
  // - Alle Medien vom public zum media Disk verschieben
  // - media Tabelle aktualisieren (disk Spalte)
  // - Conversions neu generieren
  // - Fortschrittsanzeige
  // - Dry-run Option
  ```

### 7.2 Migration durchführen
- [ ] Dry-run ausführen und Ergebnis prüfen
- [ ] Tatsächliche Migration durchführen
- [ ] Verifizieren, dass alle Dateien verschoben wurden
- [ ] Verifizieren, dass Conversions funktionieren

### 7.3 Alte Dateien aufräumen
- [ ] Prüfen ob noch Dateien in `storage/app/public` liegen
- [ ] Alte Dateien löschen (nach Bestätigung)
- [ ] Symlink `public/storage` entfernen oder umbenennen

---

## Milestone 8: public/storage absichern

### 8.1 Symlink-Analyse
- [ ] Prüfen welche Dateien noch über Symlink erreichbar sein müssen
- [ ] Liste der Legacy-Dateien erstellen

### 8.2 Symlink-Strategie festlegen
**Option A:** Symlink komplett entfernen
- [ ] Alle Referenzen auf `/storage/` URLs finden und ersetzen
- [ ] Symlink löschen

**Option B:** Symlink auf leeren/kontrollierten Ordner zeigen lassen
- [ ] Neuen leeren Ordner erstellen
- [ ] Symlink umbiegen
- [ ] 403/404 für unbekannte Pfade

### 8.3 .htaccess / nginx Regeln
- [ ] Falls Apache: `.htaccess` in `public/storage` mit Deny-Regeln
- [ ] Falls Nginx: Location-Block mit deny all

### 8.4 Laravel Route für Legacy-URLs
- [ ] Catch-all Route für `/storage/*` erstellen
- [ ] 403 Forbidden oder Redirect zu Signed URL

---

## Milestone 9: Tests

### 9.1 Unit Tests
- [ ] `tests/Unit/Rules/SecureImageUploadTest.php`
- [ ] `tests/Unit/Services/MediaUrlServiceTest.php`

### 9.2 Feature Tests - Upload Sicherheit
- [ ] Test: PHP-Datei als Bild getarnt wird abgelehnt
- [ ] Test: Doppelte Erweiterungen werden abgelehnt
- [ ] Test: Nur erlaubte MIME-Types werden akzeptiert
- [ ] Test: Zu große Dateien werden abgelehnt

### 9.3 Feature Tests - Signed URLs
- [ ] Test: Abgelaufene Signed URL wird abgelehnt
- [ ] Test: Manipulierte Signed URL wird abgelehnt
- [ ] Test: Gültige Signed URL liefert Datei

### 9.4 Feature Tests - Storage Zugriff
- [ ] Test: Direkter Zugriff auf `/storage/` gibt 403/404
- [ ] Test: Medien im private Storage nicht öffentlich zugänglich
- [ ] Test: Nur authentifizierte Benutzer können eigene Medien sehen (falls relevant)

### 9.5 Browser Tests
- [ ] Test: Bild-Upload funktioniert korrekt
- [ ] Test: Bilder werden in Views korrekt angezeigt
- [ ] Test: API gibt korrekte Signed URLs zurück

---

## Milestone 10: Dokumentation & Cleanup

### 10.1 Entwickler-Dokumentation
- [ ] Neue Upload-Richtlinien dokumentieren
- [ ] Signed URL Nutzung dokumentieren
- [ ] Migration-Prozess dokumentieren

### 10.2 Code Cleanup
- [ ] Unbenutzte alte Upload-Logik entfernen
- [ ] Deprecation Warnings für alte Methoden hinzufügen (falls noch verwendet)
- [ ] Laravel Pint laufen lassen

### 10.3 Deployment Checkliste
- [ ] Migration Command in Deployment-Skript aufnehmen
- [ ] Storage-Berechtigungen auf Server prüfen
- [ ] Cache leeren nach Deployment
- [ ] Conversions neu generieren falls nötig

---

## Abnahmekriterien

### Sicherheit
- [ ] Kein direkter öffentlicher Zugriff auf hochgeladene Dateien möglich
- [ ] Alle Medienzugriffe laufen über Signed URLs mit Ablaufzeit
- [ ] PHP-Dateien und andere gefährliche Dateitypen können nicht hochgeladen werden
- [ ] MIME-Type wird serverseitig validiert (nicht nur Client-seitig)

### Funktionalität
- [ ] Alle bestehenden Upload-Funktionen arbeiten weiterhin korrekt
- [ ] Alle Bilder werden in der Anwendung korrekt angezeigt
- [ ] API-Endpunkte liefern funktionierende (Signed) URLs
- [ ] Conversions (thumb, preview, seo) funktionieren weiterhin

### Performance
- [ ] Signed URL Generierung < 10ms
- [ ] Keine merkbare Verzögerung beim Laden von Bildern
- [ ] Caching funktioniert weiterhin effektiv

---

## Technische Details

### Spatie Media Library Signed URLs

```php
// In Model
public function getSignedLogoUrl(): string
{
    return $this->getFirstMedia('logo')
        ?->getTemporaryUrl(now()->addMinutes(60))
        ?? '/images/default-logo.png';
}
```

### Konfiguration für Signed URLs
```php
// config/media-library.php
'temporary_urls' => [
    'expiration_time_in_seconds' => 60 * 60, // 1 Stunde
],
```

### Validierungsregel Beispiel
```php
// app/Rules/SecureImageUpload.php
public function validate(string $attribute, mixed $value, Closure $fail): void
{
    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    // Echte MIME-Type Prüfung via finfo
    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($value->getRealPath());

    if (!in_array($mimeType, $allowedMimes)) {
        $fail('Nur Bilddateien (JPEG, PNG, GIF, WebP) sind erlaubt.');
    }

    // Prüfe auf PHP-Tags im Dateiinhalt
    $content = file_get_contents($value->getRealPath());
    if (preg_match('/<\?php|<\?=/i', $content)) {
        $fail('Die Datei enthält unerlaubten Code.');
    }
}
```

---

## Risiken & Mitigationen

| Risiko | Wahrscheinlichkeit | Auswirkung | Mitigation |
|--------|-------------------|------------|------------|
| Broken Images nach Migration | Mittel | Hoch | Umfassende Tests, Rollback-Plan |
| Performance-Einbußen durch Signed URLs | Niedrig | Mittel | Caching der generierten URLs |
| Inkompatibilität mit externen Services | Mittel | Mittel | API-Versioning, Übergangszeit |
| Datenverlust bei Migration | Niedrig | Kritisch | Vollständiges Backup vor Migration |

---

## Zeitplan (geschätzt)

| Milestone | Geschätzter Aufwand |
|-----------|---------------------|
| 1. Vorbereitung & Konfiguration | 2-4 Stunden |
| 2. Upload-Härtung | 4-6 Stunden |
| 3. Modelle aktualisieren | 3-4 Stunden |
| 4. Signed URL Service | 2-3 Stunden |
| 5. Controller aktualisieren | 2-3 Stunden |
| 6. Views aktualisieren | 2-3 Stunden |
| 7. Datenmigration | 2-4 Stunden |
| 8. public/storage absichern | 1-2 Stunden |
| 9. Tests | 4-6 Stunden |
| 10. Dokumentation & Cleanup | 1-2 Stunden |
| **Gesamt** | **23-37 Stunden** |

---

## Referenzen

- [Spatie Media Library Dokumentation](https://spatie.be/docs/laravel-medialibrary)
- [Laravel Storage Dokumentation](https://laravel.com/docs/filesystem)
- [OWASP File Upload Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html)
