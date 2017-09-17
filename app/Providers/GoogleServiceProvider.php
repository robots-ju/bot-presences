<?php

namespace App\Providers;

use Google_Client;
use Google_Service_Calendar;
use Illuminate\Support\ServiceProvider;

class GoogleServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->app->singleton(Google_Client::class, function() {
            $client = new Google_Client();
            $client->setAuthConfig(base_path('google-service-account-credentials.json'));
            $client->setApplicationName(config('app.name'));

            return $client;
        });

        $this->app->singleton(Google_Service_Calendar::class, function() {
            $client = app(Google_Client::class);
            $client->addScope(Google_Service_Calendar::CALENDAR);

            return new Google_Service_Calendar($client);
        });
    }
}
