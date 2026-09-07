<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;
use App\Support\TagEditorGate;

/**
 * Who may create, see and edit a tag — under both settings of the approval gate.
 *
 * GATE OFF (`einundzwanzig.tags.require_approval` false, the state since issue #143):
 * a tag is public taxonomy from the moment it exists. Any signed-in user creates one
 * outright, everybody sees every tag, and editing or deleting one is reserved for the
 * tag editors.
 *
 * GATE ON: the pre-#143 split, kept verbatim. Creation is split in two so the Czech
 * tag requirement cannot become a dead end — editors (see
 * config/einundzwanzig.tag_editors) create outright, everyone else may *suggest*: the
 * tag is created unapproved, stays usable on the suggester's own event, and only
 * reaches other people's pickers once an editor approves it. Without that second path
 * a user in a country where tags are mandatory, who finds no fitting tag, could not
 * save their event at all.
 *
 * Every read of the flag defaults to TRUE, so a missing config key falls back to the
 * restrictive answer.
 */
class TagPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * With the gate off every tag is visible to everyone, including guests. With it on,
     * an unapproved suggestion is visible to its proposer only.
     */
    public function view(?User $user, Tag $tag): bool
    {
        if (! config('einundzwanzig.tags.require_approval', true)) {
            return true;
        }

        return $tag->isApproved()
            || ($user !== null && $tag->created_by === $user->id);
    }

    /**
     * Create a tag that is immediately live for everyone.
     *
     * With the gate off that is every signed-in user; with it on, only a tag editor.
     */
    public function create(User $user): bool
    {
        if (! config('einundzwanzig.tags.require_approval', true)) {
            return true;
        }

        return TagEditorGate::allows($user);
    }

    /**
     * Propose a tag that stays unapproved until an editor signs it off.
     *
     * Unchanged by the gate: with it off, create() already grants everyone the stronger
     * right, and the picker's fallback branch simply stops being reached.
     */
    public function suggest(User $user): bool
    {
        return true;
    }

    public function approve(User $user, Tag $tag): bool
    {
        return TagEditorGate::allows($user);
    }

    /**
     * Editors may edit anything. Whether anyone else may is exactly what the gate
     * decides, and the two answers are deliberately different.
     *
     * GATE ON: a suggester may still fix their own tag for as long as nobody has
     * approved it. Once approved it belongs to the taxonomy, not to the person who
     * happened to propose it.
     *
     * GATE OFF: editors only. The suggester clause exists to let someone repair a
     * proposal *nobody else can see yet* — with the gate off there is no such private
     * state, so keeping it would hand the proposer of a historic `approved_at = null`
     * tag the right to rename or delete a label other organisers have since attached to
     * their own events. `approved_at` is not backfilled (see the config comment), so
     * those rows persist and the clause would stay live for them. Editors keep full
     * rights through tags.moderation, which is the only screen that calls these two
     * abilities at all.
     */
    public function update(User $user, Tag $tag): bool
    {
        if (TagEditorGate::allows($user)) {
            return true;
        }

        if (! config('einundzwanzig.tags.require_approval', true)) {
            return false;
        }

        return ! $tag->isApproved() && $tag->created_by === $user->id;
    }

    /**
     * Same rule as {@see self::update()} — see there for why the gate changes it.
     */
    public function delete(User $user, Tag $tag): bool
    {
        if (TagEditorGate::allows($user)) {
            return true;
        }

        if (! config('einundzwanzig.tags.require_approval', true)) {
            return false;
        }

        return ! $tag->isApproved() && $tag->created_by === $user->id;
    }
}
