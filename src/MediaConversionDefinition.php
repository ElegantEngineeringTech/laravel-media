<?php

declare(strict_types=1);

namespace Elegantly\Media;

use Closure;
use Elegantly\Media\Converters\MediaConverter;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;

class MediaConversionDefinition
{
    /**
     * @param  (Closure(Media $media): MediaConverter)  $converter
     * @param  null|bool|(Closure(Media $media, ?MediaConversion $parent): null|bool)  $when
     * @param  null|(Closure(?MediaConversion $conversion, Media $media, ?MediaConversion $parent): void)  $onCompleted
     * @param  MediaConversionDefinition[]  $conversions
     */
    public function __construct(
        public string $name,
        public Closure $converter,
        public null|bool|Closure $when = null,
        public ?Closure $onCompleted = null,
        public bool $immediate = true,
        public bool $queued = true,
        public ?string $queue = null,
        public \DateTimeInterface|\DateInterval|int|null $delay = null,
        public array $conversions = [],
    ) {
        /** @var array<string, MediaConversionDefinition> $conversions */
        $conversions = collect($conversions)->keyBy('name')->all();
        $this->conversions = $conversions;
    }

    public function shouldExecute(Media $media, ?MediaConversion $parent): bool
    {
        $when = $this->when;

        if ($when === null) {
            return true;
        }

        if (is_bool($when)) {
            return $when;
        }

        return (bool) $when($media, $parent);
    }
}
