<?php

declare(strict_types=1);

namespace Elegantly\Media\Tests;

use Elegantly\Media\MediaServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;

use function Orchestra\Testbench\artisan;
use function Orchestra\Testbench\workbench_path;

class TestCase extends Orchestra
{
    use WithWorkbench;

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
        ];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('database.default', 'testing');

        if (PHP_OS_FAMILY === 'Darwin') {
            $app['config']->set('media.ffmpeg.ffmpeg_binaries', '/opt/homebrew/bin/ffmpeg');
            $app['config']->set('media.ffprobe.ffprobe_binaries', '/opt/homebrew/bin/ffprobe');
        }

    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(
            workbench_path('database/migrations')
        );

        artisan($this, 'vendor:publish', ['--tag' => 'media-migrations']);

        artisan($this, 'migrate');

        $this->beforeApplicationDestroyed(
            fn () => artisan($this, 'migrate:rollback')
        );
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        Model::shouldBeStrict(true);
    }

    public function getTestFile(string $path): string
    {
        return __DIR__.'/files/'.$path;
    }
}
