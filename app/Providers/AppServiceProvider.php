<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Mail;
use App\Mail\PhpMailerTransport;

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
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        Mail::extend('phpmailer', fn (array $config) => new PhpMailerTransport($config));
    }
}
