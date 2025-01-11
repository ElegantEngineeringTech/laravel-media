<?php

declare(strict_types=1);

namespace Elegantly\Media\Events;

use Elegantly\Media\Models\MediaConversion;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disptached when a new file is strored
 */
class MediaConversionAddedEvent
{
    use Dispatchable, SerializesModels;

    /**
     * @return void
     */
    public function __construct(public MediaConversion $conversion)
    {
        //
    }
}
