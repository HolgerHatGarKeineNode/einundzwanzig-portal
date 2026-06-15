# Portal-API-Paritäts-Plan — „Write-Parität App ↔ Web"

> **Mission:** Die Portal-**REST-API** so vervollständigen, dass die Mobile-App
> jede **EDIT/CREATE**-Funktion ausführen kann, die das Portal-**Web** (Livewire/Volt)
> bereits beherrscht. Ziel ist Funktions-Parität: Was ein angemeldeter Nutzer im Web
> anlegen/bearbeiten kann, muss er auch über die API (= in der App) können. Zusätzlich
> wird die **Scramble-API-Doku** auf den vollständigen, aktuellen Stand gebracht.
>
> **Single Source of Truth.** Diese Datei wird abgehakt. Bei Session-Start lesen, erste
> offene `[ ]`-Checkbox finden, dort weitermachen, Erledigtes auf `[x]` setzen. Größere
> Entscheidungen ins **Entscheidungs-Log** (unten).

---

## Ausgangslage (Stand 2026-06-15, am Quellcode verifiziert)

- **Portal-Stack:** Laravel 12 · Livewire 4 (Volt-SFC) · Flux UI v2 · Sanctum v4 · Horizon · Pest 4. Doku via **Scramble** (Dedoc, OpenAPI 3 → `api.json`, UI unter `/docs/api`), generiert aus FormRequests + API-Resources + PHPDoc/Attributen (`config/scramble.php`).
- **Web kann bereits ALLES:** Für Meetups, Meetup-Termine, Venues, Cities, Lecturers, Courses, Course-Events **und** „Meetup zu meinen hinzufügen" existieren Create-/Edit-Volt-Komponenten unter `resources/views/livewire/**` + Web-Routen (`routes/web.php:113-151`). **Kein** Admin-Panel (Filament/Nova) — normale Nutzer verwalten ihre eigenen Inhalte.
- **Die API hinkt hinterher** — die App nutzt sie und stößt an diese Lücken:
  1. **Media-Upload fehlt komplett in der API.** Models haben Spatie-Collections (`Meetup.logo`, `Lecturer.avatar`+`images`, `Course.logo`+`images`, `Venue.images`), aber es gibt **keinen** multipart-Endpunkt. Das Web lädt direkt via Livewire `flux:file-upload` → `addMedia(...)->toMediaCollection(...)` hoch.
  2. **Course & CourseEvent sind API-seitig inkonsistent:** **keine** FormRequest-Klassen (inline `$request->validate()`), **keine** `CoursePolicy`/`CourseEventPolicy` (Autorisierung inline via `abort_unless`), **keine** API-Resource (Controller geben `->fresh()` = rohes Model zurück). Alle anderen Entitäten nutzen StoreXRequest/UpdateXRequest + Policy + XResource.
  3. **Recurrence ohne Expansion in der API:** Das Web expandiert eine Serie beim Speichern in **einzelne** `MeetupEvent`-Records (`meetups/create-edit-events.blade.php` `createEventSeries()`). Die `recurrence_*`-Spalten werden gespeichert, aber **nicht** zur Anzeige gelesen und **nicht** vom Observer expandiert. Die API akzeptiert die `recurrence_*`-Felder, erzeugt aber **keine** Serie.
  4. **Doku unvollständig:** zuletzt ergänzte Endpunkte (`my-meetups`, `addToMine`, `my-lecturers`/`my-venues`/`my-cities`/`my-meetup-events`, `mobile/token`) sind ohne kuratierte Beschreibung; Course/CourseEvent liefern mangels Resource kein sauberes Response-Schema an Scramble.

### Bewusste Nicht-Ziele (keine Paritätslücke)

- **Kurs-Kategorien:** Die `Course::categories()`-Relation existiert, wird aber **auch im Web nicht gepflegt** (nur `DatabaseSeeder` vergibt zufällig). Kein Web-Feature → kein Paritätsziel. → **Backlog** (siehe Offene Fragen).
- **Venue-Geo/Typ:** Venues haben portalseitig keine Geo-/Typ-Spalten (Geo hängt an `cities.latitude/longitude`); das Web kennt das ebenfalls nicht. Kein Paritätsziel.
- **DELETE / „absagen/inaktiv":** Das Web hat **keine** Lösch-/Absage-Funktion für normale Nutzer (kein `cancelled`-Feld, keine DELETE-Route). Da der Auftrag „EDIT/CREATE" lautet, ist DELETE **nicht** im Hauptscope → optionaler Punkt (siehe Offene Fragen).

### Leitprinzipien

