<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use OpenAI\Client;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Client::class, function () {
            return \OpenAI::factory()
                ->withApiKey(config('services.openai.api_key'))
                ->withBaseUri(config('services.openai.base_url'))
                ->make();
        });
    }

    public function boot(): void
    {
        //
    }
}
