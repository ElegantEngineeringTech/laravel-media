<?php

namespace Finller\Media\Casts;

use Carbon\Carbon;
use Finller\Media\Enums\MediaType;
use Finller\Media\Traits\InteractsWithMediaFiles;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * @property null|Collection<string, GeneratedConversion> $generated_conversions
 */
class GeneratedConversion implements Arrayable
{
    use InteractsWithMediaFiles;

    public Carbon $created_at;

    public Carbon $state_set_at;

    public function __construct(
        public ?string $state = null,
        public ?string $file_name = null,
        public ?string $name = null,
        public ?MediaType $type = null,
        public ?string $disk = null,
        public ?string $path = null,
        public ?string $mime_type = null,
        public ?string $extension = null,
        public ?int $size = null,
        public ?float $duration = null,
        public ?int $height = null,
        public ?int $width = null,
        public ?float $aspect_ratio = null,
        public ?string $average_color = null,
        public Collection $generated_conversions = new Collection(),
        ?Carbon $created_at = null,
        ?Carbon $state_set_at = null,
    ) {
        $this->created_at = $created_at ?? now();
        $this->state_set_at = $state_set_at ?? now();
    }

    public static function make(array $attributes): self
    {
        $state_set_at = Arr::get($attributes, 'state_set_at');
        $created_at = Arr::get($attributes, 'created_at');
        $type = Arr::get($attributes, 'type');

        return new self(
            file_name: Arr::get($attributes, 'file_name'),
            name: Arr::get($attributes, 'name'),
            state: Arr::get($attributes, 'state'),
            state_set_at: $state_set_at ? Carbon::parse($state_set_at) : null,
            type: $type ? MediaType::from($type) : null,
            disk: Arr::get($attributes, 'disk'),
            path: Arr::get($attributes, 'path'),
            mime_type: Arr::get($attributes, 'mime_type'),
            extension: Arr::get($attributes, 'extension'),
            size: Arr::get($attributes, 'size'),
            duration: Arr::get($attributes, 'duration'),
            height: Arr::get($attributes, 'height'),
            width: Arr::get($attributes, 'width'),
            aspect_ratio: Arr::get($attributes, 'aspect_ratio'),
            average_color: Arr::get($attributes, 'average_color'),
            generated_conversions: collect(Arr::get($attributes, 'generated_conversions', []))->map(fn ($item) => self::make($item)),
            created_at: $created_at ? Carbon::parse($created_at) : null,
        );
    }

    public function delete(): static
    {
        $this->deleteDirectory();

        $this->path = null;

        $this->generated_conversions->each(fn (self $generatedConversion) => $generatedConversion->delete());
        $this->generated_conversions = collect();

        return $this;
    }

    public function toArray(): array
    {
        return array_map(
            fn ($value) => $value instanceof Arrayable ? $value->toArray() : $value,
            get_object_vars($this),
        );
    }
}
