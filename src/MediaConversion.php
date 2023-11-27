<?php

namespace Finller\LaravelMedia;

use Finller\LaravelMedia\Jobs\ConversionJob;
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
