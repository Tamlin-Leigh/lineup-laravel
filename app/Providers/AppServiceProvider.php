<?php

namespace App\Providers;

use App\Models\BandProfile;
use App\Models\OrganiserProfile;
use App\Models\User;
use App\Models\VenueProfile;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // `artisan serve` (the Sail dev server) only hands a fixed list of environment variables to the
        // PHP server it starts; docker/php/opcache.ini reads this one.
        ServeCommand::$passthroughVariables[] = 'PHP_OPCACHE_REVALIDATE_FREQ';
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Store short aliases in polymorphic columns (e.g. links.linkable_type) instead of class names.
        Relation::enforceMorphMap([
            'venue_profile' => VenueProfile::class,
            'band_profile' => BandProfile::class,
            'organiser_profile' => OrganiserProfile::class,
            // Notifications are stored against users (notifications.notifiable_type).
            'user' => User::class,
        ]);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
