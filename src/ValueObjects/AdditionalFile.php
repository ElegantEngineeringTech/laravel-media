<?php

declare(strict_types=1);

namespace Elegantly\Media\ValueObjects;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Storage;
use JsonSerializable;

/**
 * @implements Arrayable<string, null|string|int>
 */
class AdditionalFile implements Arrayable, JsonSerializable
{
    public string $disk;

    public string $path;

    public string $name;

    public string $file_name;

    /**
     * @var int in bytes
     */
    public int $size;

    public ?string $extension = null;

    public ?string $mime_type = null;

    /**
     * @param  array{disk: string, path: string, name: string, file_name: string, extension: null|string, mime_type: null|string, size: int}  $data
     */
    public function __construct(array $data)
    {
        $this->disk = $data['disk'];
        $this->path = $data['path'];
        $this->name = $data['name'];
        $this->file_name = $data['file_name'];
        $this->extension = $data['extension'] ?? null;
        $this->mime_type = $data['mime_type'] ?? null;
        $this->size = $data['size'];
    }

    public function getDisk(): Filesystem
    {
        return Storage::disk($this->disk);
    }

    /**
     * @return array{disk: string, path: string, name: string, file_name: string, extension: null|string, mime_type: null|string, size: int}
     */
    public function toArray(): array
    {
        return [
            'disk' => $this->disk,
            'path' => $this->path,
            'name' => $this->name,
            'file_name' => $this->file_name,
            'extension' => $this->extension,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
        ];
    }

    /**
     * @return array{disk: string, path: string, name: string, file_name: string, extension: null|string, mime_type: null|string, size: int}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
