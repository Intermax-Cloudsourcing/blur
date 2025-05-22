<?php

declare(strict_types=1);

namespace Intermax\Blur;

use Illuminate\Support\ServiceProvider;
use Intermax\Blur\Console\Commands\ObfuscateDatabaseCommand;

class BlurServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/blur.php' => config_path('blur.php'),
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ObfuscateDatabaseCommand::class,
            ]);
        }
    }
}