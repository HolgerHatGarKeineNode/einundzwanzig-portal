<?php

/*
|--------------------------------------------------------------------------
| Tag vocabulary
|--------------------------------------------------------------------------
|
| Curated from the live production set on 2026-08-17 (89 tags, 746 taggables)
| and translated into the nine locales under lang/.
|
| Two shapes for `name`:
|   - a string  → proper noun or loanword, used verbatim in every locale
|                 (Bitcoin, Nostr, Lightning, V4V, Bindle, GiGi, …)
|   - an array  → real translations, keyed by locale; German is the source
|
| `type` groups tags by the entity they describe — that is the existing house
| convention (`library_item`, `course`), not a semantic grouping. Events get
| their own `meetup_event` type rather than reusing the library vocabulary,
| which is a media taxonomy (News, Artikel, Videos, Lyrics) and a poor fit.
|
| Curation applied to the live set, all merges keep the most-used spelling:
|   - Philosophie / Etatismus / Kryptografie / News / Bitcoin(course) existed
|     twice, once German once English, as SEPARATE rows — merged
|   - "#Bindle " → Bindle · "Immmobilien" → Immobilien (typo, 0 uses)
|   - Reise + Travel → one tag · Privacy + Privatsphäre → one tag
|   - "Bitcoin" (library_item, 2 uses) folded into "Allgemein Bitcoin" (69)
|   - PlebRapCash → PlebRap
|   - wissenschaftliche Arbeit + Masterarbeit + Bachelorarbeit → Abschlussarbeit
|   - "Nostr" kept despite 0 uses: live topic, will be needed
|
*/

