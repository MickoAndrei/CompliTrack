<?php

namespace App\Providers;

use App\Mail\PhpMailerTransport;
use App\Models\PersonalAccessToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Reuse Laravel's session guard, including its normal cookie encryption,
        // with a distinct remember-me cookie and login key for each tab context.
        Auth::extend('tab-session', function ($app, $name, array $config) {
            $tab = $app['request']->attributes->get('complitrack.tab');

            return $app['auth']->createSessionDriver($tab ? $name.'_tab_'.$tab : $name, $config);
        });
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        Mail::extend('phpmailer', fn (array $config) => new PhpMailerTransport($config));
    }
}
