<?php

namespace App\Models;

use App\Http\Controllers\Api\BtcMapCommunityController;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use ParagonIE\CipherSweet\BlindIndex;
use ParagonIE\CipherSweet\EncryptedRow;
use ParagonIE\CipherSweet\JsonFieldMap;
use Spatie\LaravelCipherSweet\Concerns\UsesCipherSweet;
use Spatie\LaravelCipherSweet\Contracts\CipherSweetEncrypted;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements CipherSweetEncrypted
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use Notifiable;
    use UsesCipherSweet;

    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'remember_token',
        'profile_photo_path',
        'public_key',
        'is_lecturer',
        'is_leader',
        'current_team_id',
        'timezone',
        'lightning_address',
        'lnurl',
        'node_id',
        'paynym',
        'nostr',
        'lnbits',
        'change',
        'change_time',
        'lightning_retired_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'meetup_privacy_hint_dismissed_at' => 'datetime',
        'lightning_retired_at' => 'datetime',
    ];

    /**
     * Rolle mit uneingeschränkter Vollmacht über alles (MCP-SuperAdmin-Tools inklusive).
     */
    public const ROLE_SUPER_ADMIN = 'super-admin';

    /**
     * Rolle, die einen Nutzer zum Verwalter ALLER Meetups macht, ohne Ersteller
     * oder Leader des einzelnen Meetups zu sein. Sie schaltet die update- und
     * manageLeaders-Ability frei (MeetupPolicy) und damit alles, was daran
     * hängt: Stammdaten, Logo, RSVP-Einstellungen, Teilnehmerlisten, das
     * Anlegen und Bearbeiten von Terminen im Portal — plattformweit. Das ist
     * die Vollmacht eines Erstellers, nur eben für jedes Meetup.
     *
     * Was sie bewusst NICHT tut: sie schreibt keine meetup_user-Pivot und setzt
     * kein created_by um. „Meine Meetups" (Meetup::scopeAssociatedWith(),
     * User::meetups()) bleibt dadurch klein, und der Entzug der Rolle entzieht
     * die Rechte wirklich — es bleibt keine Pivot-Zeile zurück. Damit das hält,
     * verbietet MeetupPolicy::appointLeader() die Selbstbeförderung.
     */
    public const ROLE_MEETUP_STEWARD = 'meetup-steward';

    /**
     * Rolle, die einen Nutzer zum Verwalter der IDENTITAET jeder Stadt macht, ohne ihr
     * Ersteller zu sein. Sie schaltet CityPolicy::updateIdentity() frei und damit die
     * fuenf Felder, die eine Stadt zu dieser Stadt machen: Name, Land, Region,
     * Einwohnerzahl und deren Stichjahr.
     *
     * Was sie NICHT tut: das Anreichern freischalten. Das darf seit Issue #30 jeder
     * angemeldete Nutzer ohnehin (CityPolicy::update()), und dafuer braucht es keine
     * Rolle. Sie schreibt auch kein `created_by` um — der Entzug der Rolle entzieht
     * die Rechte damit wirklich, und „Meine Staedte" bleibt klein.
     *
     * Warum ausgerechnet diese fuenf Felder geschuetzt sind: `name` ist global
     * eindeutig und traegt den (eingefrorenen) Slug, `country_id` und `region_id`
     * verorten die Stadt, und `population` plus `population_date` entscheiden zusammen
     * mit `simplified_geojson` darueber, ob die Meetups dieser Stadt im BTC-Map-Export
     * erscheinen ({@see BtcMapCommunityController}). Ein
     * geleertes Stichjahr laesst fremde Meetups aus einem Drittsystem verschwinden —
     * das ist keine Anreicherung mehr.
     */
    public const ROLE_CITY_STEWARD = 'city-steward';

    /**
     * Darf dieser Nutzer jedes Meetup verwalten, ohne dessen Ersteller oder
     * Leader zu sein? Maßgeblich für MeetupPolicy::update()/manageLeaders().
     */
    public function managesAllMeetups(): bool
    {
        return $this->hasAnyRole([self::ROLE_MEETUP_STEWARD, self::ROLE_SUPER_ADMIN]);
    }

    /**
     * Darf dieser Nutzer die Identitaetsfelder jeder Stadt aendern, ohne ihr Ersteller
     * zu sein? Massgeblich fuer CityPolicy::updateIdentity().
     */
    public function managesAllCities(): bool
    {
        return $this->hasAnyRole([self::ROLE_CITY_STEWARD, self::ROLE_SUPER_ADMIN]);
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    public static function configureCipherSweet(EncryptedRow $encryptedRow): void
    {
        $map = (new JsonFieldMap)
            ->addTextField('url')
            ->addTextField('read_key')
            ->addTextField('wallet_id');

        $encryptedRow
            ->addOptionalTextField('public_key')
            ->addOptionalTextField('lightning_address')
            ->addOptionalTextField('lnurl')
            ->addOptionalTextField('node_id')
            ->addOptionalTextField('email')
            ->addOptionalTextField('paynym')
            ->addNullableJsonField('lnbits', $map, strict: false)
            ->addBlindIndex('public_key', new BlindIndex('public_key_index'))
            ->addBlindIndex('lightning_address', new BlindIndex('lightning_address_index'))
            ->addBlindIndex('lnurl', new BlindIndex('lnurl_index'))
            ->addBlindIndex('node_id', new BlindIndex('node_id_index'))
            ->addBlindIndex('paynym', new BlindIndex('paynym_index'))
            ->addBlindIndex('email', new BlindIndex('email_index'));
    }

    public function meetups()
    {
        return $this->belongsToMany(Meetup::class)->withPivot('is_leader');
    }

    public function votes()
    {
        return $this->hasMany(Vote::class);
    }

    public function updateProfilePhoto(UploadedFile $photo)
    {
        tap($this->profile_photo_path, function ($previous) use ($photo) {
            $this->forceFill([
                'profile_photo_path' => $photo->storePublicly(
                    'profile-photos', ['disk' => $this->profilePhotoDisk()]
                ),
            ])->save();

            if ($previous) {
                Storage::disk($this->profilePhotoDisk())->delete($previous);
            }
        });
    }

    /**
     * Delete the user's profile photo.
     *
     * @return void
     */
    public function deleteProfilePhoto()
    {
        if (is_null($this->profile_photo_path)) {
            return;
        }

        Storage::disk($this->profilePhotoDisk())->delete($this->profile_photo_path);

        $this->forceFill([
            'profile_photo_path' => null,
        ])->save();
    }

    /**
     * Get the URL to the user's profile photo.
     *
     * @return string
     */
    public function getProfilePhotoUrlAttribute()
    {
        return $this->profile_photo_path
            ? Storage::disk($this->profilePhotoDisk())->url($this->profile_photo_path)
            : $this->defaultProfilePhotoUrl();
    }

    /**
     * Get the default profile photo URL if no profile photo has been uploaded.
     *
     * @return string
     */
    protected function defaultProfilePhotoUrl()
    {
        $name = trim(collect(explode(' ', $this->name))->map(function ($segment) {
            return mb_substr($segment, 0, 1);
        })->join(' '));

        return 'https://ui-avatars.com/api/?name='.urlencode($name).'&color=7F9CF5&background=EBF4FF';
    }

    /**
     * Get the disk that profile photos should be stored on.
     *
     * @return string
     */
    protected function profilePhotoDisk()
    {
        return isset($_ENV['VAPOR_ARTIFACT_NAME']) ? 's3' : config('jetstream.profile_photo_disk', 'public');
    }
}
