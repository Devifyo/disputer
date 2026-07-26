<?php

namespace App\Providers;

use App\Services\Billing\LegacyPlanEventHandler;
use App\Services\Billing\PlusSubscriptionEventHandler;
use App\Services\Billing\StripeWebhookDispatcher;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Stripe webhook: every billing product registers its handler here -
        // the pipeline itself never changes when a product is added.
        $this->app->when(StripeWebhookDispatcher::class)
            ->needs('$handlers')
            ->give(fn ($app) => [
                $app->make(LegacyPlanEventHandler::class),
                $app->make(PlusSubscriptionEventHandler::class),
            ]);
    }

    public function boot(): void
    {
        Event::listen(SocialiteWasCalled::class, [
            \SocialiteProviders\Apple\AppleExtendSocialite::class, 'handle',
        ]);
    }
}
