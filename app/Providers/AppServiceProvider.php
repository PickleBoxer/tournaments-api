<?php

declare(strict_types=1);

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[\Override]
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Scramble::afterOpenApiGenerated(function (\Dedoc\Scramble\Support\Generator\OpenApi $openApi): void {
            $openApi->components->securitySchemes = [];
            $openApi->security = [];
        });
    }
}
