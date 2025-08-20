<?php

declare(strict_types=1);

namespace Elegantly\Media;

use Elegantly\Media\Commands\GenerateMediaConversionsCommand;
use Elegantly\Media\Commands\RetryMediaConversionsCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class MediaServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-media')
            ->hasConfigFile()
            ->hasMigration('create_media_table')
            ->hasMigration('create_media_conversions_table')
            ->hasMigration('migrate_generated_conversions_to_media_conversions_table')
            ->hasMigration('migrate_state_in_media_conversions_table')
            ->hasCommand(GenerateMediaConversionsCommand::class)
            ->hasCommand(RetryMediaConversionsCommand::class)
            ->hasViews();
    }
}
