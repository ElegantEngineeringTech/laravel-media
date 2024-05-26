<?php

namespace Elegantly\Media\Tests;

use Elegantly\Media\MediaServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase as Orchestra;
use ProtoneMedia\LaravelFFMpeg\Support\ServiceProvider;

class TestCase extends Orchestra
{
    public $dummy_pdf_url = 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf';

    public $dummy_video_url = 'https://storage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4';

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Elegantly\\Media\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            MediaServiceProvider::class,
            ServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        $migration = include __DIR__.'/../database/migrations/create_media_table.php.stub';
        $migration->up();

        $app['db']->connection()->getSchemaBuilder()->create('tests', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function getTestFile(string $path): string
    {
        return __DIR__.'/files/'.$path;
    }
}
