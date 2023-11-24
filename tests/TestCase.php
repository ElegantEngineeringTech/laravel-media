<?php

namespace Finller\LaravelMedia\Tests;

use Finller\LaravelMedia\LaravelMediaServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Finller\\LaravelMedia\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelMediaServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        $migration = include __DIR__.'/../database/migrations/1_create_media_table.php.stub';
        $migration->up();
        $migration = include __DIR__.'/../database/migrations/2_add_columns_to_media_table.php.stub';
        $migration->up();
    }
}
