<?php

namespace Elegantly\Media;

use Closure;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Spatie\TemporaryDirectory\TemporaryDirectory as SpatieTemporaryDirectory;

class TemporaryDirectory extends SpatieTemporaryDirectory
{
    /**
     * @template TValue
     *
     * @param  Closure(SpatieTemporaryDirectory $temporaryDirectory):TValue  $callback
     * @return TValue
     */
    public static function callback(
        Closure $callback,
        ?string $location = null
    ): mixed {

        $location ??= storage_path(config()->string('media.temporary_storage_path', 'app/tmp/media'));

        $temporaryDirectory = (new self)
            ->location($location)
            ->create();

        try {
            $value = $callback($temporaryDirectory);
        } catch (\Throwable $th) {
            $temporaryDirectory->delete();
            throw $th;
        }

        $temporaryDirectory->delete();

        return $value;

    }

    public static function storage(SpatieTemporaryDirectory $temporaryDirectory): Filesystem
    {
        return Storage::build([
            'driver' => 'local',
            'root' => $temporaryDirectory->path(),
        ]);
    }
}
