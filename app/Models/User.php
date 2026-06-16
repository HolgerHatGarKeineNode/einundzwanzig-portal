<?php

namespace App\Models;

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
        'current_language',
        'timezone',
        'lightning_address',
        'lnurl',
        'node_id',
        'paynym',
        'nostr',
        'lnbits',
        'change',
        'change_time',
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
    ];

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

    public function reputations()
    {
        return $this->morphMany('QCod\Gamify\Reputation', 'subject');
    }

    public function votes()
    {
        return $this->hasMany(Vote::class);
    }

    public function paidArticles()
    {
        return $this->belongsToMany(LibraryItem::class, 'library_item_user', 'user_id', 'library_item_id');
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
