<?php

namespace Finller\Media;

use Finller\Media\Jobs\ConversionJob;
use Illuminate\Support\Collection;

class MediaConversion
{
    public function __construct(
        public string $name,
        public ConversionJob $job,
        public Collection $conversions = new Collection()
    ) {
        $this->conversions = $conversions->keyBy('name');
    }
}
