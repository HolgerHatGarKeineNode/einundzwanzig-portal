<?php

namespace App\Attributes;

use RalphJSmit\Laravel\SEO\Support\SEOData;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
class SeoDataAttribute
{
    public function __construct(
        public ?string $key = null, // e.g., 'meetups_index', 'event_show', etc.
        public ?string $image = null, // image url override
    ) {}

    // Centralized SEO data definitions by key as SEOData instances (lazy initialized)
    private static array $seoDefinitions;

    /**
     * Sprache, Markenname und Länderbild, mit denen die Definitionen gebaut
     * wurden. Ohne diesen Abgleich fror der erste Aufruf alles ein — die
     * Definitionen stecken voller __()-Aufrufe, und ein zweiter Request im
     * selben Prozess bekäme Titel in der Sprache des ersten.
     */
    private static ?string $definitionsSignature = null;

    private static function initDefinitions(): void
    {
        $domainAttributes = get_domain_attributes();
        $domainImage = $domainAttributes['image'];
        $domainAuthor = $domainAttributes['author'];
        $domainTwitter = $domainAttributes['twitter'];
        $domainSiteName = $domainAttributes['siteName'];

        self::$seoDefinitions = [
            'login' => new SEOData(
                title: __('Login - Bitcoin Meetups'),
                description: __('Logge dich ein, um auf dein Bitcoin Meetup Konto zuzugreifen und an der Community teilzunehmen.'),
                author: $domainAuthor,
                image: $domainImage,
                twitter_username: $domainTwitter,
                site_name: $domainSiteName,
            ),
            'dashboard' => new SEOData(
                title: __('Dashboard - Bitcoin Meetups'),
                description: __('Verwalte deine Bitcoin Meetups, Events und Einstellungen in deinem persönlichen Dashboard.'),
                author: $domainAuthor,
                image: $domainImage,
                twitter_username: $domainTwitter,
                site_name: $domainSiteName,
            ),
            'welcome' => new SEOData(
                title: __('Willkommen bei Bitcoin Meetups'),
                description: __('Entdecke die Bitcoin Community in deiner Nähe. Finde lokale Meetups und vernetze dich mit Gleichgesinnten.'),
                author: $domainAuthor,
                image: $domainImage,
                twitter_username: $domainTwitter,
                site_name: $domainSiteName,
            ),
            'follow_the_rabbit' => new SEOData(
                title: __('Follow the Rabbit - Bitcoin Journey'),
                description: __('Starte deine Bitcoin-Reise und entdecke spannende Inhalte rund um Bitcoin und Blockchain.'),
                author: $domainAuthor,
                image: $domainImage,
                twitter_username: $domainTwitter,
                site_name: $domainSiteName,
            ),
            'cities_create' => new SEOData(
                title: __('Neue Stadt hinzufügen - Bitcoin Meetups'),
                description: __('Füge eine neue Stadt hinzu, um Bitcoin Meetups in deiner Region zu organisieren.'),
                author: $domainAuthor,
                image: $domainImage,
                twitter_username: $domainTwitter,
                site_name: $domainSiteName,
            ),
            'cities_edit' => new SEOData(
                title: __('Stadt bearbeiten - Bitcoin Meetups'),
                description: __('Aktualisiere die Informationen für Bitcoin Meetup Standorte in deiner Stadt.'),
                author: $domainAuthor,
                image: $domainImage,
                twitter_username: $domainTwitter,
                site_name: $domainSiteName,
            ),
            'cities_index' => new SEOData(
                title: __('Städteübersicht - Bitcoin Meetups'),
                description: __('Durchsuche alle Städte mit aktiven Bitcoin Meetups und finde Events in deiner Nähe.'),
                author: $domainAuthor,
                image: $domainImage,
                twitter_username: $domainTwitter,
                site_name: $domainSiteName,
            ),
            'courses_create' => new SEOData(
                title: __('Neuen Kurs erstellen - Bitcoin Education'),
                description: __('Erstelle einen neuen Bitcoin-Bildungskurs und teile dein Wissen mit der Community.'),
                author: $domainAuthor,
                image: $domainImage,
                twitter_username: $domainTwitter,
                site_name: $domainSiteName,
            ),
            'courses_edit_events' => new SEOData(
                title: __('Kursevents bearbeiten - Bitcoin Education'),
                description: __('Verwalte die Termine und Details deiner Bitcoin-Bildungsveranstaltungen.'),
                author: $domainAuthor,
                image: $domainImage,
                twitter_username: $domainTwitter,
                site_name: $domainSiteName,
            ),
            'courses_edit' => new SEOData(
                title: __('Kurs bearbeiten - Bitcoin Education'),
                description: __('Aktualisiere die Inhalte und Informationen deines Bitcoin-Bildungskurses.'),
                author: $domainAuthor,
                image: $domainImage,
                twitter_username: $domainTwitter,
                site_name: $domainSiteName,
            ),
            'courses_index' => new SEOData(
                title: __('Bitcoin Kurse - Übersicht'),
                description: __('Entdecke unsere vielfältigen Bitcoin-Bildungsangebote und Workshops.'),
                author: $domainAuthor,
                image: $domainImage,
                twitter_username: $domainTwitter,
                site_name: $domainSiteName,
            ),
            'courses_landingpage' => new SEOData(
                title: __('Bitcoin Bildung & Kurse'),
                description: __('Lerne alles über Bitcoin - von den Grundlagen bis zu fortgeschrittenen Themen.'),
                author: $domainAuthor,
                image: $domainImage,
                twitter_username: $domainTwitter,
                site_name: $domainSiteName,
            ),
            'lecturers_create' => new SEOData(
                title: __('Dozent werden - Bitcoin Education'),
                description: __('Werde Bitcoin-Dozent und teile dein Expertenwissen mit der Community.'),
                author: $domainAuthor,
                image: $domainImage,
                twitter_username: $domainTwitter,
                site_name: $domainSiteName,
            ),
            'lecturers_edit' => new SEOData(
                title: __('Dozentenprofil bearbeiten'),
                description: __('Aktualisiere dein Profil als Bitcoin-Dozent und deine Kursangebote.'),
                author: $domainAuthor,
                image: $domainImage,
                twitter_username: $domainTwitter,
                site_name: $domainSiteName,
            ),
            'lecturers_index' => new SEOData(
                title: __('Bitcoin Dozenten - Übersicht'),
                description: __('Lerne unsere erfahrenen Bitcoin-Dozenten und ihre Expertise kennen.'),
                author: $domainAuthor,
                image: $domainImage,
                twitter_username: $domainTwitter,
                site_name: $domainSiteName,
            ),
            'meetups_create_edit_events' => new SEOData(
                title: __('Bitcoin Meetup Events verwalten'),
                description: __('Erstelle und bearbeite Bitcoin Meetup Events für deine Community.'),
                author: $domainAuthor,
                image: $domainImage,
                twitter_username: $domainTwitter,
                site_name: $domainSiteName,
            ),
            'meetups_edit' => new SEOData(
                title: __('Meetup bearbeiten - Bitcoin Events'),
                description: __('Aktualisiere die Details und Informationen deines Bitcoin Meetups.'),
                author: $domainAuthor,
                image: $domainImage,
                twitter_username: $domainTwitter,
                site_name: $domainSiteName,
            ),
            'meetups_index' => new SEOData(
                title: __('Bitcoin Meetups - Alle Events'),
                description: __('Finde alle aktuellen Bitcoin Meetups und Events in deiner Region.'),
                author: $domainAuthor,
                image: $domainImage,
                twitter_username: $domainTwitter,
                site_name: $domainSiteName,
            ),
            'meetups_landingpage' => new SEOData(
                title: __('Bitcoin Meetups - Community Events'),
                description: __('Entdecke Bitcoin Community Events und vernetze dich mit Gleichgesinnten.'),
                author: $domainAuthor,
                image: $domainImage,
                twitter_username: $domainTwitter,
                site_name: $domainSiteName,
            ),
            'meetups_landingpage_event' => new SEOData(
                title: __('Bitcoin Event Details'),
                description: __('Alle Informationen zum ausgewählten Bitcoin Meetup Event.'),
                author: $domainAuthor,
                image: $domainImage,
                twitter_username: $domainTwitter,
                site_name: $domainSiteName,
            ),
            'meetups_map' => new SEOData(
                title: __('Bitcoin Meetups Karte'),
                description: __('Finde Bitcoin Meetups in deiner Nähe mit unserer interaktiven Karte.'),
                author: $domainAuthor,
                image: $domainImage,
                twitter_username: $domainTwitter,
                site_name: $domainSiteName,
            ),
            'settings_appearance' => new SEOData(
                title: __('Erscheinungsbild - Einstellungen'),
                description: __('Passe das Erscheinungsbild deines Bitcoin Meetup Profils an.'),
                author: $domainAuthor,
                image: $domainImage,
                twitter_username: $domainTwitter,
                site_name: $domainSiteName,
            ),
            'settings_link_identity' => new SEOData(
                title: __('Konten verbinden - Einstellungen'),
                description: __('Verbinde dein Lightning- und Nostr-Konto, ohne deine Meetup-Leaderships zu verlieren.'),
                author: $domainAuthor,
                image: $domainImage,
                twitter_username: $domainTwitter,
                site_name: $domainSiteName,
            ),
            'settings_api_tokens' => new SEOData(
                title: __('API Tokens - Einstellungen'),
                description: __('Verwalte deine persönlichen Zugriffstokens für den programmatischen API-Zugriff auf dein Bitcoin Meetup Konto.'),
                author: $domainAuthor,
                image: $domainImage,
                twitter_username: $domainTwitter,
                site_name: $domainSiteName,
            ),
            'settings_delete_user_form' => new SEOData(
                title: __('Konto löschen - Bitcoin Meetups'),
                description: __('Informationen zum Löschen deines Bitcoin Meetup Kontos.'),
                author: $domainAuthor,
                image: $domainImage,
                twitter_username: $domainTwitter,
                site_name: $domainSiteName,
            ),
            'settings_password' => new SEOData(
                title: __('Passwort ändern - Bitcoin Meetups'),
                description: __('Ändere dein Passwort für mehr Sicherheit deines Bitcoin Meetup Kontos.'),
                author: $domainAuthor,
                image: $domainImage,
                twitter_username: $domainTwitter,
                site_name: $domainSiteName,
            ),
            'settings_profile' => new SEOData(
                title: __('Profil bearbeiten - Bitcoin Meetups'),
                description: __('Aktualisiere deine persönlichen Informationen und Profileinstellungen.'),
                author: $domainAuthor,
                image: $domainImage,
                twitter_username: $domainTwitter,
                site_name: $domainSiteName,
            ),
            'services_create' => new SEOData(
                title: __('Neuen Service erstellen'),
                description: __('Füge einen neuen Self-Hosted Service zur Bitcoin Community hinzu.'),
                author: $domainAuthor,
                image: $domainImage,
                twitter_username: $domainTwitter,
                site_name: $domainSiteName,
            ),
            'services_edit' => new SEOData(
                title: __('Service bearbeiten'),
                description: __('Aktualisiere die Details deines Self-Hosted Service.'),
                author: $domainAuthor,
                image: $domainImage,
                twitter_username: $domainTwitter,
                site_name: $domainSiteName,
            ),
            'services_index' => new SEOData(
                title: __('Self-Hosted Services - Übersicht'),
                description: __('Entdecke Bitcoin Self-Hosted Services und dezentrale Angebote der Community.'),
                author: $domainAuthor,
                image: $domainImage,
                twitter_username: $domainTwitter,
                site_name: $domainSiteName,
            ),
            'services_landingpage' => new SEOData(
                title: __('Service Details'),
                description: __('Erfahre mehr über diesen Self-Hosted Service aus der Bitcoin Community.'),
                author: $domainAuthor,
                image: $domainImage,
                twitter_username: $domainTwitter,
                site_name: $domainSiteName,
            ),
            'ki_assistent' => new SEOData(
                title: __('EINUNDZWANZIG mit Claude verbinden'),
                description: __('Verwalte deine Meetups, Termine und Kurse ganz einfach per Chat – mit der KI von claude.ai. Ganz ohne Technikwissen.'),
                author: $domainAuthor,
                image: $domainImage,
                twitter_username: $domainTwitter,
                site_name: $domainSiteName,
            ),
            // Add more as needed
            'default' => new SEOData(
                title: __('Willkommen'),
                description: __('Toximalistisches Infotainment für bullische Bitcoiner.'),
                author: $domainAuthor,
                image: $domainImage,
                twitter_username: $domainTwitter,
                site_name: $domainSiteName,
            ),
        ];
    }

    // Static method to get SEO data by key as SEOData instance
    public static function getData(string $key): SEOData
    {
        $signature = implode('|', [
            app()->getLocale(),
            (string) config('app.name'),
            (string) session('lang_country', 'de-DE'),
        ]);

        if (self::$definitionsSignature !== $signature) {
            self::initDefinitions();
            self::$definitionsSignature = $signature;
        }

        return self::$seoDefinitions[$key] ?? self::$seoDefinitions['default'];
    }

    // If direct SEOData is provided, return it; else fetch by key as SEOData
    public function resolve(): SEOData
    {
        if ($this->key) {
            return self::getData($this->key);
        }

        return self::getData('default'); // Fallback
    }
}
