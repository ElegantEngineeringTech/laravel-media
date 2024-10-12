<?php

namespace Elegantly\Media\Events;

use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disptached when a new file is strored
 */
class MediaFileStoredEvent
{
    use Dispatchable, SerializesModels;

    /**
     * @return void
     */
    public function __construct(public Media|MediaConversion $media)
    {
        //
    }
}
