<?php

namespace ElegantEngineeringTech\Media;

use Closure;
use ElegantEngineeringTech\Media\Casts\GeneratedConversion;
use ElegantEngineeringTech\Media\Jobs\MediaConversionJob;
use ElegantEngineeringTech\Media\Models\Media;
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
