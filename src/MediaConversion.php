<?php

namespace Finller\Media;

use Closure;
use Finller\Media\Casts\GeneratedConversion;
use Finller\Media\Jobs\MediaConversionJob;
use Finller\Media\Models\Media;
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
}
