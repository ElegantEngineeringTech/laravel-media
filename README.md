# A flexible media library for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/finller/laravel-media.svg?style=flat-square)](https://packagist.org/packages/finller/laravel-media)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/finller/laravel-media/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/finller/laravel-media/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/finller/laravel-media/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/finller/laravel-media/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/finller/laravel-media.svg?style=flat-square)](https://packagist.org/packages/finller/laravel-media)

This package provide an extremly flexible media library, allowing you to store any files with their conversions (nested conversions are supported).
It is designed to be usable with local upload/conversions and with cloud upload/conversions solutions like Bunny.net, AWS S3/MediaConvert, Transloadit, ...

It takes its inspiration from the wonderful `spatie/laravel-media-library` package (check spatie packages, they are really great),but it's not a fork.
The migration from `spatie/laravel-media-library` is possible but not that easy if you want to keep your conversions files.

## Installation

You can install the package via composer:

```bash
composer require finller/laravel-media
```

You have to publish and run the migrations with:

```bash
php artisan vendor:publish --tag="laravel-media-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-media-config"
```

This is the contents of the published config file:

```php
// config for Finller/Media

use Finller\Media\Jobs\DeleteModelMediaJob;
use Finller\Media\Models\Media;

return [
    /**
     * The media model
     */
    'model' => Media::class,

    /**
     * The default disk used to store files
     */
    'disk' => env('MEDIA_DISK', env('FILESYSTEM_DISK', 'local')),

    /**
     * Control if media should be deleted with the model
     * when using the HasMedia Trait
     */
    'delete_media_with_model' => true,

    /**
     * Control if media should be deleted with the model
     * when soft deleted
     */
    'delete_media_with_trashed_model' => false,

    /**
     * Deleting a lot of media related to a model can take some time
     * or even fail (cloud api error, permissions, ...)
     * For performance and monitoring, when a model with HasMedia trait is deleted,
     * each media is individually deleted inside a job.
     */
    'delete_media_with_model_job' => DeleteModelMediaJob::class,

    /**
     * The default collection name
     */
    'default_collection_name' => 'default',

    /**
     * Prefix the generate path of files
     * set to null if you don't want any prefix
     * To fully customize the generated default path, extends the Media model ans override generateBasePath method
     */
    'generated_path_prefix' => null,

    /**
     * Customize the queue connection used when dispatching conversion jobs
     */
    'queue_connection' => env('QUEUE_CONNECTION', 'sync'),

    /**
     * Customize the queue used when dispatching conversion jobs
     * null will fallback to the default laravel queue
     */
    'queue' => null,

    /**
     * Customize WithoutOverlapping middleware settings
     */
    'queue_overlapping' => [
        /**
         * Release value must be longer than the longest conversion job that might run
         * Default is: 1 minute, increase it if you jobs are longer
         */
        'release_after' => 60,
        /**
         * Expire value allow to forget a lock in case of the job failed in a unexpected way
         *
         * @see https://laravel.com/docs/10.x/queues#preventing-job-overlaps
         */
        'expire_after' => 60 * 60,
    ],

];
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="laravel-media-views"
```

## Introduction to the concept

There are 2 important concepts to understand, both are tied to the Model associated with its media:

-   **Media Collection:** Define a group of media with its own settings (the group can only have 1 media).
    For exemple: avatar, thumbnail, upload, ... are media collections.
-   **Media Conversion:** Define a file conversion of a media.
    For exemple: A 720p version of a larger 1440p video, a webp conversion or a png image, ... Are media conversion.
    A Media conversion can have media conversions too!

## Preparing your models

This package is designed to associate media to a model but can also be used without model association.

### Registering your media collections

First you need to add the `HasMedia` trait to your Model:

```php
namespace App\Models;

use Finller\Media\Traits\HasMedia;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasMedia;
}
```

Then you can define your Media collection and Media conversion like in this exemple:

