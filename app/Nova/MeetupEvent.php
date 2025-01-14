<?php

namespace App\Nova;

use App\Notifications\ModelCreatedNotification;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Markdown;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class MeetupEvent extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\MeetupEvent::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'id';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
        'meetup.name',
    ];

    public static $with = [
        'meetup',
        'createdBy',
    ];

    public static function label()
    {
        return __('Meetup Event');
    }

    public static function afterCreate(NovaRequest $request, Model $model)
    {
        \App\Models\User::find(1)
                        ->notify(new ModelCreatedNotification($model, str($request->getRequestUri())
                            ->after('/nova-api/')
                            ->before('?')
                            ->toString()));
    }

    public static function relatableMeetups(NovaRequest $request, $query, Field $field)
    {
        if ($field instanceof BelongsTo) {
            $query->whereIn('meetups.id', $request->user()
                                                  ->meetups()
                                                  ->pluck('id')
                                                  ->toArray());
        }

        return $query;
    }

    public function subtitle()
    {
        return __('Created by: :name', ['name' => $this->createdBy->name]);
    }

    /**
     * Get the fields displayed by the resource.
     */
    public function fields(Request $request): array
    {
        return [
            ID::make()
              ->sortable(),

            DateTime::make(__('Start'), 'start')
                    ->step(CarbonInterval::minutes(15))
                    ->displayUsing(fn ($value) => $value->asDateTime()),

            Text::make(__('Location'), 'location'),

            Markdown::make(__('Description'), 'description')
                ->rules('required', 'string')
                ->hideFromIndex(),

            Text::make('Link')
                ->hideFromIndex()
                ->rules('nullable', 'string')
                ->nullable()
                ->help(__('For example, a link to a location on Google Maps or a link to a website. (not your Telegram group link)')),

            BelongsTo::make('Meetup')
                     ->searchable()
                     ->withSubtitles(),

            BelongsTo::make(__('Created By'), 'createdBy', User::class)
                     ->canSee(function ($request) {
                         return $request->user()
                                        ->hasRole('super-admin');
                     })
                     ->searchable()
                     ->withSubtitles(),

        ];
    }

    /**
     * Get the cards available for the request.
     */
    public function cards(Request $request): array
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     */
    public function filters(Request $request): array
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     */
    public function lenses(Request $request): array
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     */
    public function actions(Request $request): array
    {
        return [];
    }
}
