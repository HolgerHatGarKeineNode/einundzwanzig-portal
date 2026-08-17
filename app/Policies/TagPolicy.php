<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;
use App\Support\TagEditorGate;

/**
 * Tag creation is split in two so the Czech tag requirement cannot become a dead end.
 *
 * Editors (see config/einundzwanzig.tag_editors) create tags outright. Everyone else
 * may *suggest* one: it is created unapproved, stays usable on the suggester's own
 * event, and only reaches other people's pickers once an editor approves it. Without
 * that second path a user in a country where tags are mandatory, who finds no fitting
 * tag, could not save their event at all.
 */
class TagPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Tag $tag): bool
    {
        return $tag->isApproved()
            || ($user !== null && $tag->created_by === $user->id);
    }

    /**
     * Create a tag that is immediately live for everyone.
     */
    public function create(User $user): bool
    {
        return TagEditorGate::allows($user);
    }

    /**
     * Propose a tag that stays unapproved until an editor signs it off.
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
     * Editors may edit anything; a suggester may still fix their own tag for as long
     * as nobody has approved it. Once approved it belongs to the taxonomy, not to the
     * person who happened to propose it.
     */
    public function update(User $user, Tag $tag): bool
    {
        if (TagEditorGate::allows($user)) {
            return true;
        }

        return ! $tag->isApproved() && $tag->created_by === $user->id;
    }

    public function delete(User $user, Tag $tag): bool
    {
        if (TagEditorGate::allows($user)) {
            return true;
        }

        return ! $tag->isApproved() && $tag->created_by === $user->id;
    }
}