1. **Web ist die Referenz.** Validierungsregeln, Collections und Autorisierungs-Semantik 1:1 aus den Volt-Komponenten spiegeln — keine Neuerfindung.
2. **Konsistenz vor Sonderlocken.** Course/CourseEvent auf dasselbe FormRequest+Policy+Resource-Muster heben wie Meetup/Venue/City/Lecturer.
3. **Doku-getrieben.** Saubere FormRequests + Resources = saubere Scramble-Schemas. Doku ist Nebenprodukt korrekter Klassen, nicht handgepflegtes JSON.
4. **DRY zwischen Web & API.** Wo Web und API dieselbe Fachlogik brauchen (Recurrence-Expansion, Media-Validierung), eine gemeinsame Action/FormRequest extrahieren, statt zu duplizieren.
5. **Keine Breaking Changes.** Bestehende Request-Felder/Response-Shapes bleiben kompatibel (additiv erweitern).
6. **Jede Änderung getestet** (Pest-Feature-Tests gegen die API-Routen), `vendor/bin/pint` vor Abschluss.

---

## Phase P0 — Konsistenz-Fundament: Course & CourseEvent angleichen 🏗️

> Ohne FormRequests + Resources kann Scramble keine sauberen Schemas erzeugen, und die Upload-/Serien-Arbeit baut darauf auf. Zuerst die Schiene legen.

- [x] **P0.1** `StoreCourseRequest` + `UpdateCourseRequest` (`app/Http/Requests/Api/`) anlegen — Felder `name` (req, max:255), `lecturer_id` (req, exists:lecturers,id), `description` (nullable). `authorize()` über `CoursePolicy`. Update mit `sometimes`.
- [x] **P0.2** `StoreCourseEventRequest` + `UpdateCourseEventRequest` — Felder `course_id` (req, exists), `venue_id` (req, exists), `from` (req, date), `to` (req, date, after_or_equal:from), `link` (req, url, max:255).
- [x] **P0.3** `CoursePolicy` + `CourseEventPolicy` anlegen (im selben Muster wie `MeetupPolicy`/`VenuePolicy`, `ChecksCreatorOwnership`-Trait nutzen):
  - `CoursePolicy::create` = `is_lecturer` (spiegelt das bisherige `abort_unless($user->is_lecturer)`), `update` = owner||super-admin.
  - `CourseEventPolicy::create` = `is_lecturer`, `update` = owner||super-admin.
  - In `AuthServiceProvider`/Policy-Auto-Discovery registrieren.
- [x] **P0.4** `CourseResource` + `CourseEventResource` anlegen (Feldwahl an `CourseController::show`/`index` orientieren; Logo-/Venue-/Lecturer-Beziehungen wie in den Lese-Endpunkten). Damit liefern store/update strukturierte, dokumentierbare Responses statt `->fresh()`.
- [x] **P0.5** `CourseController` + `CourseEventController` refactoren: Inline-`validate()` → FormRequest, Inline-`abort_unless` → `$this->authorize(...)`/Policy, `->fresh()` → Resource. Verhalten unverändert (gleiche Felder, gleiche 403/422-Semantik).
- [x] **P0.6** Tests: `tests/Feature/Api/CourseWriteTest.php` + `CourseEventWriteTest.php` (Create/Update, 401 Gast, 403 Nicht-Lecturer/Fremd-Edit, 422 Pflichtfelder, Response-Shape). Bestehende Lese-Tests dürfen nicht brechen.

**Akzeptanz:** Course/CourseEvent verhalten sich nach außen identisch wie zuvor, basieren intern aber auf FormRequest+Policy+Resource; Scramble zeigt für `POST/PATCH /courses` + `/course-events` korrekte Request-/Response-Schemas.

---

## Phase P1 — Media-Upload-API (Logo / Avatar) 🖼️

> Die größte echte Paritätslücke. Das Web lädt via `flux:file-upload` direkt in Spatie-Collections; die API kann es gar nicht.

- [x] **P1.1** Entscheidung umsetzen: **dedizierte Upload-Endpunkte** statt multipart-in-Store (siehe Log). Routen in der `auth:sanctum`-Gruppe:
  - `POST /api/meetup/{meetup}/logo` → `MeetupController::uploadLogo`
  - `POST /api/lecturers/{lecturer}/avatar` → `LecturerController::uploadAvatar`
  - `POST /api/courses/{course}/logo` → `CourseController::uploadLogo`
  - *(optional, falls App es braucht: `…/images` für Multi-Collections bei Lecturer/Course/Venue — erst bei Bedarf, siehe Offene Fragen.)*
