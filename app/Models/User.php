<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Sanctum\HasApiTokens;
use ParagonIE\CipherSweet\BlindIndex;
use ParagonIE\CipherSweet\EncryptedRow;
use QCod\Gamify\Gamify;
use Spatie\Comments\Models\Concerns\InteractsWithComments;
use Spatie\Comments\Models\Concerns\Interfaces\CanComment;
use Spatie\LaravelCipherSweet\Concerns\UsesCipherSweet;
use Spatie\LaravelCipherSweet\Contracts\CipherSweetEncrypted;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements CanComment, CipherSweetEncrypted, MustVerifyEmail
{
    use Gamify;
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use HasRoles;
    use HasTeams;
    use InteractsWithComments;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use UsesCipherSweet;

    protected $guarded = [];

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
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }

    public static function configureCipherSweet(EncryptedRow $encryptedRow): void
    {
        $encryptedRow
            ->addField('public_key')
            ->addField('lightning_address')
            ->addField('lnurl')
            ->addField('node_id')
            ->addField('email')
            ->addBlindIndex('public_key', new BlindIndex('public_key_index'))
            ->addBlindIndex('lightning_address', new BlindIndex('lightning_address_index'))
            ->addBlindIndex('lnurl', new BlindIndex('lnurl_index'))
            ->addBlindIndex('node_id', new BlindIndex('node_id_index'))
            ->addBlindIndex('email', new BlindIndex('email_index'));
    }

    public function orangePills()
    {
        return $this->hasMany(OrangePill::class);
    }

    public function meetups()
    {
        return $this->belongsToMany(Meetup::class);
    }

    public function reputations()
    {
        return $this->morphMany('QCod\Gamify\Reputation', 'subject');
    }
}
