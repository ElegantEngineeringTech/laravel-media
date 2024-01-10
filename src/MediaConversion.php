<?php

namespace Finller\Media;

use Finller\Media\Jobs\ConversionJob;
use Illuminate\Support\Collection;

class MediaConversion
{
    /**
     * @param  bool  $sync When sync is true, dispatch_sync is used
     */
    public function __construct(
        public string $name,
        public ConversionJob $job,
        public bool $sync = false,
        public Collection $conversions = new Collection(),
    ) {
        $this->conversions = $conversions->keyBy('name');
    }
}