- [x] **P1.2** Gemeinsame `UploadMediaRequest` (FormRequest) mit der **exakt aus dem Web gespiegelten** Validierung: `image|mimes:jpeg,png,webp,avif|max:5120|dimensions:max_width=4000,max_height=4000` (Feld `file`/`image`). `authorize()` = `update`-Policy der jeweiligen Entität (nur Ersteller/Admin).
- [x] **P1.3** Controller-Methoden spiegeln das Web-Muster: `clearMediaCollection(...)` (singleFile) → `addMedia($file->getRealPath())->usingName(...)->toMediaCollection('logo'|'avatar')`; Response = die jeweilige Resource (mit frischer Media-URL).
- [x] **P1.4** `MediaUrl`/Resource-Felder prüfen: stellen die Resources die Conversion-URLs (`preview`/`thumb`) + Original bereit, damit die App das neue Bild sofort anzeigt?
- [x] **P1.5** Tests: `tests/Feature/Api/MediaUploadTest.php` mit `UploadedFile::fake()->image(...)` — Erfolg (Collection befüllt, URL geändert), 401 Gast, 403 Fremd-Upload, 422 (kein Bild / zu groß / falscher MIME / zu große Dimension).

**Akzeptanz:** Ein verbundener Nutzer kann per API ein Meetup-Logo, Referenten-Avatar und Kurs-Logo hoch-/ersetzen; Validierung deckungsgleich mit dem Web; Response liefert die neue Bild-URL.

---

## Phase P2 — Recurrence-Serien-Parität (Meetup-Termine) 🔁

> Das Web erzeugt aus einer Wiederhol-Regel **echte Einzeltermine**. Die API muss dasselbe tun — ohne die Logik zu duplizieren.

- [x] **P2.1** Die Expansionslogik aus `meetups/create-edit-events.blade.php` (`getPreviewDatesProperty()` + `createEventSeries()`) in eine wiederverwendbare **Action/Service** extrahieren, z. B. `app/Actions/MeetupEvents/ExpandRecurrenceSeries.php` (gibt die Liste der `start`-DateTimes zurück) + `CreateMeetupEventSeries` (persistiert die Einzeltermine). Carbon-basiert, UTC-sauber, harte Obergrenze (z. B. 100 Termine wie im Web).
- [x] **P2.2** Das **Web** auf die neue Action umstellen (Refactor ohne Verhaltensänderung — Preview + Speichern nutzen dieselbe Quelle). Sichert echte Parität + verhindert Drift.
- [x] **P2.3** `MeetupEventController::store` erweitern: bei gesetztem `recurrence_type` die Action aufrufen (Serie anlegen) statt eines Einzeltermins; Response = Liste der erzeugten Events (oder das erste + Anzahl). Ohne `recurrence_type` unverändert ein Einzeltermin. `StoreMeetupEventRequest` validiert die `recurrence_*`-Felder bereits — Regeln gegen die Action-Erwartungen abgleichen.
- [x] **P2.4** Klären/entscheiden: bleibt der `recurrence_*`-Metadaten-Block am ersten Event erhalten (für späteres iCal/rrule), oder werden nur Einzeltermine persistiert? (Web speichert aktuell Einzeltermine; siehe Offene Fragen.)
- [x] **P2.5** Tests: Serie über die API anlegen (wöchentlich/monatlich, `recurrence_end_date`) → erwartete Anzahl Einzeltermine; Obergrenze; Einzeltermin-Pfad unverändert; gemeinsame Action unit-getestet.

**Akzeptanz:** Ein API-Aufruf mit Wiederhol-Regel erzeugt dieselben Einzeltermine wie der Web-Editor (gemeinsame Action, kein Duplikat-Code).

---

## Phase P3 — Scramble-API-Doku vervollständigen 📖

> Alles Neue **und** das zuletzt Erstellte dokumentieren.

