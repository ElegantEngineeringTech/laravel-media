# A flexible media library for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/finller/laravel-media.svg?style=flat-square)](https://packagist.org/packages/finller/laravel-media)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/finller/laravel-media/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/finller/laravel-media/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/finller/laravel-media/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/finller/laravel-media/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/finller/laravel-media.svg?style=flat-square)](https://packagist.org/packages/finller/laravel-media)

This package provide an extremly flexible media library, allowing you to store any files with their conversions (nested conversions are supported).
It is designed to be usable with any filesystem solutions (local or cloud) like Bunny.net, AWS S3/MediaConvert, Transloadit, ...

It takes its inspiration from the wonderful `spatie/laravel-media-library` package (check spatie packages, they are really great),but it's not a fork, the internal architecture is defferent and allow you to do more.
The migration from `spatie/laravel-media-library` is possible but not that easy if you want to keep your conversions files.

## Motivation

The Spatie team already put together a very nice package: `spatie/laravel-media-library`. Their package is great for most common situation, however I found myself limited by their architecture.
For my own project, I needed to support:

-   File transformations
-   Advanced media conversions
-   Nested media conversions

That's why I put together this package in the most flexible way I could. I've been using it in production for almost a year now, moving terbytes of files every months.

## Full Example

The following example will give you a better understanding of what is possible to do with this package.

Let's recreate a Youtube like service. We will have a model called `Channel`, this channel will have two kind of media: `avatar` and `videos`. We will do that in `registerMediaCollections`.

We only want to store avatar in a square format, not larger than 500px and as a webp. We will do that in `registerMediaTransformations`

For each media, we will need conversions described in the following tree:

```php
/avatar
  /avatar-360
/video
  /poster
    /poster-360
    /poster-720
  /360
  /720
  /1080
  /hls
```

We will do that in `registerMediaConversions`.

Our `Channel` class will be defined like that:

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
use Finller\Media\Contracts\InteractWithMedia;
use Illuminate\Contracts\Support\Arrayable;
use Finller\Media\Support\ResponsiveImagesConversionsPreset;

class Channel extends Model implements InteractWithMedia
{
    use HasMedia;

    public function registerMediaCollections(): Arrayable|iterable|null;
    {
       return [
            new MediaCollection(
                name: 'avatar',
                acceptedMimeTypes: [
                    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                ],
            )
            new MediaCollection(
                name: 'videos',
                acceptedMimeTypes: [
                    'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime',
                ],
            )
       ];
    }

    public function registerMediaTransformations($media, UploadedFile|File $file): void
    {
        if($media->collection_name === "avatar"){
            Image::load($file->getRealPath())
                ->fit(Fit::Crop, 500, 500)
                ->optimize()
                ->save();
        }

        return $file;
    }

    public function registerMediaConversions($media): Arrayable|iterable|null;
    {

        if($media->collection_name === 'avatar'){
            return [
                new MediaConversion(
                    conversionName: '360',
                    job: new OptimizedImageConversionJob(
                        media: $media,
                        width: 360,
                        fileName: "{$media->name}-360.jpg"
                    ),
                )
            ]
        }elseif($media->collection_name === 'videos'){
            return [
                new MediaConversion(
                    conversionName: 'poster',
                    job: new VideoPosterConversionJob(
                        media: $media,
                        queue: 'sync' // The conversion will not be queued, you will have access to it immediatly
                        seconds: 1,
                        fileName: "{$media->name}-poster.jpg"
                    ),
                    conversions: function(GeneratedConversion $generatedConversion) use ($media){
                        return ResponsiveImagesConversionsPreset::make(
                            media: $media,
                            generatedConversion: $generatedConversion
                            widths: [360, 720]
                        )
                    }
                ),
                ...ResponsiveVideosConversionsPreset::make(
                    media: $media,
                    widths: [360, 720, 1080],
                )
            ]
        }

        return null;
    }
}
```

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
use Finller\Media\Jobs\DeleteModelMediaJob;
use Finller\Media\Models\Media;

return [
    /**
     * The media model
     * Define your own model here by extending \Finller\Media\Models\Media::class
     */
    'model' => Media::class,

    /**
     * The default disk used for storing files
     */
    'disk' => env('MEDIA_DISK', env('FILESYSTEM_DISK', 'local')),

    /**
     * Determine if media should be deleted with the model
     * when using the HasMedia Trait
     */
    'delete_media_with_model' => true,

    /**
     * Determine if media should be deleted with the model
     * when it is soft deleted
     */
    'delete_media_with_trashed_model' => false,

    /**
     * Deleting a large number of media attached to a model can be time-consuming
     * or even fail (e.g., cloud API error, permissions, etc.)
     * For performance and monitoring, when a model with the HasMedia trait is deleted,
     * each media is individually deleted inside a job.
     */
    'delete_media_with_model_job' => DeleteModelMediaJob::class,

    /**
     * The default collection name
     */
    'default_collection_name' => 'default',

    /**
     * Prefix for the generated path of files
     * Set to null if you do not want any prefix
     * To fully customize the generated default path, extend the Media model and override the generateBasePath method
     */
    'generated_path_prefix' => null,

    /**
     * Customize the queue connection used when dispatching conversion jobs
     */
    'queue_connection' => env('QUEUE_CONNECTION', 'sync'),

    /**
     * Customize the queue used when dispatching conversion jobs
     * null will fall back to the default Laravel queue
     */
    'queue' => null,

    /**
     * Customize WithoutOverlapping middleware settings
     */
    'queue_overlapping' => [
        /**
         * The release value should be longer than the longest conversion job that might run
         * Default is: 1 minute. Increase it if your jobs are longer.
         */
        'release_after' => 60,
        /**
         * The expire value allows you to forget a lock in case of an unexpected job failure
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

## Usage

### Preparing your models

This package is designed to associate media with a model but can also be used without any model association.

#### Registering your media collections

First you need to add the `HasMedia` trait and the `InteractWithMedia` interface to your Model:

```php
namespace App\Models;

