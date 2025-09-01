<?php

namespace App\Providers;

use App\Models\BitcoinEvent;
use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\LibraryItem;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\OrangePill;
use App\Observers\BitcoinEventObserver;
use App\Observers\CourseEventObserver;
use App\Observers\CourseObserver;
use App\Observers\LibraryItemObserver;
use App\Observers\MeetupEventObserver;
use App\Observers\MeetupObserver;
use App\Observers\OrangePillObserver;
use App\Support\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Spatie\Translatable\Facades\Translatable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        Date::use(
            Carbon::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(app()->environment('local'));

        Stringable::macro('initials', function () {
            $words = preg_split("/\s+/", $this);
            $initials = '';

            foreach ($words as $w) {
                $initials .= $w[0];
            }

            return new static($initials);
        });
        Str::macro('initials', function (string $string) {
            return (string) (new Stringable($string))->initials();
        });

        Translatable::fallback(
            fallbackAny: true,
        );

        $this->bootEvent();
        $this->bootRoute();
    }

    public function bootEvent(): void
    {
        Meetup::observe(MeetupObserver::class);
        MeetupEvent::observe(MeetupEventObserver::class);
        OrangePill::observe(OrangePillObserver::class);
        CourseEvent::observe(CourseEventObserver::class);
        Course::observe(CourseObserver::class);
        BitcoinEvent::observe(BitcoinEventObserver::class);
        LibraryItem::observe(LibraryItemObserver::class);
    }

    public function bootRoute(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip());
        });


    }
}
