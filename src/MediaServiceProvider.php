<?php

namespace ElegantEngineeringTech\Media;

use ElegantEngineeringTech\Media\Commands\GenerateMediaConversionsCommand;
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
            ->hasCommand(GenerateMediaConversionsCommand::class)
            ->hasViews();
    }
}
