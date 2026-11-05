<?php

declare(strict_types=1);

namespace Elegantly\Media;

use Closure;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Spatie\TemporaryDirectory\TemporaryDirectory as SpatieTemporaryDirectory;

class TemporaryDirectory extends SpatieTemporaryDirectory
{
    /**
     * Create the directory, by default in media.temporary_storage_path.
     */
    public static function make(?string $location = null): static
    {
        return parent::make(
            $location ?? MediaServiceProvider::getTemporaryStoragePath()
        );
    }

    /**
     * @template TValue
     *
     * @param  Closure(static $temporaryDirectory):TValue  $callback
     * @return TValue
     */
    public static function callback(
        Closure $callback,
        ?string $location = null
    ): mixed {

        $temporaryDirectory = static::make($location);

        try {
            $value = $callback($temporaryDirectory);
        } catch (\Throwable $th) {
            $temporaryDirectory->delete();
            throw $th;
        }

        $temporaryDirectory->delete();

        return $value;

    }

    public function toFilesystem(): Filesystem
    {
        return Storage::build([
            'driver' => 'local',
            'root' => $this->path(),
        ]);
    }
}
