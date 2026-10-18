<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #164: a URL longer than 255 characters could not be saved.
 *
 * Every column below was a `string()`, i.e. VARCHAR(255), and every form and API
 * request in front of it said `max:255` to match. Real links outgrow that: tracking
 * parameters, Luma and Meetup.com share links, and Lightning/LNURL-bearing URLs run
 * well past 255 characters. The validation limit moves to 2048 (the limit the webhook
 * URL already had), and the columns become TEXT so the database can hold what the
 * validation now lets through.
 *
 * `webhook_subscriptions.url` was already validated at `max:2048` while its column
 * stayed VARCHAR(255) — on PostgreSQL or MySQL a 300-character webhook URL passed
 * validation and then failed on insert. It is widened here for the same reason.
 *
 * TEXT and not VARCHAR(2048): `meetups` alone carries two of these columns next to
 * several other strings, and MySQL counts every VARCHAR against its 65 535-byte row
 * limit at four bytes per character under utf8mb4. TEXT is stored off-row and does not.
 *
 * `podcasts.link` and `nova_trix_attachments.url` are left alone: nothing in this
 * application lets a person type into either.
 *
 * `down()` restores VARCHAR(255). On PostgreSQL and on MySQL in strict mode that fails
 * outright while a longer value is stored — it refuses rather than truncating a link.
 */
return new class extends Migration
{
    /**
     * @var array<string, array<string, bool>> table => [column => nullable]
     */
    private const URL_COLUMNS = [
        'meetup_events' => ['link' => true],
        'course_events' => ['link' => false],
        'meetups' => ['telegram_link' => true, 'webpage' => true],
        'lecturers' => ['website' => true],
        'self_hosted_services' => [
            'url_clearnet' => true,
            'url_onion' => true,
            'url_i2p' => true,
            'url_pkdns' => true,
        ],
        'webhook_subscriptions' => ['url' => false],
    ];

    /**
     * The expression index of 2026_08_26_134145 on `lower(name)`. SQLite rebuilds the
     * table on `->change()` and dies re-creating this index — see
     * 2026_09_17_200000_enable_nostr_publishing_by_default_on_meetups_table.
     */
    private const LOWER_NAME_INDEX = 'meetups_lower_name_unique';

    public function up(): void
    {
        $this->changeColumns(toText: true);
    }

    public function down(): void
    {
        $this->changeColumns(toText: false);
    }

    private function changeColumns(bool $toText): void
    {
        foreach (self::URL_COLUMNS as $tableName => $columns) {
            $rebuildsLowerNameIndex = $tableName === 'meetups'
                && DB::getDriverName() === 'sqlite'
                && Schema::hasIndex('meetups', self::LOWER_NAME_INDEX);

            if ($rebuildsLowerNameIndex) {
                DB::statement(sprintf('DROP INDEX %s', self::LOWER_NAME_INDEX));
            }

            Schema::table($tableName, function (Blueprint $table) use ($columns, $toText) {
                foreach ($columns as $column => $nullable) {
                    $definition = $toText ? $table->text($column) : $table->string($column);
                    $definition->nullable($nullable)->change();
                }
            });

            if ($rebuildsLowerNameIndex) {
                DB::statement(sprintf('CREATE UNIQUE INDEX %s ON meetups (LOWER(name))', self::LOWER_NAME_INDEX));
            }
        }
    }
};
