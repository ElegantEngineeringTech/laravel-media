<?php

namespace Finller\LaravelMedia;

use Finller\LaravelMedia\Jobs\ConversionJob;

class MediaConversion
{
    public function __construct(
        public string $name,
        public string|ConversionJob $job,
    ) {
    }
}