- [x] **P3.1** Alle Write-Controller-Methoden mit kuratiertem PHPDoc + `#[Group(...)]` versehen (Titel + 1–2 Sätze Zweck, Auth-Hinweis), Muster wie `CourseController::show`. Betroffen: Meetup/MeetupEvent/Venue/City/Lecturer/Course/CourseEvent store+update, die neuen Upload-Endpunkte (P1), die Serie (P2).
- [x] **P3.2** Die **zuletzt erstellten** Endpunkte dokumentieren: `GET /my-meetups`, `POST /my-meetups/{slug}` (addToMine, idempotent), `GET /my-meetups/{meetup}`, `GET /my-lecturers(+/{})`, `GET /my-venues(+/{})`, `GET /my-cities(+/{})`, `GET /my-meetup-events(+/{})`, `GET /course-events`, `POST/DELETE /mobile/token`, Nostr/LNURL-Callbacks.
- [x] **P3.3** Fehler-Antworten dokumentieren (401/403/422) — wo Scramble sie nicht automatisch ableitet, via Attribut/PHPDoc ergänzen.
- [x] **P3.4** `GET /api/meetup` (öffentliche Index-Route, von der App-seitig als 401-Falle erkannt) prüfen: korrekt als **öffentlich/ohne Member-Kontext** dokumentieren bzw. ggf. deprecaten (die App nutzt `my-meetups`).
- [x] **P3.5** `api.json` regenerieren und `/docs/api` (Scalar) sichten — alle Write-Pfade sichtbar, mit Bearer-Markierung, Request-/Response-Schema, Beispielen.

**Akzeptanz:** `/docs/api` zeigt sämtliche Schreib- und „my-*"-Endpunkte vollständig mit Beschreibung, Auth, Parametern und Schemas; `api.json` ist aktuell.

---

## Phase P4 — Autorisierungs-Parität entscheiden 🔐

- [x] **P4.1** **`updateViaPortal` vs. `update`** (Offene Frage): Das Web erlaubt **Ersteller ODER Meetup-Mitglied** das Bearbeiten der Meetup-Stammdaten (`MeetupPolicy::updateViaPortal`), die API nur den **Ersteller** (`update`). Entscheiden: soll die API (= App) dieselbe gelockerte Regel bekommen (echte 1:1-Parität) oder bewusst strikt bleiben? Default-Empfehlung: **strikt** lassen + dokumentieren, bis ein echtes Rollen-/Freigabekonzept existiert. Falls Parität gewünscht: `MeetupController::update` auf `updateViaPortal` umstellen + Tests.
- [x] **P4.2** Ergebnis im Entscheidungs-Log + in der API-Doku festhalten.

---

## Phase A — App nachziehen (Repo `einundzwanzig-mobile-app`)

> Folge-Arbeit, sobald die Portal-Phasen stehen. Entsperrt die in `VERSION_1_2_0.md` als „portalseitig blockiert" geparkten Punkte. Detailplanung dort.

- [ ] **A1** **Logo/Avatar-Upload in den App-Editoren** (NativePHP Camera/Photo-Picker → multipart an die neuen Endpunkte aus P1). Entsperrt `VERSION_1_2_0.md` **4.6** (Meetup-Logo) + **7.1** (Referenten-Avatar). Felder in `meetup-editor`/`lecturer-editor`/`course-editor` ergänzen, `PortalWriter` um Upload-Methoden erweitern.
- [ ] **A2** **Recurrence-UI im `event-editor`** (Wiederhol-Typ/Tag/Position/Ende) → Serie über den Endpunkt aus P2 anlegen. Entsperrt `VERSION_1_2_0.md` **5.4**.
- [ ] **A3** Optional, nur falls P-Backlog gezogen wird: Kurs-Kategorie-Auswahl (erst wenn das **Web** sie bekommt — sonst keine Parität).

---

## Empfohlene Reihenfolge

`P0 → P1 → P2 → P3 → P4 → A1 → A2`

Begründung: P0 (Konsistenz-Fundament) ist Voraussetzung für saubere Doku und baut die Schiene für P1/P2. Media-Upload (P1) ist die größte Nutzer-spürbare Lücke. Recurrence (P2) braucht den Web-Refactor (DRY). Doku (P3) schließt den Portal-Teil ab, P4 ist eine kleine Policy-Entscheidung. Danach zieht die App (A) die neuen Fähigkeiten nach.

---

## Offene Fragen / Klären

- [x] **Upload-Design:** **Entschieden — dedizierte Endpunkte, zweistufig.** Die App legt Stammdaten per JSON an und lädt das Bild danach separat hoch (`POST …/{id}/logo|avatar`). Bild-Update unabhängig von Stammdaten.
- [ ] **Multi-Image-Collections** (`Lecturer.images`, `Course.images`, `Venue.images`): braucht die App das, oder reicht das Single-File-Logo/Avatar? (Nur bei Bedarf bauen — **noch offen, bewusst nicht gebaut**.)
- [x] **Recurrence-Persistenz (P2.4):** **Entschieden — nur Einzeltermine** (1:1 wie Web heute), keine `recurrence_*`-Metadaten am Master-Event. iCal/rrule bleibt Backlog.
- [x] **`updateViaPortal`-Parität (P4.1):** **Entschieden — API bleibt strikt** (nur Ersteller/Super-Admin). Die gelockerte Mitglieder-Regel bleibt Portal-/Livewire-exklusiv (`MeetupPolicy::updateViaPortal`), bis ein echtes Rollen-/Freigabekonzept existiert.
- [ ] **Kategorien (Backlog):** Soll das **Web** zuerst eine Kategorie-Verwaltung bekommen? Erst dann ergibt eine Kategorie-API für die App-Parität Sinn. Relation + `category_course`-Pivot + `Category`-Model existieren bereits.
- [ ] **DELETE/Absage (Backlog):** Sollen Nutzer eigene Termine/Inhalte löschen oder absagen können? Bräuchte Web **und** API + Policies (`delete`) + ggf. ein `cancelled`/`is_active`-Statusfeld. Aktuell weder Web noch API.

