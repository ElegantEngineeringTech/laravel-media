# Flexible Media Library for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ElegantEngineeringTech/laravel-media.svg?style=flat-square)](https://packagist.org/packages/ElegantEngineeringTech/laravel-media)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/ElegantEngineeringTech/laravel-media/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/ElegantEngineeringTech/laravel-media/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/ElegantEngineeringTech/laravel-media/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/ElegantEngineeringTech/laravel-media/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/ElegantEngineeringTech/laravel-media.svg?style=flat-square)](https://packagist.org/packages/ElegantEngineeringTech/laravel-media)

This package offers an extremely flexible media library, enabling you to store any type of file along with their conversions (nested conversions are supported). It is designed to work seamlessly with any filesystem solutions (local or cloud) such as Bunny.net, AWS S3/MediaConvert, Transloadit, among others.

The inspiration for this package is derived from the exceptional `spatie/laravel-media-library` package (be sure to check out Spatie's packages, they are top-notch). However, it is not a fork, as the internal architecture is different, providing you with more capabilities. Migration from `spatie/laravel-media-library` is feasible but may be challenging if you wish to retain your conversion files.

## Motivation

The Spatie team has developed a remarkable package, `spatie/laravel-media-library`, which is well-suited for most common scenarios. However, I found myself constrained by their architecture for my own project. To address this, I required the following features:

-   File transformations
-   Advanced media conversions
-   Nested media conversions

Consequently, I developed this package with the highest degree of flexibility possible. I have been utilizing it in production for nearly a year, handling terabytes of files monthly.

## Full Example

The following example will provide you with a better understanding of the package's capabilities.

We will create a YouTube-like service, with a model named `Channel`. This `Channel` will have two types of media: `avatar` and `videos`. We will define these media types in the `registerMediaCollections` method.

We want to store the avatars in a square format, with dimensions not exceeding 500px, and in the WebP format. We will accomplish this in the `registerMediaTransformations` method.

For each media type, we will need a set of conversions, as illustrated in the following tree:

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

We will define these conversions in the `registerMediaConversions` method.

Here is how our `Channel` class will be defined:

```php
namespace App\Models;

use ElegantEngineeringTech\Media\Traits\HasMedia;
use ElegantEngineeringTech\Media\MediaCollection;
use ElegantEngineeringTech\Media\MediaConversion;
use ElegantEngineeringTech\Media\Enums\MediaType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Image\Enums\Fit;
use \App\Jobs\Media\OptimizedImageConversionJob;
use ElegantEngineeringTech\Media\Models\Media;
use ElegantEngineeringTech\Media\Contracts\InteractWithMedia;
use Illuminate\Contracts\Support\Arrayable;
use ElegantEngineeringTech\Media\Support\ResponsiveImagesConversionsPreset;

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

    public function registerMediaTransformations($media, UploadedFile|File $file): UploadedFile|File
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

From now, we will be able to store files in the easiest way possible:

From a Controller

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ChannelAvatarController extends Controller
{
    function function store(Request $request, Channel $channel)
    {
        $channel->addMedia(
            file: $file->file('avatar'),
            collection_name: 'avatar',
            name: "{$channel->name}-avatar",
        )
    }
}
```

From a Livewire component:

```php
namespace App\Livewire;

use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\Component;

class ImageUploader extends Component
{
    use WithFileUploads;

    function function save()
    {
        /** @var TemporaryUploadedFile */
        $file = $this->avatar;

        $this->channel->addMedia(
            file: $file->getRealPath(),
            collection_name: 'avatar',
            name: "{$channel->name}-avatar",
        )
    }
}
```

## Installation

You can install the package via composer:

```bash
composer require ElegantEngineeringTech/laravel-media
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
use ElegantEngineeringTech\Media\Jobs\DeleteModelMediaJob;
use ElegantEngineeringTech\Media\Models\Media;

return [
    /**
     * The media model
     * Define your own model here by extending \ElegantEngineeringTech\Media\Models\Media::class
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

## Introduction to the Concepts

There are two essential concepts to understand, both of which are associated with the Model and its media:

-   **Media Collection**: This defines a group of media with its specific settings (the group can only contain one media item). For example, avatar, thumbnail, and upload are all media collections.

-   **Media Conversion**: This defines a file conversion of a particular media item. For instance, a 720p version of a larger 1440p video, a WebP or PNG conversion of an image, are all examples of media conversions. Notably, a media conversion can also have its own media conversions.

## Usage

### Preparing Your Models

This package is designed to associate media with a model, but it can also be used without any model association.

#### Registering Media Collections

First, you need to add the `HasMedia` trait and the `InteractWithMedia` interface to your Model:

```php
namespace App\Models;

use ElegantEngineeringTech\Media\Traits\HasMedia;
use Illuminate\Database\Eloquent\Model;
use ElegantEngineeringTech\Media\Contracts\InteractWithMedia;

class Channel extends Model implements InteractWithMedia
{
    use HasMedia;

}
```

You can then define your media collections in the `registerMediaCollections` method:

```php
namespace App\Models;

use ElegantEngineeringTech\Media\Traits\HasMedia;
use ElegantEngineeringTech\Media\MediaCollection;
use Illuminate\Database\Eloquent\Model;
use ElegantEngineeringTech\Media\Contracts\InteractWithMedia;
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

#### Registering Media Conversions

This package provides common jobs for your conversions to simplify your work:

-   `VideoPosterConversionJob`: This job extracts a poster using `pbmedia/laravel-ffmpeg`.
-   `OptimizedVideoConversionJob`: This job optimizes, resizes, or converts any video using `pbmedia/laravel-ffmpeg`.
-   `OptimizedImageConversionJob`: This job optimizes, resizes, or converts any image using `spatie/image`.
-   `ResponsiveImagesConversionsPreset`: This preset creates a set of optimized images of different sizes.
-   `ResponsiveVideosConversionsPreset`: This preset creates a set of optimized videos of different sizes.

```php
namespace App\Models;

use ElegantEngineeringTech\Media\Traits\HasMedia;
use ElegantEngineeringTech\Media\MediaCollection;
use ElegantEngineeringTech\Media\MediaConversion;
use ElegantEngineeringTech\Media\Enums\MediaType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Image\Enums\Fit;
use \App\Jobs\Media\OptimizedImageConversionJob;
use ElegantEngineeringTech\Media\Models\Media;
use ElegantEngineeringTech\Media\Contracts\InteractWithMedia;
use Illuminate\Contracts\Support\Arrayable;
use ElegantEngineeringTech\Media\Support\ResponsiveImagesConversionsPreset;

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

### Defining Your Own MediaConversion

You can create your own conversion by creating a new class in your app (e.g., `App\Support\MediaConversions`) and extending `MediaConversionJob`.

Media conversions are executed through Laravel Jobs. You can perform any task in the job, provided that:

-   Your job extends `ElegantEngineeringTech\Media\Jobs\MediaConversion`.
-   Your job defines a `run` method.
-   Your job calls `$this->media->storeConversion(...)`.

Let's consider a common media conversion task: optimizing an image. Here's how you could implement it in your app:

> **Note:** The following job is already provided by this package, but it serves as an excellent introduction to the concept.

```php
namespace App\Support\MediaConversions;

use ElegantEngineeringTech\Media\Models\Media;
use Illuminate\Support\Facades\File;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Spatie\ImageOptimizer\OptimizerChain;
use ElegantEngineeringTech\Media\Jobs\MediaConversionJob;

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

## Using Your Own Media Model

You can define your own Media model to use with the library.

First, create your own model class:

```php
namespace App\Models;

use ElegantEngineeringTech\Media\Models\Media as FinllerMedia;

class Media extends FinllerMedia
{
    // ...
}
```

Then, update the `config` file:

```php
use App\Models\Media;

return [

    'model' => Media::class,

    // ...

];
```

The library is typed with generics, so you can use your own Media model seamlessly:

```php
namespace App\Models;

use App\Models\Media;
use ElegantEngineeringTech\Media\Traits\HasMedia;
use ElegantEngineeringTech\Media\Contracts\InteractWithMedia;

/**
 * @implements InteractWithMedia<Media>
 */
class Post extends Model implements InteractWithMedia
{
    /** @use HasMedia<Media> **/
    use HasMedia;

    // ...
}
```

## Testing

```bash
composer test
```

## Changelog

Please see the [CHANGELOG](CHANGELOG.md) for more information on recent changes.

## Contributing

Feel free to open an issue or a discussion.

## Security Vulnerabilities

Please contact [me](https://github.com/QuentinGab) to report security vulnerabilities.

## Credits

-   [Quentin Gabriele](https://github.com/QuentinGab)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see the [License File](LICENSE.md) for more information.
