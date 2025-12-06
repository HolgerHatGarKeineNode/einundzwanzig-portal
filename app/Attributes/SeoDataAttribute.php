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

    private static function initDefinitions(): void
    {
        $domainImage = get_domain_image();

        self::$seoDefinitions = [
            'login' => new SEOData(
                title: __('Login - Bitcoin Meetups'),
                description: __('Logge dich ein, um auf dein Bitcoin Meetup Konto zuzugreifen und an der Community teilzunehmen.'),
                image: $domainImage,
            ),
            'dashboard' => new SEOData(
                title: __('Dashboard - Bitcoin Meetups'),
                description: __('Verwalte deine Bitcoin Meetups, Events und Einstellungen in deinem persönlichen Dashboard.'),
                image: $domainImage,
            ),
            'welcome' => new SEOData(
                title: __('Willkommen bei Bitcoin Meetups'),
                description: __('Entdecke die Bitcoin Community in deiner Nähe. Finde lokale Meetups und vernetze dich mit Gleichgesinnten.'),
                image: $domainImage,
            ),
            'follow_the_rabbit' => new SEOData(
                title: __('Follow the Rabbit - Bitcoin Journey'),
                description: __('Starte deine Bitcoin-Reise und entdecke spannende Inhalte rund um Bitcoin und Blockchain.'),
                image: $domainImage,
            ),
            'cities_create' => new SEOData(
                title: __('Neue Stadt hinzufügen - Bitcoin Meetups'),
                description: __('Füge eine neue Stadt hinzu, um Bitcoin Meetups in deiner Region zu organisieren.'),
                image: $domainImage,
            ),
            'cities_edit' => new SEOData(
                title: __('Stadt bearbeiten - Bitcoin Meetups'),
                description: __('Aktualisiere die Informationen für Bitcoin Meetup Standorte in deiner Stadt.'),
                image: $domainImage,
            ),
            'cities_index' => new SEOData(
                title: __('Städteübersicht - Bitcoin Meetups'),
                description: __('Durchsuche alle Städte mit aktiven Bitcoin Meetups und finde Events in deiner Nähe.'),
                image: $domainImage,
            ),
            'courses_create' => new SEOData(
                title: __('Neuen Kurs erstellen - Bitcoin Education'),
                description: __('Erstelle einen neuen Bitcoin-Bildungskurs und teile dein Wissen mit der Community.'),
                image: $domainImage,
            ),
            'courses_edit_events' => new SEOData(
                title: __('Kursevents bearbeiten - Bitcoin Education'),
                description: __('Verwalte die Termine und Details deiner Bitcoin-Bildungsveranstaltungen.'),
                image: $domainImage,
            ),
            'courses_edit' => new SEOData(
                title: __('Kurs bearbeiten - Bitcoin Education'),
                description: __('Aktualisiere die Inhalte und Informationen deines Bitcoin-Bildungskurses.'),
                image: $domainImage,
            ),
            'courses_index' => new SEOData(
                title: __('Bitcoin Kurse - Übersicht'),
                description: __('Entdecke unsere vielfältigen Bitcoin-Bildungsangebote und Workshops.'),
                image: $domainImage,
            ),
            'courses_landingpage' => new SEOData(
                title: __('Bitcoin Bildung & Kurse'),
                description: __('Lerne alles über Bitcoin - von den Grundlagen bis zu fortgeschrittenen Themen.'),
                image: $domainImage,
            ),
            'lecturers_create' => new SEOData(
                title: __('Dozent werden - Bitcoin Education'),
                description: __('Werde Bitcoin-Dozent und teile dein Expertenwissen mit der Community.'),
                image: $domainImage,
            ),
            'lecturers_edit' => new SEOData(
                title: __('Dozentenprofil bearbeiten'),
                description: __('Aktualisiere dein Profil als Bitcoin-Dozent und deine Kursangebote.'),
                image: $domainImage,
            ),
            'lecturers_index' => new SEOData(
                title: __('Bitcoin Dozenten - Übersicht'),
                description: __('Lerne unsere erfahrenen Bitcoin-Dozenten und ihre Expertise kennen.'),
                image: $domainImage,
            ),
            'meetups_create_edit_events' => new SEOData(
                title: __('Bitcoin Meetup Events verwalten'),
                description: __('Erstelle und bearbeite Bitcoin Meetup Events für deine Community.'),
                image: $domainImage,
            ),
            'meetups_edit' => new SEOData(
                title: __('Meetup bearbeiten - Bitcoin Events'),
                description: __('Aktualisiere die Details und Informationen deines Bitcoin Meetups.'),
                image: $domainImage,
            ),
            'meetups_index' => new SEOData(
                title: __('Bitcoin Meetups - Alle Events'),
                description: __('Finde alle aktuellen Bitcoin Meetups und Events in deiner Region.'),
                image: $domainImage,
            ),
            'meetups_landingpage' => new SEOData(
                title: __('Bitcoin Meetups - Community Events'),
                description: __('Entdecke Bitcoin Community Events und vernetze dich mit Gleichgesinnten.'),
                image: $domainImage,
            ),
            'meetups_landingpage_event' => new SEOData(
                title: __('Bitcoin Event Details'),
                description: __('Alle Informationen zum ausgewählten Bitcoin Meetup Event.'),
                image: $domainImage,
            ),
            'meetups_map' => new SEOData(
                title: __('Bitcoin Meetups Karte'),
                description: __('Finde Bitcoin Meetups in deiner Nähe mit unserer interaktiven Karte.'),
                image: $domainImage,
            ),
            'settings_appearance' => new SEOData(
                title: __('Erscheinungsbild - Einstellungen'),
                description: __('Passe das Erscheinungsbild deines Bitcoin Meetup Profils an.'),
                image: $domainImage,
            ),
            'settings_delete_user_form' => new SEOData(
                title: __('Konto löschen - Bitcoin Meetups'),
                description: __('Informationen zum Löschen deines Bitcoin Meetup Kontos.'),
                image: $domainImage,
            ),
            'settings_password' => new SEOData(
                title: __('Passwort ändern - Bitcoin Meetups'),
                description: __('Ändere dein Passwort für mehr Sicherheit deines Bitcoin Meetup Kontos.'),
                image: $domainImage,
            ),
            'settings_profile' => new SEOData(
                title: __('Profil bearbeiten - Bitcoin Meetups'),
                description: __('Aktualisiere deine persönlichen Informationen und Profileinstellungen.'),
                image: $domainImage,
            ),
            'venues_create' => new SEOData(
                title: __('Neuen Veranstaltungsort erstellen'),
                description: __('Füge einen neuen Ort für Bitcoin Meetups und Events hinzu.'),
                image: $domainImage,
            ),
            'venues_edit' => new SEOData(
                title: __('Veranstaltungsort bearbeiten'),
                description: __('Aktualisiere die Details eines Bitcoin Meetup Veranstaltungsortes.'),
                image: $domainImage,
            ),
            'venues_index' => new SEOData(
                title: __('Veranstaltungsorte - Übersicht'),
                description: __('Finde alle Veranstaltungsorte für Bitcoin Meetups und Events.'),
                image: $domainImage,
            ),
            'services_create' => new SEOData(
                title: __('Neuen Service erstellen'),
                description: __('Füge einen neuen Self-Hosted Service zur Bitcoin Community hinzu.'),
                image: $domainImage,
            ),
            'services_edit' => new SEOData(
                title: __('Service bearbeiten'),
                description: __('Aktualisiere die Details deines Self-Hosted Service.'),
                image: $domainImage,
            ),
            'services_index' => new SEOData(
                title: __('Self-Hosted Services - Übersicht'),
                description: __('Entdecke Bitcoin Self-Hosted Services und dezentrale Angebote der Community.'),
                image: $domainImage,
            ),
            'services_landingpage' => new SEOData(
                title: __('Service Details'),
                description: __('Erfahre mehr über diesen Self-Hosted Service aus der Bitcoin Community.'),
                image: $domainImage,
            ),
            // Add more as needed
            'default' => new SEOData(
                title: __('Willkommen'),
                description: __('Toximalistisches Infotainment für bullische Bitcoiner.'),
                image: $domainImage,
            ),
        ];
    }

    // Static method to get SEO data by key as SEOData instance
    public static function getData(string $key): SEOData
    {
        if (empty(self::$seoDefinitions)) {
            self::initDefinitions();
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