return [

    /*
     * Event vocabulary — new, not derived from the library set.
     * `featured` entries are what the picker offers before the user types.
     */
    'meetup_event' => [
        // Formats
        ['icon' => 'microphone-stand', 'featured' => true, 'name' => [
            'de' => 'Vortrag', 'en' => 'Talk', 'cs' => 'Přednáška', 'es' => 'Charla',
            'hu' => 'Előadás', 'lv' => 'Lekcija', 'nl' => 'Lezing', 'pl' => 'Prelekcja', 'pt' => 'Palestra',
        ]],
        ['icon' => 'chalkboard-user', 'featured' => true, 'name' => [
            'de' => 'Workshop', 'en' => 'Workshop', 'cs' => 'Workshop', 'es' => 'Taller',
            'hu' => 'Műhely', 'lv' => 'Darbnīca', 'nl' => 'Workshop', 'pl' => 'Warsztaty', 'pt' => 'Oficina',
        ]],
        ['icon' => 'beer-mug', 'featured' => true, 'name' => [
            'de' => 'Stammtisch', 'en' => 'Meetup', 'cs' => 'Setkání', 'es' => 'Encuentro',
            'hu' => 'Törzsasztal', 'lv' => 'Tikšanās', 'nl' => 'Stamtafel', 'pl' => 'Spotkanie', 'pt' => 'Encontro',
        ]],
        ['icon' => 'film', 'featured' => false, 'name' => [
            'de' => 'Filmabend', 'en' => 'Film night', 'cs' => 'Filmový večer', 'es' => 'Noche de cine',
            'hu' => 'Filmest', 'lv' => 'Filmu vakars', 'nl' => 'Filmavond', 'pl' => 'Wieczór filmowy', 'pt' => 'Noite de cinema',
        ]],
        ['icon' => 'users', 'featured' => false, 'name' => [
            'de' => 'Diskussionsrunde', 'en' => 'Panel discussion', 'cs' => 'Diskuse', 'es' => 'Mesa redonda',
            'hu' => 'Kerekasztal', 'lv' => 'Diskusija', 'nl' => 'Paneldiscussie', 'pl' => 'Dyskusja panelowa', 'pt' => 'Mesa redonda',
        ]],

        // Audience
        ['icon' => 'seedling', 'featured' => true, 'name' => [
            'de' => 'Einsteiger', 'en' => 'Beginners', 'cs' => 'Začátečníci', 'es' => 'Principiantes',
            'hu' => 'Kezdők', 'lv' => 'Iesācēji', 'nl' => 'Beginners', 'pl' => 'Początkujący', 'pt' => 'Iniciantes',
        ]],
        ['icon' => 'graduation-cap', 'featured' => false, 'name' => [
            'de' => 'Fortgeschrittene', 'en' => 'Advanced', 'cs' => 'Pokročilí', 'es' => 'Avanzado',
            'hu' => 'Haladók', 'lv' => 'Pieredzējušiem', 'nl' => 'Gevorderden', 'pl' => 'Zaawansowani', 'pt' => 'Avançado',
        ]],
        ['icon' => 'child', 'featured' => false, 'name' => [
            'de' => 'Familien', 'en' => 'Families', 'cs' => 'Rodiny', 'es' => 'Familias',
            'hu' => 'Családok', 'lv' => 'Ģimenēm', 'nl' => 'Gezinnen', 'pl' => 'Rodziny', 'pt' => 'Famílias',
        ]],

        // Topics
        ['icon' => 'coin', 'featured' => true, 'name' => 'Bitcoin'],
        ['icon' => 'bolt', 'featured' => true, 'name' => 'Lightning'],
        ['icon' => 'key', 'featured' => true, 'name' => [
            'de' => 'Selbstverwahrung', 'en' => 'Self-custody', 'cs' => 'Vlastní úschova', 'es' => 'Autocustodia',
            'hu' => 'Önőrzés', 'lv' => 'Pašglabāšana', 'nl' => 'Zelfbeheer', 'pl' => 'Samodzielne przechowywanie', 'pt' => 'Auto-custódia',
        ]],
        ['icon' => 'user-secret', 'featured' => false, 'name' => [
            'de' => 'Privatsphäre', 'en' => 'Privacy', 'cs' => 'Soukromí', 'es' => 'Privacidad',
            'hu' => 'Magánélet', 'lv' => 'Privātums', 'nl' => 'Privacy', 'pl' => 'Prywatność', 'pt' => 'Privacidade',
        ]],
        ['icon' => 'server', 'featured' => false, 'name' => [
            'de' => 'Mining', 'en' => 'Mining', 'cs' => 'Těžba', 'es' => 'Minería',
            'hu' => 'Bányászat', 'lv' => 'Ieguve', 'nl' => 'Mining', 'pl' => 'Kopanie', 'pt' => 'Mineração',
        ]],
        ['icon' => 'tag', 'featured' => false, 'name' => 'Nostr'],
        ['icon' => 'store', 'featured' => false, 'name' => [
            'de' => 'Annahmestellen', 'en' => 'Merchant adoption', 'cs' => 'Obchodníci', 'es' => 'Comercios',
            'hu' => 'Elfogadóhelyek', 'lv' => 'Tirgotāji', 'nl' => 'Acceptanten', 'pl' => 'Akceptanci', 'pt' => 'Comerciantes',
        ]],
    ],

    /*
     * Course vocabulary — one tag, kept from live (18 + 3 uses, was duplicated).
     */
    'course' => [
        ['icon' => 'coin', 'featured' => false, 'name' => 'Bitcoin'],
    ],

    /*
     * Library vocabulary — curated from the live set.
     */
    'library_item' => [
        // Media formats
        ['icon' => 'tag', 'name' => [
            'de' => 'News', 'en' => 'News', 'cs' => 'Novinky', 'es' => 'Noticias',
            'hu' => 'Hírek', 'lv' => 'Ziņas', 'nl' => 'Nieuws', 'pl' => 'Wiadomości', 'pt' => 'Notícias',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Artikel', 'en' => 'Article', 'cs' => 'Článek', 'es' => 'Artículo',
            'hu' => 'Cikk', 'lv' => 'Raksts', 'nl' => 'Artikel', 'pl' => 'Artykuł', 'pt' => 'Artigo',
        ]],
        ['icon' => 'microphone-stand', 'name' => [
            'de' => 'Interview', 'en' => 'Interview', 'cs' => 'Rozhovor', 'es' => 'Entrevista',
            'hu' => 'Interjú', 'lv' => 'Intervija', 'nl' => 'Interview', 'pl' => 'Wywiad', 'pt' => 'Entrevista',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Videos', 'en' => 'Videos', 'cs' => 'Videa', 'es' => 'Vídeos',
            'hu' => 'Videók', 'lv' => 'Video', 'nl' => "Video's", 'pl' => 'Wideo', 'pt' => 'Vídeos',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Musik', 'en' => 'Music', 'cs' => 'Hudba', 'es' => 'Música',
            'hu' => 'Zene', 'lv' => 'Mūzika', 'nl' => 'Muziek', 'pl' => 'Muzyka', 'pt' => 'Música',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Liedtext', 'en' => 'Lyrics', 'cs' => 'Text písně', 'es' => 'Letra',
            'hu' => 'Dalszöveg', 'lv' => 'Dziesmas vārdi', 'nl' => 'Songtekst', 'pl' => 'Tekst piosenki', 'pt' => 'Letra',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Meinung', 'en' => 'Opinion', 'cs' => 'Názor', 'es' => 'Opinión',
            'hu' => 'Vélemény', 'lv' => 'Viedoklis', 'nl' => 'Opinie', 'pl' => 'Opinia', 'pt' => 'Opinião',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Zusammenstellung', 'en' => 'Compilation', 'cs' => 'Sbírka', 'es' => 'Recopilación',
            'hu' => 'Összeállítás', 'lv' => 'Apkopojums', 'nl' => 'Compilatie', 'pl' => 'Zestawienie', 'pt' => 'Compilação',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Übersetzung', 'en' => 'Translation', 'cs' => 'Překlad', 'es' => 'Traducción',
            'hu' => 'Fordítás', 'lv' => 'Tulkojums', 'nl' => 'Vertaling', 'pl' => 'Tłumaczenie', 'pt' => 'Tradução',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Wochenrückblick', 'en' => 'Weekly review', 'cs' => 'Týdenní přehled', 'es' => 'Resumen semanal',
            'hu' => 'Heti összefoglaló', 'lv' => 'Nedēļas apskats', 'nl' => 'Weekoverzicht', 'pl' => 'Przegląd tygodnia', 'pt' => 'Resumo semanal',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Meme', 'en' => 'Meme', 'cs' => 'Meme', 'es' => 'Meme',
            'hu' => 'Mém', 'lv' => 'Mēms', 'nl' => 'Meme', 'pl' => 'Mem', 'pt' => 'Meme',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Ratgeber', 'en' => 'Guide', 'cs' => 'Průvodce', 'es' => 'Guía',
            'hu' => 'Útmutató', 'lv' => 'Ceļvedis', 'nl' => 'Gids', 'pl' => 'Poradnik', 'pt' => 'Guia',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Glossar', 'en' => 'Glossary', 'cs' => 'Slovníček', 'es' => 'Glosario',
            'hu' => 'Szójegyzék', 'lv' => 'Glosārijs', 'nl' => 'Woordenlijst', 'pl' => 'Słowniczek', 'pt' => 'Glossário',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Abkürzungsverzeichnis', 'en' => 'List of abbreviations', 'cs' => 'Seznam zkratek', 'es' => 'Lista de abreviaturas',
            'hu' => 'Rövidítésjegyzék', 'lv' => 'Saīsinājumu saraksts', 'nl' => 'Afkortingenlijst', 'pl' => 'Wykaz skrótów', 'pt' => 'Lista de abreviaturas',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Literatursammlung', 'en' => 'Reading list', 'cs' => 'Seznam literatury', 'es' => 'Bibliografía',
            'hu' => 'Irodalomjegyzék', 'lv' => 'Literatūras saraksts', 'nl' => 'Literatuurlijst', 'pl' => 'Spis literatury', 'pt' => 'Bibliografia',
        ]],
        ['icon' => 'tag', 'name' => 'Whitepaper'],

        // Audience and education
        ['icon' => 'tag', 'name' => [
            'de' => 'Einsteiger', 'en' => 'Beginners', 'cs' => 'Začátečníci', 'es' => 'Principiantes',
            'hu' => 'Kezdők', 'lv' => 'Iesācēji', 'nl' => 'Beginners', 'pl' => 'Początkujący', 'pt' => 'Iniciantes',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Fortgeschrittene', 'en' => 'Advanced', 'cs' => 'Pokročilí', 'es' => 'Avanzado',
            'hu' => 'Haladók', 'lv' => 'Pieredzējušiem', 'nl' => 'Gevorderden', 'pl' => 'Zaawansowani', 'pt' => 'Avançado',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Bildung', 'en' => 'Education', 'cs' => 'Vzdělávání', 'es' => 'Educación',
            'hu' => 'Oktatás', 'lv' => 'Izglītība', 'nl' => 'Onderwijs', 'pl' => 'Edukacja', 'pt' => 'Educação',
        ]],
        ['icon' => 'chalkboard-user', 'name' => [
            'de' => 'Dozentenmaterial', 'en' => 'Lecturer material', 'cs' => 'Materiály pro lektory', 'es' => 'Material para docentes',
            'hu' => 'Oktatói anyag', 'lv' => 'Pasniedzēju materiāli', 'nl' => 'Docentmateriaal', 'pl' => 'Materiały dla wykładowców', 'pt' => 'Material para docentes',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Schulung', 'en' => 'Training', 'cs' => 'Školení', 'es' => 'Formación',
            'hu' => 'Képzés', 'lv' => 'Apmācība', 'nl' => 'Training', 'pl' => 'Szkolenie', 'pt' => 'Formação',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Material für Studenten', 'en' => 'Student material', 'cs' => 'Materiály pro studenty', 'es' => 'Material para estudiantes',
            'hu' => 'Hallgatói anyag', 'lv' => 'Studentu materiāli', 'nl' => 'Studentenmateriaal', 'pl' => 'Materiały dla studentów', 'pt' => 'Material para estudantes',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Abschlussarbeit', 'en' => 'Thesis', 'cs' => 'Závěrečná práce', 'es' => 'Trabajo de fin de carrera',
            'hu' => 'Szakdolgozat', 'lv' => 'Noslēguma darbs', 'nl' => 'Scriptie', 'pl' => 'Praca dyplomowa', 'pt' => 'Trabalho de conclusão',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Kinder', 'en' => 'Children', 'cs' => 'Děti', 'es' => 'Niños',
            'hu' => 'Gyerekek', 'lv' => 'Bērni', 'nl' => 'Kinderen', 'pl' => 'Dzieci', 'pt' => 'Crianças',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Kostenlos', 'en' => 'Free of charge', 'cs' => 'Zdarma', 'es' => 'Gratuito',
            'hu' => 'Ingyenes', 'lv' => 'Bez maksas', 'nl' => 'Gratis', 'pl' => 'Bezpłatne', 'pt' => 'Gratuito',
        ]],

        // Bitcoin topics
        ['icon' => 'coin', 'name' => [
            'de' => 'Allgemein Bitcoin', 'en' => 'Bitcoin general', 'cs' => 'Bitcoin obecně', 'es' => 'Bitcoin general',
            'hu' => 'Bitcoin általában', 'lv' => 'Bitcoin vispārīgi', 'nl' => 'Bitcoin algemeen', 'pl' => 'Bitcoin ogólnie', 'pt' => 'Bitcoin geral',
        ]],
        ['icon' => 'tag', 'name' => 'Lightning'],
        ['icon' => 'tag', 'name' => 'Nostr'],
        ['icon' => 'tag', 'name' => 'OrangePill'],
        ['icon' => 'tag', 'name' => 'V4V'],
        ['icon' => 'tag', 'name' => 'Hyperbitcoinization'],
        ['icon' => 'tag', 'name' => 'Bindle'],
        ['icon' => 'tag', 'name' => 'GiGi'],
        ['icon' => 'tag', 'name' => 'PlebRap'],
        ['icon' => 'tag', 'name' => '21magazin'],
        ['icon' => 'tag', 'name' => 'BitcoinTravelChannel'],
        ['icon' => 'tag', 'name' => 'Shitcoins'],
        ['icon' => 'tag', 'name' => 'brrrrrrr'],
        ['icon' => 'tag', 'name' => [
            'de' => 'Eid der Maschinen', 'en' => 'Oath of the machines', 'cs' => 'Přísaha strojů', 'es' => 'Juramento de las máquinas',
            'hu' => 'A gépek esküje', 'lv' => 'Mašīnu zvērests', 'nl' => 'Eed van de machines', 'pl' => 'Przysięga maszyn', 'pt' => 'Juramento das máquinas',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Technik', 'en' => 'Technology', 'cs' => 'Technologie', 'es' => 'Tecnología',
            'hu' => 'Technológia', 'lv' => 'Tehnoloģija', 'nl' => 'Techniek', 'pl' => 'Technologia', 'pt' => 'Tecnologia',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Mining', 'en' => 'Mining', 'cs' => 'Těžba', 'es' => 'Minería',
            'hu' => 'Bányászat', 'lv' => 'Ieguve', 'nl' => 'Mining', 'pl' => 'Kopanie', 'pt' => 'Mineração',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Kryptografie', 'en' => 'Cryptography', 'cs' => 'Kryptografie', 'es' => 'Criptografía',
            'hu' => 'Kriptográfia', 'lv' => 'Kriptogrāfija', 'nl' => 'Cryptografie', 'pl' => 'Kryptografia', 'pt' => 'Criptografia',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Zeitstempel', 'en' => 'Timestamping', 'cs' => 'Časové razítko', 'es' => 'Sellado de tiempo',
            'hu' => 'Időbélyeg', 'lv' => 'Laika zīmogs', 'nl' => 'Tijdstempel', 'pl' => 'Znacznik czasu', 'pt' => 'Carimbo de tempo',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Notariat', 'en' => 'Notarisation', 'cs' => 'Notářství', 'es' => 'Notaría',
            'hu' => 'Közjegyzőség', 'lv' => 'Notariāts', 'nl' => 'Notariaat', 'pl' => 'Notariat', 'pt' => 'Notariado',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Angriffe', 'en' => 'Attacks', 'cs' => 'Útoky', 'es' => 'Ataques',
            'hu' => 'Támadások', 'lv' => 'Uzbrukumi', 'nl' => 'Aanvallen', 'pl' => 'Ataki', 'pt' => 'Ataques',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Digitalisierung', 'en' => 'Digitalisation', 'cs' => 'Digitalizace', 'es' => 'Digitalización',
            'hu' => 'Digitalizáció', 'lv' => 'Digitalizācija', 'nl' => 'Digitalisering', 'pl' => 'Cyfryzacja', 'pt' => 'Digitalização',
        ]],

        // Economy and money
        ['icon' => 'tag', 'name' => [
            'de' => 'Geld', 'en' => 'Money', 'cs' => 'Peníze', 'es' => 'Dinero',
            'hu' => 'Pénz', 'lv' => 'Nauda', 'nl' => 'Geld', 'pl' => 'Pieniądz', 'pt' => 'Dinheiro',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Ökonomie', 'en' => 'Economics', 'cs' => 'Ekonomie', 'es' => 'Economía',
            'hu' => 'Közgazdaságtan', 'lv' => 'Ekonomika', 'nl' => 'Economie', 'pl' => 'Ekonomia', 'pt' => 'Economia',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Wirtschaftswissenschaften', 'en' => 'Economic science', 'cs' => 'Ekonomické vědy', 'es' => 'Ciencias económicas',
            'hu' => 'Gazdaságtudomány', 'lv' => 'Ekonomikas zinātne', 'nl' => 'Economische wetenschappen', 'pl' => 'Nauki ekonomiczne', 'pt' => 'Ciências económicas',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Inflation', 'en' => 'Inflation', 'cs' => 'Inflace', 'es' => 'Inflación',
            'hu' => 'Infláció', 'lv' => 'Inflācija', 'nl' => 'Inflatie', 'pl' => 'Inflacja', 'pt' => 'Inflação',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Deflation', 'en' => 'Deflation', 'cs' => 'Deflace', 'es' => 'Deflación',
            'hu' => 'Defláció', 'lv' => 'Deflācija', 'nl' => 'Deflatie', 'pl' => 'Deflacja', 'pt' => 'Deflação',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Gold', 'en' => 'Gold', 'cs' => 'Zlato', 'es' => 'Oro',
            'hu' => 'Arany', 'lv' => 'Zelts', 'nl' => 'Goud', 'pl' => 'Złoto', 'pt' => 'Ouro',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Geldanlage', 'en' => 'Investment', 'cs' => 'Investice', 'es' => 'Inversión',
            'hu' => 'Befektetés', 'lv' => 'Ieguldījums', 'nl' => 'Belegging', 'pl' => 'Inwestycja', 'pt' => 'Investimento',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Finanzmarkt', 'en' => 'Financial market', 'cs' => 'Finanční trh', 'es' => 'Mercado financiero',
            'hu' => 'Pénzpiac', 'lv' => 'Finanšu tirgus', 'nl' => 'Financiële markt', 'pl' => 'Rynek finansowy', 'pt' => 'Mercado financeiro',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Immobilien', 'en' => 'Real estate', 'cs' => 'Nemovitosti', 'es' => 'Inmuebles',
            'hu' => 'Ingatlan', 'lv' => 'Nekustamais īpašums', 'nl' => 'Vastgoed', 'pl' => 'Nieruchomości', 'pt' => 'Imobiliário',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Steuern', 'en' => 'Taxes', 'cs' => 'Daně', 'es' => 'Impuestos',
            'hu' => 'Adók', 'lv' => 'Nodokļi', 'nl' => 'Belastingen', 'pl' => 'Podatki', 'pt' => 'Impostos',
        ]],
        ['icon' => 'tag', 'name' => 'CBDCs'],
        ['icon' => 'tag', 'name' => 'Remittances'],
        ['icon' => 'tag', 'name' => 'Middle-Income Trap'],

        // Society and philosophy
        ['icon' => 'thought-bubble', 'name' => [
            'de' => 'Philosophie', 'en' => 'Philosophy', 'cs' => 'Filozofie', 'es' => 'Filosofía',
            'hu' => 'Filozófia', 'lv' => 'Filozofija', 'nl' => 'Filosofie', 'pl' => 'Filozofia', 'pt' => 'Filosofia',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Freiheit', 'en' => 'Freedom', 'cs' => 'Svoboda', 'es' => 'Libertad',
            'hu' => 'Szabadság', 'lv' => 'Brīvība', 'nl' => 'Vrijheid', 'pl' => 'Wolność', 'pt' => 'Liberdade',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Eigentum', 'en' => 'Property', 'cs' => 'Vlastnictví', 'es' => 'Propiedad',
            'hu' => 'Tulajdon', 'lv' => 'Īpašums', 'nl' => 'Eigendom', 'pl' => 'Własność', 'pt' => 'Propriedade',
        ]],
        ['icon' => 'user-secret', 'name' => [
            'de' => 'Privatsphäre', 'en' => 'Privacy', 'cs' => 'Soukromí', 'es' => 'Privacidad',
            'hu' => 'Magánélet', 'lv' => 'Privātums', 'nl' => 'Privacy', 'pl' => 'Prywatność', 'pt' => 'Privacidade',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Etatismus', 'en' => 'Statism', 'cs' => 'Etatismus', 'es' => 'Estatismo',
            'hu' => 'Etatizmus', 'lv' => 'Etatisms', 'nl' => 'Etatisme', 'pl' => 'Etatyzm', 'pt' => 'Estatismo',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Demokratie', 'en' => 'Democracy', 'cs' => 'Demokracie', 'es' => 'Democracia',
            'hu' => 'Demokrácia', 'lv' => 'Demokrātija', 'nl' => 'Democratie', 'pl' => 'Demokracja', 'pt' => 'Democracia',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Politik', 'en' => 'Politics', 'cs' => 'Politika', 'es' => 'Política',
            'hu' => 'Politika', 'lv' => 'Politika', 'nl' => 'Politiek', 'pl' => 'Polityka', 'pt' => 'Política',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Umwelt', 'en' => 'Environment', 'cs' => 'Životní prostředí', 'es' => 'Medio ambiente',
            'hu' => 'Környezet', 'lv' => 'Vide', 'nl' => 'Milieu', 'pl' => 'Środowisko', 'pt' => 'Ambiente',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Emotionale Intelligenz', 'en' => 'Emotional intelligence', 'cs' => 'Emoční inteligence', 'es' => 'Inteligencia emocional',
            'hu' => 'Érzelmi intelligencia', 'lv' => 'Emocionālā inteliģence', 'nl' => 'Emotionele intelligentie', 'pl' => 'Inteligencja emocjonalna', 'pt' => 'Inteligência emocional',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Gemeinschaft', 'en' => 'Community', 'cs' => 'Komunita', 'es' => 'Comunidad',
            'hu' => 'Közösség', 'lv' => 'Kopiena', 'nl' => 'Gemeenschap', 'pl' => 'Społeczność', 'pt' => 'Comunidade',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Idee', 'en' => 'Idea', 'cs' => 'Nápad', 'es' => 'Idea',
            'hu' => 'Ötlet', 'lv' => 'Ideja', 'nl' => 'Idee', 'pl' => 'Pomysł', 'pt' => 'Ideia',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Teilmenge', 'en' => 'Subset', 'cs' => 'Podmnožina', 'es' => 'Subconjunto',
            'hu' => 'Részhalmaz', 'lv' => 'Apakškopa', 'nl' => 'Deelverzameling', 'pl' => 'Podzbiór', 'pt' => 'Subconjunto',
        ]],

        // Places and travel
        ['icon' => 'tag', 'name' => [
            'de' => 'Reise', 'en' => 'Travel', 'cs' => 'Cestování', 'es' => 'Viajes',
            'hu' => 'Utazás', 'lv' => 'Ceļojumi', 'nl' => 'Reizen', 'pl' => 'Podróże', 'pt' => 'Viagens',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Welt', 'en' => 'World', 'cs' => 'Svět', 'es' => 'Mundo',
            'hu' => 'Világ', 'lv' => 'Pasaule', 'nl' => 'Wereld', 'pl' => 'Świat', 'pt' => 'Mundo',
        ]],
        ['icon' => 'tag', 'name' => [
            'de' => 'Afrika', 'en' => 'Africa', 'cs' => 'Afrika', 'es' => 'África',
            'hu' => 'Afrika', 'lv' => 'Āfrika', 'nl' => 'Afrika', 'pl' => 'Afryka', 'pt' => 'África',
        ]],
    ],
];
