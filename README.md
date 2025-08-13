# Extremely powerful media library for Laravel 🖼️

[![Latest Version on Packagist](https://img.shields.io/packagist/v/elegantly/laravel-media.svg?style=flat-square)](https://packagist.org/packages/elegantly/laravel-media)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/ElegantEngineeringTech/laravel-media/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/ElegantEngineeringTech/laravel-media/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/ElegantEngineeringTech/laravel-media/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/ElegantEngineeringTech/laravel-media/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/elegantly/laravel-media.svg?style=flat-square)](https://packagist.org/packages/elegantly/laravel-media)

This package offers an extremely flexible media library, enabling you to store any type of file along with their conversions.

It provides advanced features such as:

-   🌐 Supports any filesystem solutions (local or cloud), such as S3, R2, Bunny.net, DO...
-   ⚡ Supports any file conversion solutions (local or cloud), such as ffmpeg, Transloadit, Cloudflare, Coconut, and others.
-   🔄 Advanced nested media conversions
-   🚀 Rich metadata automatically extracted
-   🛠️ Highly flexible and customizable

I developed this package with the highest degree of flexibility possible and I have been using it in production for nearly two years, handling terabytes of files monthly.

## Table of Contents

1. [Requirements](#requirements)

1. [Installation](#installation)

1. [Basic Usage](#basic-usage)

    - [Define Media Collection](#defining-media-collections)
    - [Define Media Conversions](#defining-media-conversions)
    - [Adding Media](#adding-media)
    - [Retreiving Media](#adding-media)
    - [Media properties](#media-properties)
    - [Accessing Media Conversions](#accessing-media-conversions)
    - [Blade components](#blade-components)

1. [Advanced Usage](#advanced-usage)

    - [Async vs Sync conversions](#async-vs-sync-conversions)
    - [Delayed conversions](#delayed-conversions)
    - [`onAdded` MediaCollection Callback](#onadded-mediacollection-callback)
    - [`onCompleted` MediaConversionDefinition Callback](#oncompleted-mediaconversiondefinition-callback)
    - [Custom conversions](#custom-conversions)
    - [Manually generate conversions](#manually-generate-conversions)
    - [Format Media Url](#format-media-url)

1. [Customization](#customization)

    - [Custom Media Model](#custom-media-model)

1. [Troubleshooting](#troubleshooting)
    - [Ghostscript and Imagick Issues](#ghostscript-and-imagick-issues)

## Requirements

-   PHP 8.1+
-   Laravel 11.0+
-   `spatie/image` for image conversions
-   `spatie/pdf-to-image` for PDF to image conversions
-   `ffmpeg` for video/audio processing

## Installation

You can install the package via composer:

```bash
composer require elegantly/laravel-media
```

You have to publish and run the migrations with:

```bash
php artisan vendor:publish --tag="media-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="media-config"
```

This is the contents of the published config file:

```php
use Elegantly\Media\Jobs\DeleteModelMediaJob;
use Elegantly\Media\Models\Media;

return [
    /**
     * The media model
     * Define your own model here by extending \Elegantly\Media\Models\Media::class
     */
    'model' => Media::class,

    /**
     * The path used to store temporary file copy for conversions
     * This will be used with storage_path() function
     */
    'temporary_storage_path' => 'app/tmp/media',

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

];
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="media-views"
```

## Basic Usage

### Defining Media Collections

Media Collections define how media are stored, transformed, and processed for a specific model. They provide granular control over file handling, accepted types, and transformations.

To associate a media collection with a Model, start by adding the `InteractWithMedia` interface and the `HasMedia` trait.

Next, define your collections in the `registerMediaCollections` method, as shown below:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Elegantly\Media\Concerns\HasMedia;
use Elegantly\Media\Contracts\InteractWithMedia;
use Elegantly\Media\MediaCollection;

class Channel extends Model implements InteractWithMedia
{
    use HasMedia;

    public function registerMediaCollections(): array;
    {
        return [
            new MediaCollection(
                name: 'avatar',
                single: true, // If true, only the latest file will be kept
                disk: 's3', // (optional) Specify where the file will be stored
                acceptedMimeTypes: [ // (optional) Specify accepted file types
                    'image/jpeg',
                    'image/png',
                    'image/webp'
                ]
            )
        ];
    }
}
```

### Defining Media Conversions

Media conversions create different variants of your media files. For example, a 720p version of a 1440p video or a WebP or PNG version of an image are common types of media conversions. Interestingly, a media conversion can also have its own additional conversions.

This package provides common converions to simplify your work:

-   `MediaConversionImage`: This conversion optimizes, resizes, or converts any image using `spatie/image`.
-   `MediaConversionVideo`: This conversion optimizes, resizes, or converts any video using `pbmedia/laravel-ffmpeg`.
-   `MediaConversionAudio`: This conversion optimizes, resizes, converts or extract any audio using `pbmedia/laravel-ffmpeg`.
-   `MediaConversionPoster`: This conversion extracts a poster using `pbmedia/laravel-ffmpeg`.
-   `MediaConversionPdfPreview`: This conversion extracts an image from the PDF using `spatie/pdf-to-image`.
-   `MediaConversionSpritesheet`: This conversion extracts a spritesheet from the video using `pbmedia/laravel-ffmpeg`.

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Elegantly\Media\Concerns\HasMedia;
use Elegantly\Media\Contracts\InteractWithMedia;
use Elegantly\Media\MediaCollection;
use Elegantly\Media\Definitions\MediaConversionImage;

class Channel extends Model implements InteractWithMedia
{
    use HasMedia;

    public function registerMediaCollections(): array;
    {
        return [
            new MediaCollection(
                name: 'videos',
                conversions: [
                    new MediaConversionPoster(
                        name: 'poster',
                        conversions: [
                            new MediaConversionImage(
                                name: '360p',
                                width: 360
                            ),
                        ],
                    ),
                    new MediaConversionVideo(
                        name: '720p',
                        width: 720
                    ),
                ]
            )
        ];
    }
}
```

### Adding Media

Add media to your model, using the `addMedia` method, from various sources:

-   an url
-   a resource or stream
-   a \Illuminate\Http\UploadedFile instance
-   a \Illuminate\Http\File instance

#### From a Controller

```php
use Elegantly\Media\Exceptions\InvalidMimeTypeException;

public function store(Request $request, Channel $channel)
{
    try {
        $channel->addMedia(
            file: $request->file('avatar'),
            collectionName: 'avatar',
            name: "{$channel->name}-avatar"
        );
    } catch (InvalidMimeTypeException $exception){
        // Will throw an error if the mime type is not included in the collection's `acceptedMimeTypes` parameter.
    }
}
```

#### From a Livewire Component

```php
use Livewire\WithFileUploads;
use Elegantly\Media\Exceptions\InvalidMimeTypeException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ImageUploader extends Component
{
    use WithFileUploads;

    /** @var ?TemporaryUploadedFile */
    public $avatar = null;

    public function save()
    {
        try {
            $this->channel->addMedia(
                file: $this->avatar->getRealPath(),
                collectionName: 'avatar',
                name: "{$this->channel->name}-avatar"
            );
        } catch (InvalidMimeTypeException $exception){
            // Will throw an error if the mime type is not included in the collection's `acceptedMimeTypes` parameter.
        }
    }
}
```

#### From an Url

```php
use Elegantly\Media\Exceptions\InvalidMimeTypeException;

 try {
    $channel->addMedia(
        file: "http://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4",
        collectionName: 'videos',
        name: "BigBuckBunny"
    );
} catch (InvalidMimeTypeException $exception){
    // Will throw an error if the mime type is not included in the collection's `acceptedMimeTypes` parameter.
}
```

### Retreiving Media

Retrieve media from your model:

```php
// Get all media from a specific collection
$avatars = $channel->getMedia('avatar');

// Get the first media from a collection
$avatar = $channel->getFirstMedia('avatar');

// Check if media exists
$hasAvatar = $channel->hasMedia('avatar');
```

### Media properties

Each media item provides rich metadata automatically:

```php
$media = $channel->getFirstMedia('avatar');

// File properties
$media->name; // file_name without the extension
$media->file_name;
$media->extension;
$media->mime_type;
$media->size; // in bytes
$media->humanReadableSize();

// Image/Video specific properties
$media->width;       // in pixels
$media->height;      // in pixels
$media->aspect_ratio;
$media->duration;    // for video/audio
```

You can use dot notation to access either the root properties or a specific conversion:

```php
// Get the original media URL
$originalUrl = $media->getUrl();

// Get a specific conversion URL
$thumbnailUrl = $media->getUrl(
    conversion: '360p',
    fallback: true // Falls back to original if conversion doesn't exist
);

$posterUrl = $media->getUrl(
    conversion: 'poster.360p',
    fallback: 'poster' // Falls back to an other conversion doesn't exist
);

// Use the same logic with other properties such as
$media->getPath();
$media->getWith();
// ...
```

### Access Media Conversions

To directly access conversions, use:

```php
// Check if a conversion exists
$hasThumbnail = $media->hasConversion('100p');

// Get a specific conversion
$thumbnailConversion = $media->getConversion('100p');

// Get the 'poster' conversion
$media->getParentConversion('poster.360p');

// Only get children conversions of poster
$media->getChildrenConversions('poster');
```

### Blade components

The package also provides blade components.

```html
<!-- fallback to the root media url if the conversion doesn't exist -->
<!-- allows you to specify query parameters -->
<x-media::img
    :media="$user->getFirstMedia('poster')"
    conversion="360p"
    fallback
    parameters="['foo'=>'bar']"
    alt="Video poster"
/>
```

```html
<!-- fallback to the root media url if the conversion doesn't exist -->
<!-- allows you to specify query parameters -->
<x-media::video
    :media="$user->getFirstMedia('videos')"
    conversion="720p"
    fallback
    muted
    playsinline
    autoplay
    loop
/>
```

## Advanced Usage

### Async vs. Sync Conversions

When adding new media, its conversions can be either dispatched asynchronously or generated synchronously.

You can configure the strategy in the conversion definition using the `queued` and `queue` parameters:

```php
new MediaCollection(
    name: 'avatar',
    conversions: [
        new MediaConversionImage(
            name: '360',
            width: 360,
            queued: true,  // (default) Dispatch as a background job
            queue: 'slow' // (optional) Specify a custom queue
        ),
        new MediaConversionImage(
            name: '180',
            width: 180,
            queued: false,  // Generate the conversion synchronously
        )
    ]
)
```

Synchronous conversions can be particularly useful in specific use cases, such as generating a poster immediately upon upload.

### Delayed Conversions

There are scenarios where you might want to define conversions that should not be generated immediately. For instance, if a conversion is resource-intensive or not always required, you can defer its generation to a later time.

To achieve this, configure the conversion with the `immediate` parameter set to `false`. This allows you to generate the conversion manually when needed:

```php
new MediaCollection(
    name: 'avatar',
    conversions: [
        new MediaConversionImage(
            name: '360',
            width: 360,
            immediate: false, // Conversion will not be generated at upload time
        )
    ]
)
```

To generate the conversion later, you can use the following methods:

```php
// Generate the conversion synchronously
$media->executeConversion(
    conversion: '360',
    force: false // Skips execution if the conversion already exists
);

// Dispatch the conversion as a background job
$media->dispatchConversion(
    conversion: '360',
    force: false // Skips execution if the conversion already exists
);
```

### `onAdded` MediaCollection Callback

The `onAdded` callback allows you to define custom logic that will be executed whenever new media is added to your collection.

To use it, simply set the `onAdded` parameter when defining a `MediaCollection`. For example:

```php
new MediaCollection(
    name: 'avatar',
    onAdded: function ($media) {
        // Example: Notify the model when new media is added
        // $media->model->notify(new MediaAddedNotification($media));
    }
);
```

With this, you can easily hook into the media addition process and trigger actions like sending notifications, logging, or other custom behavior.

> [!TIP]
> The same behavior can be achieved by listening to `Elegantly\Media\Events\MediaAddedEvent`.

### `onCompleted` MediaConversionDefinition Callback

The `onCompleted` callback allows you to define custom logic that will be executed whenever a new conversion is generated.

To use it, simply set the `onCompleted` parameter when defining a `MediaConversionDefinition`. For example:

```php
new MediaConversionImage(
    name: '360',
    onCompleted: function ($conversion, $media, $parent) {
        // Example: Refresh your UI
        // broadcast(new MyEvent($media));
    }
);
```

This allows you to hook into the conversion process and execute additional logic, such as updating your UI or triggering other actions.

> [!TIP]
> The same behavior can be achieved by listening to `Elegantly\Media\Events\MediaConversionAddedEvent`.

### Custom Conversions

Conversions can be anything: a variant of a file, a transcription of a video, a completely new file, or even just a string.

You can use built-in presets or define your own custom conversion. To create a custom conversion, use the `MediaConversionDefinition` class:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Elegantly\Media\Concerns\HasMedia;
use Elegantly\Media\Contracts\InteractWithMedia;
use Elegantly\Media\MediaCollection;
use Elegantly\Media\Definitions\MediaConversionDefinition;

class Channel extends Model implements InteractWithMedia
{
    use HasMedia;

    public function registerMediaCollections(): array
    {
        return [
            new MediaCollection(
                name: 'videos',
                conversions: [
                    // Using a custom conversion definition
                    new MediaConversionDefinition(
                        name: 'webp',
                        when: fn($media, $parent) => ($parent ?? $media)->type === MediaType::Image,
                        handle: function($media, $parent, $file, $filesystem, $temporaryDirectory) {
                            $source = $parent ?? $media;
                            $target = $filesystem->path("{$source->name}.webp");

                            Image::load($filesystem->path($file))
                                ->optimize()
                                ->save($target);

                            return $media->addConversion(
                                file: $target,
                                conversionName: $this->name,
                                parent: $parent,
                            );
                        }
                    ),
                ]
            ),
        ];
    }
}
```

The `handle` method of `MediaConversionDefinition` is where the logic for the conversion is implemented. It provides the following parameters:

-   **`$media`**: The Media model.
-   **`$parent`**: The MediaConversion model, if the conversion is nested.
-   **`$file`**: A local copy of the file associated with either `$media` or `$parent`.
-   **`$filesystem`**: An instance of the local filesystem where the file copy is stored.
-   **`$temporaryDirectory`**: An instance of `TemporaryDirectory` where the file copy is temporarily stored.

You don’t need to worry about cleaning up the files, as the `$temporaryDirectory` will be deleted automatically when the process completes.

To finalize the conversion, ensure you save it by calling `$media->addConversion` or returning a `MediaConversion` instance at the end of the `handle` method.

### Manually Generate Conversions

You can manage your media conversions programmatically using the following methods:

```php
// Store a new file as a conversion
$media->addConversion(
    file: $file, // Can be an HTTP File, URL, or file path
    conversionName: 'transcript',
    parent: $mediaConversion // (Optional) Specify a parent conversion
    // Additional parameters...
);

// Replace an existing conversion safely
// If the same conversion already exists, it ensures the new file is stored before deleting the previous one.
$media->replaceConversion(
    conversion: $mediaConversion
);

// Safely delete a specific conversion and all its children
$media->deleteConversion('360');

// Safely delete only the child conversions of a parent conversion
$media->deleteChildrenConversions('poster');

// Dispatch or execute a conversion
$media->dispatchConversion('360'); // Runs asynchronously as a job
$media->executeConversion('poster.360'); // Executes synchronously
$media->getOrExecuteConversion('poster.360'); // Retrieves or generates the conversion

// Retrieve conversion information
$media->getConversion('360'); // Fetch a specific conversion
$media->hasConversion('360'); // Check if a conversion exists
$media->getParentConversion('poster.360'); // Retrieve the parent (poster) of a conversion
$media->getChildrenConversions('poster'); // Retrieve child conversions
```

Additionally, you can use an Artisan command to generate conversions with various options:

```bash
php artisan media:generate-conversions
```

This provides a convenient way to process conversions in bulk or automate them within your workflows.

### Format Media URLs

Some cloud providers like Cloudflare, Bunny, or ImageKit allow you to create instant transformations of your images and videos using specially formatted URLs.

This package gives you a simple way to format your URLs so you can take advantage of these services.

When using the `$media->getUrl()` method, you can specify two parameters:

-   `parameters`: An array of values
-   `formatter`: The class name of the formatter you want to use

By combining these parameters, you can retrieve formatted URLs like this:

```php
use \Elegantly\Media\UrlFormatters\CloudflareImageUrlFormatter;

// Default formatter (query parameters)
$default = $media->getUrl(
    parameters: ['width' => 360],
); // https://your-url.com?width=360

// Cloudflare formatter (path-based format)
$cloudflare = $media->getUrl(
    parameters: ['width' => 360],
    formatter: CloudflareImageUrlFormatter::class
); // /cdn-cgi/media/width=360/https://your-url.com
```

This package comes with 3 formatters out of the box:

-   `\Elegantly\Media\UrlFormatters\DefaultUrlFormatter`
-   `\Elegantly\Media\UrlFormatters\CloudflareImageUrlFormatter`
-   `\Elegantly\Media\UrlFormatters\CloudflareVideoUrlFormatter`

Feel free to implement your own formatter by extending `\Elegantly\Media\UrlFormatters\AbstractUrlFormatter`.

## Customization

### Custom Media Model

You can define your own Media model to use with the library.

First, create your own model class:

```php
namespace App\Models;

use Elegantly\Media\Models\Media as ElegantlyMedia;

class Media extends ElegantlyMedia
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
use Elegantly\Media\Concerns\HasMedia;
use Elegantly\Media\Contracts\InteractWithMedia;

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

## Troubleshooting

### Ghostscript and Imagick Issues

This package relies on the `spatie/pdf-to-image` library, which uses Ghostscript via Imagick to convert PDFs into images.

If you encounter errors while generating images from PDFs, such as:

-   `attempt to perform an operation not allowed by the security policy 'PDF'`
-   `Uncaught ImagickException: FailedToExecuteCommand 'gs'`

these issues are likely related to the configuration of Ghostscript or Imagick on your system.

For detailed guidance on resolving these errors, refer to the [spatie/pdf-to-image documentation on Ghostscript issues](https://github.com/spatie/pdf-to-image/blob/main/README.md#issues-regarding-ghostscript).

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
