<?php

namespace Database\Seeders;

use App\Models\BitcoinEvent;
use App\Models\BookCase;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\EmailCampaign;
use App\Models\EmailTexts;
use App\Models\Episode;
use App\Models\Highscore;
use App\Models\Lecturer;
use App\Models\Library;
use App\Models\LibraryItem;
use App\Models\LoginKey;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\OrangePill;
use App\Models\Participant;
use App\Models\Podcast;
use App\Models\ProjectProposal;
use App\Models\Registration;
use App\Models\SelfHostedService;
use App\Models\Tag;
use App\Models\TwitterAccount;
use App\Models\User;
use App\Models\Venue;
use App\Models\Vote;
use Database\Factories\Helpers\NostrHelper;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        App::setLocale('de');

        $this->command->info('Phase 0: Admin-User');
        $admin = User::factory()->create([
            'name' => 'Admin Einundzwanzig',
            'email' => 'admin@einundzwanzig.local',
            'is_lecturer' => true,
        ]);

        $this->command->info('Phase 1: Stamm-Daten (Tier 0)');
        User::factory()->count(20)->create();
        $countries = Country::factory()->count(8)->create();
        Category::factory()->count(6)->create();
        Participant::factory()->count(30)->create();
        Tag::factory()->count(15)->create();
        EmailCampaign::factory()->count(2)->create();
        TwitterAccount::factory()->count(2)->create();

        $users = User::all();

        $this->command->info('Phase 2: Geo + abhängige Tier-1-Daten');
        $cities = City::factory()->count(20)
            ->recycle($countries)
            ->recycle($users)
            ->create();
        $lecturers = Lecturer::factory()->count(15)
            ->recycle($users)
            ->create();
        $bookCases = BookCase::factory()->count(25)
            ->recycle($users)
            ->create();
        SelfHostedService::factory()->count(10)
            ->recycle($users)
            ->create();
        SelfHostedService::factory()->count(2)->anonymous()->create();
        $podcasts = Podcast::factory()->count(3)
            ->recycle($users)
            ->create();

        $this->command->info('Phase 3: Tier-2-Daten');
        $venues = Venue::factory()->count(15)
            ->recycle($cities)
            ->recycle($users)
            ->create();
        $courses = Course::factory()->count(20)
            ->recycle($lecturers)
            ->recycle($users)
            ->create();
        Episode::factory()->count(50)
            ->recycle($podcasts)
            ->recycle($users)
            ->create();
        $libraries = Library::factory()->count(4)
            ->recycle($users)
            ->create();
        $meetups = Meetup::factory()->count(30)
            ->recycle($cities)
            ->recycle($users)
            ->create();

        $this->command->info('Phase 4: Events & Items (Tier-3)');
        BitcoinEvent::factory()->count(15)
            ->recycle($venues)
            ->recycle($users)
            ->create();
        $courseEvents = CourseEvent::factory()->count(30)
            ->recycle($courses)
            ->recycle($venues)
            ->recycle($users)
            ->create();
        MeetupEvent::factory()->count(60)
            ->recycle($meetups)
            ->recycle($users)
            ->create();
        $libraryItems = LibraryItem::factory()->count(50)
            ->recycle($lecturers)
            ->recycle($users)
            ->create();
        OrangePill::withoutEvents(function () use ($users, $bookCases) {
            OrangePill::factory()->count(50)
                ->recycle($users)
                ->recycle($bookCases)
                ->create();
        });
        $proposals = ProjectProposal::factory()->count(8)
            ->recycle($users)
            ->create();
        EmailTexts::factory()->count(10)
            ->recycle(EmailCampaign::all())
            ->create();

        $this->command->info('Phase 5: Pivots');

        $categoryIds = Category::pluck('id');
        Course::all()->each(function (Course $course) use ($categoryIds) {
            $course->categories()->attach(
                $categoryIds->random(min(2, $categoryIds->count()))->all()
            );
        });

        $libraryIds = $libraries->pluck('id');
        $libraryItems->each(function (LibraryItem $item) use ($libraryIds) {
            $item->libraries()->attach(
                $libraryIds->random(min(2, $libraryIds->count()))->all()
            );
        });

        $userIds = $users->pluck('id');
        $meetups->each(function (Meetup $meetup) use ($userIds) {
            foreach ($userIds->random(min(4, $userIds->count()))->all() as $idx => $uid) {
                DB::table('meetup_user')->insertOrIgnore([
                    'meetup_id' => $meetup->id,
                    'user_id' => $uid,
                    'is_leader' => $idx === 0,
                ]);
            }
        });

        $participantIds = Participant::pluck('id');
        $courseEvents->each(function (CourseEvent $event) use ($participantIds) {
            foreach ($participantIds->random(min(4, $participantIds->count()))->all() as $pid) {
                Registration::create([
                    'course_event_id' => $event->id,
                    'participant_id' => $pid,
                    'active' => true,
                ]);
            }
        });

        $libraryItems->take(15)->each(function (LibraryItem $item) use ($users) {
            $item->update(['value_to_be_paid' => 'true', 'sats' => fake()->numberBetween(100, 21000)]);
            foreach ($users->random(min(3, $users->count()))->pluck('id')->all() as $uid) {
                DB::table('library_item_user')->insertOrIgnore([
                    'library_item_id' => $item->id,
                    'user_id' => $uid,
                ]);
            }
        });

        $this->command->info('Phase 6: Voting & Highscores');
        $proposals->each(function (ProjectProposal $proposal) use ($users) {
            foreach ($users->random(min(8, $users->count())) as $voter) {
                Vote::create([
                    'user_id' => $voter->id,
                    'project_proposal_id' => $proposal->id,
                    'value' => fake()->randomElement([0, 1]),
                    'reason' => fake()->boolean(30) ? fake()->sentence() : null,
                    'created_by' => $voter->id,
                ]);
            }
        });

        foreach (NostrHelper::realNpubs() as $i => $npub) {
            for ($d = 0; $d < 5; $d++) {
                Highscore::factory()->create([
                    'npub' => $npub,
                    'achieved_at' => now()->subDays(($i * 10) + $d),
                    'satoshis' => fake()->numberBetween(1000, 1_000_000),
                    'blocks' => fake()->numberBetween(1, 5000),
                ]);
            }
        }

        $this->command->info('Phase 7: LoginKeys');
        LoginKey::factory()->count(5)->recycle($users)->create();

        $this->command->info("Seeding fertig — Admin: {$admin->email} (Passwort: password)");
    }
}
