<?php

namespace App\Support;

use App\Models\User;

/**
 * Gates the board-only webhook approval screen (Issue #40 — the admin UI over
 * Issue #36's `webhooks.require_approval` gate).
 *
 * Board npubs are hardcoded in config/einundzwanzig.board_members, copied from
 * the einundzwanzig-verein repo's config/einundzwanzig/config.php
 * `current_board` — deliberately NOT VereinMeetupGate::vereinMemberHexes()'s
 * verein.einundzwanzig.space API call, which fails soft to an empty set on a
 * network error and would make this admin gate silently inaccessible to the
 * whole board instead of loudly broken.
 *
 * Hex conversion is delegated to VereinMeetupGate::npubToHex() rather than
 * reimplemented, so both gates agree on what counts as a valid npub.
 *
 * Fail-closed by design, same as TagEditorGate: an npub that cannot be
 * decoded is dropped rather than matched, so a malformed config entry denies
 * access instead of matching something unintended. An empty list therefore
 * denies everyone — never grants everyone.
 */
class BoardGate
{
    /**
     * The configured board npubs.
     *
     * @return array<int, string>
     */
    public static function npubs(): array
    {
        return array_values(array_filter(
            (array) config('einundzwanzig.board_members', []),
            static fn ($npub): bool => is_string($npub) && $npub !== ''
        ));
    }

    /**
     * Whether this user is a board member and may reach the webhook approval
     * admin screen.
     */
    public static function allows(?User $user): bool
    {
        $identity = $user?->nostr;

        if (! is_string($identity) || $identity === '') {
            return false;
        }

        $gate = app(VereinMeetupGate::class);

        $hex = $gate->npubToHex($identity);

        if ($hex === null) {
            return false;
        }

        foreach (self::npubs() as $npub) {
            if ($gate->npubToHex($npub) === $hex) {
                return true;
            }
        }

        return false;
    }
}
