<?php

declare(strict_types=1);

namespace Elegantly\Media\Contracts;

use Closure;
use Elegantly\Media\TemporaryDirectory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\File as HttpFile;
use Illuminate\Http\UploadedFile;

interface InteractWithFiles
{
    public function dirname(): ?string;

    public function getDisk(): ?Filesystem;

    /**
     * @return null|resource
     */
    public function readStream();

    public function deleteFile(): bool;

    public function deleteDirectory(): bool;

    public function rename(string $name): static;

    /**
     * @return string The new file path
     */
    public function putFile(
        string $disk,
        string $destination,
        UploadedFile|HttpFile $file,
        string $name,
    ): string;

    /**
     * @param  string|UploadedFile|HttpFile|resource  $file
     * @param  null|(Closure(UploadedFile|HttpFile $file, TemporaryDirectory $temporaryDirectory):(UploadedFile|HttpFile))  $before
     */
    public function storeFile(
        mixed $file,
        ?string $destination = null,
        ?string $name = null,
        ?string $disk = null,
        ?Closure $before = null,
    ): static;

    /**
     * @param  null|(Closure(UploadedFile|HttpFile $file, TemporaryDirectory $temporaryDirectory):(UploadedFile|HttpFile))  $before
     */
    public function storeFileFromHttpFile(
        UploadedFile|HttpFile $file,
        ?string $destination = null,
        ?string $name = null,
        ?string $disk = null,
        ?Closure $before = null,
    ): static;

    /**
     * @return ?string The new file path on success, null on failure
     */
    public function copyFileTo(
        string|Filesystem $disk,
        string $path,
    ): ?string;

    /**
     * @return ?string The new file path on success, null on failure
     */
    public function moveFileTo(
        string $disk,
        string $path,
    ): ?string;

    /**
     * Transform the media file inside a temporary directory while keeping the same Model
     * Usefull to optimize or convert the media file afterwards
     *
     * @param  Closure(HttpFile $copy, TemporaryDirectory $temporaryDirectory): HttpFile  $transform
     * @return $this
     */
    public function transformFile(Closure $transform): static;
}
