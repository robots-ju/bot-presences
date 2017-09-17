<?php

namespace App\Providers;

use App\DiscordApi;
use Illuminate\Support\ServiceProvider;

class DiscordServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->app->singleton(DiscordApi::class, function() {
            return new DiscordApi(config('services.discord.bot_token'));
        });
    }
}
