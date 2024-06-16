<?php

namespace Elegantly\Media;

use Closure;
use Elegantly\Media\Casts\GeneratedConversion;
use Elegantly\Media\Jobs\MediaConversionJob;
use Elegantly\Media\Models\Media;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

class MediaConversion
{
    /**
     * @param  null|(Closure(GeneratedConversion):(Arrayable<int|string,MediaConversion>|iterable<MediaConversion>))  $conversions
     */
    public function __construct(
        public string $conversionName,
        protected MediaConversionJob $job,
        public bool $sync = false,
        public ?Closure $conversions = null,
    ) {
        //
    }

    /**
     * @return Collection<string, MediaConversion>
     */
    public function getConversions(Media $media): Collection
    {
        $conversions = $this->conversions;

        if ($conversions instanceof Closure) {
            $generatedConversion = $media->getGeneratedConversion($this->conversionName);

            if ($generatedConversion) {
                $conversions = $conversions($generatedConversion);
            } else {
                $conversions = [];
            }
        }

        return collect($conversions)->keyBy('conversionName');
    }

    public function getJob(): MediaConversionJob
    {
        return $this->job->setConversionName($this->conversionName);
    }

    public function dispatch(
        ?string $withConversionName = null,
        bool $forceSync = false,
        bool $forceQueued = false,
    ): static {
        $job = $this->getJob();

        if ($withConversionName) {
            $job->setConversionName($withConversionName);
        }

        if (($this->sync || $forceSync) && ! $forceQueued) {
            dispatch_sync($job);
        } else {
            dispatch($job);
        }

        return $this;
    }
}