---

## Entscheidungs-Log

| Datum | Entscheidung | Begründung |
|------|--------------|-----------|
| 2026-06-15 | Plan-Ziel = **API-Schreib-Parität App ↔ Web**, Web bleibt unangetastet | Recherche ergab: das Web kann bereits alles (Volt-Editoren), die App hinkt nur hinterher, weil die REST-API Upload/Serien nicht exponiert und Course/CourseEvent inkonsistent sind. Parität wird durch API-Ausbau + App-Nachzug erreicht, nicht durch Web-Umbau |
| 2026-06-15 | **Kategorien & Venue-Geo aus dem Hauptscope** | Beide sind **auch im Web** kein Nutzer-Feature (Kategorie nur per Seeder, Venue ohne Geo-Spalten) → keine Paritätslücke. Kategorie wandert ins Backlog (Relation existiert), Venue-Geo bleibt als bewusst stadtgebunden |
| 2026-06-15 | **Course/CourseEvent zuerst auf FormRequest+Policy+Resource heben (P0)** | Sie sind die einzigen Entitäten mit Inline-Validate, ohne Policy und ohne Resource. Scramble braucht Requests/Resources für saubere Schemas; Upload/Serie bauen darauf auf — Konsistenz zuerst |
| 2026-06-15 | **Media-Upload als dedizierte Endpunkte** (`POST …/{id}/logo` etc.), nicht multipart-in-Store | Trennt Bild-Upload vom JSON-Stammdaten-Write (die App-Editoren senden JSON); vermeidet gemischte multipart/JSON-Requests; erlaubt Bild-Ersatz ohne Stammdaten-Update. Validierung 1:1 aus dem Web gespiegelt (`image|mimes:jpeg,png,webp,avif|max:5120|dimensions:…`) |
| 2026-06-15 | **Recurrence-Expansion als gemeinsame Action** (Web + API), nicht Server-Observer | Das Web expandiert bereits beim Speichern in Einzeltermine (`createEventSeries`); die `recurrence_*`-Spalten werden nicht zur Anzeige gelesen. Eine geteilte Action erreicht echte 1:1-Parität ohne Code-Duplikat und ohne das bestehende Anzeige-Modell (Einzeltermine) zu ändern |
| 2026-06-15 | **DELETE/Absage nicht im Hauptscope** | Auftrag lautet „EDIT/CREATE". Weder Web noch API bieten Löschen/Absagen für Nutzer; als Backlog-Frage dokumentiert statt halbfertig zu bauen |
| 2026-06-15 | **P0–P4 umgesetzt** (Portal-Teil abgeschlossen) | Course/CourseEvent auf FormRequest+Policy+Resource gehoben; Media-Upload-API (Meetup-Logo, Lecturer-Avatar, Course-Logo) via dedizierte Endpunkte + gemeinsame `UploadMediaRequest`; Recurrence-Expansion als geteilte Action `ExpandRecurrenceSeries` (Web + API, harte Obergrenze 100); Scramble-Doku verifiziert (`scramble:analyze` fehlerfrei). 130 Tests grün, Pint sauber |
| 2026-06-15 | **API-Antworten für Course/CourseEvent jetzt `data`-gewrappt** (Resource) | Konsistenz mit Meetup/Venue/Lecturer (alle `data`-gewrappt). Bedeutet eine Shape-Änderung ggü. dem alten rohen JSON — wird in Phase A (Mobile-App) nachgezogen. Bestehende Tests entsprechend aktualisiert |
| 2026-06-15 | **`recurrence_type` ohne `recurrence_end_date` ⇒ Einzeltermin** | Serie wird nur erzeugt, wenn beide Felder gesetzt sind (wie der Web-Serien-Modus mit Pflicht-Enddatum). Sonst Backward-Compatible: ein einzelnes Event inkl. der übergebenen `recurrence_*`-Spalten |