```php
namespace App\Models;

use Finller\Media\Traits\HasMedia;
use Finller\Media\MediaCollection;
use Finller\Media\Enums\MediaType;
use Finller\Media\Support\ResponsiveImagesConversionsPreset;
use Finller\Media\Support\VideoPosterConversionPreset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Post extends Model
{
    use HasMedia;

    public function registerMediaCollections(): Collection
    {
        $collections = collect()
            ->push(new MediaCollection(
                name: 'files',
            ))
            ->push(new MediaCollection(
                name: 'videos',
                disk: 's3',
                acceptedMimeTypes: [
                    'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime',
                    'video/x-m4v',
                ],
            ))
            ->push(new MediaCollection(
                name: 'thumbnail',
                single: true,
                fallback: asset('fallback-image.jpg'),
                acceptedMimeTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            ));

        return $collections;
    }
}
```

### Registering your media conversions

Media conversions are run through Laravel Jobs, you can do anything in the job as long as:

-   Your job extends `\Finller\Media\Jobs\ConversionJob`.
-   Your job define a `run` method.
-   Your job call `$this->media->storeConversion(...)`.

Let's take a look at a common media conversion task: Optimizing an image.

> [!NOTE]
> The following job is already provided by this package, but it's a great introduction to the concept

```php
namespace App\Jobs\Media;

use Finller\Media\Jobs\ConversionJob;
use Finller\Media\Models\Media;
use Illuminate\Support\Facades\File;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Spatie\ImageOptimizer\OptimizerChain;

class OptimizedImageConversionJob extends ConversionJob
{
    public string $fileName;

    public function __construct(
        public Media $media,
        public string $conversion,
        public ?int $width = null,
        public ?int $height = null,
        public Fit $fit = Fit::Contain,
        public ?OptimizerChain $optimizerChain = null,
        ?string $fileName = null,
    ) {
        parent::__construct($media, $conversion);

        $this->fileName = $fileName ?? $this->media->file_name;
    }

    public function run()
    {
        // ConversionJob provide a temporary disk that will be automatically deleted at the end
        $temporaryDisk = $this->getTemporaryDisk();

        // This method will make a local copy of your file inside the temporary disk
        $path = $this->makeTemporaryFileCopy();

        $newPath = $temporaryDisk->path($this->fileName);

        Image::load($path)
            ->fit($this->fit, $this->width, $this->height)
            ->optimize($this->optimizerChain)
            ->save($newPath);


        // be sure to save your conversion
        $this->media->storeConversion(
            file: $newPath,
            conversion: $this->conversion,
            name: File::name($this->fileName)
        );
    }
}

```

This media conversion Job can now be registered in you Model like that:

```php
namespace App\Models;

use Finller\Media\Traits\HasMedia;
use Finller\Media\MediaCollection;
use Finller\Media\MediaConversion;
use Finller\Media\Enums\MediaType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Image\Enums\Fit;
use \App\Jobs\Media\OptimizedImageConversionJob;
use Finller\Media\Models\Media;

class Post extends Model
{
    use HasMedia;

    public function registerMediaCollections(): Collection
    {
       // ...
    }

    public function registerMediaConversions(Media $media): Collection
    {
        $conversions = collect();

        if ($media->type === MediaType::Image) {
            return $conversions->push(new MediaConversion(
                name: '720p',
                sync: false, // you can force the job to be run on the sync driver if you need the converion immediatly after saving the media
                job: new OptimizedImageConversionJob(
                    media: $media,
                    conversion: '720p', // its important to define the name here too
                    width: 720,
                    fit : Fit::Contain,
                    fileName: "{$media->name}-720p.jpg"
                )
            ));
        }

        return $conversions;
    }
}
```

#### Common media conversions

This package provide common jobs for your conversions to make your life easier:

-   `VideoPosterConversionJob` will extract a poster using ffmpeg.
-   `OptimizedVideoConversionJob` will optimize, resize or convert any video using ffmpeg.
-   `OptimizedImageConversionJob` will optimize, resize or convert any image using spatie/image.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

-   [Quentin Gabriele](https://github.com/finller)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