use Finller\Media\Traits\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Finller\Media\Contracts\InteractWithMedia;

class Channel extends Model implements InteractWithMedia
{
    use HasMedia;

}
```

Then you can define your Media collection and Media conversion like in this exemple:

```php
namespace App\Models;

use Finller\Media\Traits\HasMedia;
use Finller\Media\MediaCollection;
use Illuminate\Database\Eloquent\Model;
use Finller\Media\Contracts\InteractWithMedia;
use Illuminate\Contracts\Support\Arrayable;

class Channel extends Model implements InteractWithMedia
{
    use HasMedia;

    public function registerMediaCollections(): Arrayable|iterable|null;
    {
       return [
            new MediaCollection(
                name: 'avatar',
                acceptedMimeTypes: [
                    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                ],
            )
            new MediaCollection(
                name: 'videos',
                acceptedMimeTypes: [
                    'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime',
                ],
            )
       ];
    }
}
```

#### Registering your media conversions

This package provides common jobs for your conversions to make your life easier:

-   `VideoPosterConversionJob` will extract a poster using `pbmedia/laravel-ffmpeg`.
-   `OptimizedVideoConversionJob` will optimize, resize or convert any video using `pbmedia/laravel-ffmpeg`.
-   `OptimizedImageConversionJob` will optimize, resize or convert any image using `spatie/image`.
-   `ResponsiveImagesConversionsPreset` will create a set of optimized images of differents sizes
-   `ResponsiveVideosConversionsPreset` will create a set of optimized videos of differents sizes

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
use Finller\Media\Contracts\InteractWithMedia;
use Illuminate\Contracts\Support\Arrayable;
use Finller\Media\Support\ResponsiveImagesConversionsPreset;

class Channel extends Model implements InteractWithMedia
{
    use HasMedia;

    // ...

    public function registerMediaConversions($media): Arrayable|iterable|null;
    {

        if($media->collection_name === 'avatar'){
            return [
                new MediaConversion(
                    conversionName: '360',
                    job: new OptimizedImageConversionJob(
                        media: $media,
                        width: 360,
                        fileName: "{$media->name}-360.jpg"
                    ),
                )
            ]
        }elseif($media->collection_name === 'videos'){
            return [
                new MediaConversion(
                    conversionName: 'poster',
                    job: new VideoPosterConversionJob(
                        media: $media,
                        queue: 'sync' // The conversion will not be queued, you will have access to it immediatly
                        seconds: 1,
                        fileName: "{$media->name}-poster.jpg"
                    ),
                    conversions: function(GeneratedConversion $generatedConversion) use ($media){
                        return ResponsiveImagesConversionsPreset::make(
                            media: $media,
                            generatedConversion: $generatedConversion
                            widths: [360, 720]
                        )
                    }
                ),
                ...ResponsiveVideosConversionsPreset::make(
                    media: $media,
                    widths: [360, 720, 1080],
                )
            ]
        }

        return null;
    }
}
```

### Defining your own MediaConversion

You can create your own conversion, create a new class somewhere in your app (ex: `\App\Support\MediaConversions`) and extend `MediaConversionJob`.

Media conversions are run through Laravel Jobs, you can do anything in the job as long as:

-   Your job extends `\Finller\Media\Jobs\MediaConversion`.
-   Your job define a `run` method.
-   Your job call `$this->media->storeConversion(...)`.

Let's take a look at a common media conversion task: Optimizing an image. Here is how you would implement it in your app:

> [!NOTE]
> The following job is already provided by this package, but it's a great introduction to the concept

```php
namespace App\Support\MediaConversions;

use Finller\Media\Models\Media;
use Illuminate\Support\Facades\File;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Spatie\ImageOptimizer\OptimizerChain;
use Finller\Media\Jobs\MediaConversionJob;

class OptimizedImageConversionJob extends MediaConversionJob
{
    public string $fileName;

    public function __construct(
        public Media $media,
        ?string $queue = null,
        public ?int $width = null,
        public ?int $height = null,
        public Fit $fit = Fit::Contain,
        public ?OptimizerChain $optimizerChain = null,
        ?string $fileName = null,
    ) {
        parent::__construct($media, $queue);

        $this->fileName = $fileName ?? $this->media->file_name;
    }

    public function run(): void
    {
        $temporaryDisk = $this->getTemporaryDisk();
        $path = $this->makeTemporaryFileCopy();

        $newPath = $temporaryDisk->path($this->fileName);

        Image::load($path)
            ->fit($this->fit, $this->width, $this->height)
            ->optimize($this->optimizerChain)
            ->save($newPath);

        $this->media->storeConversion(
            file: $newPath,
            conversion: $this->conversionName,
            name: File::name($this->fileName)
        );
    }
}

```

## Using your own Media model

You can define your own Media model to use with the library.

First create your own model class:

```php
namespace App\Models;

use Finller\Media\Models\Media as FinllerMedia;

class Media extends FinllerMedia
{
    //
}

```

Then update the `config` file:

```php
use App\Models\Media;

return [

    'model' => Media::class,

    // other configs
];
```

The whole library is typed with generics so you can use your own Media flawlessly like that:

```php
namespace App\Models;

use App\Models\Media;

use Finller\Media\Traits\HasMedia;
use Finller\Media\Contracts\InteractWithMedia;

/**
 * @implements InteractWithMedia<Media>
 */
class Post extends Model implements InteractWithMedia
{
    /** @use HasMedia<Media> **/
    use HasMedia;

    //
}
```

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
