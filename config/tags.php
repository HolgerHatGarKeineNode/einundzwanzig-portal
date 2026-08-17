<?php

use App\Models\Tag;
use Illuminate\Database\Eloquent\Relations\MorphPivot;

return [

    /*
     * The given function generates a URL friendly "slug" from the tag name property before saving it.
     * Defaults to Str::slug (https://laravel.com/docs/master/helpers#method-str-slug)
     */
    'slugger' => null,

    /*
    |--------------------------------------------------------------------------
    | Tag model
    |--------------------------------------------------------------------------
    |
    | Without this line the package hands back Spatie\Tags\Tag from every relation,
    | even though this application defines App\Models\Tag. The two differ in ways
    | that matter: displayName(), displayLocale(), isApproved(), the featured /
    | approved / pending / selectableBy scopes and the stable-slug override all live
    | on ours. Reading $event->tags would silently return the package model and every
    | one of those calls would fail with BadMethodCallException.
    |
    | The mismatch went unnoticed for years because no code ever read tags through a
    | relation — the trait was declared on four models and used by none of them.
    |
    */

    'tag_model' => Tag::class,

    /*
     * The name of the table associated with the taggable morph relation.
     */
    'taggable' => [
        'table_name' => 'taggables',
        'morph_name' => 'taggable',

        /*
         * The fully qualified class name of the pivot model.
         */
        'class_name' => MorphPivot::class,
    ],
];
