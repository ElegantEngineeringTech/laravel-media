<?php

declare(strict_types=1);

namespace Elegantly\Media\Events;

use Elegantly\Media\Models\Media;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disptached when a new file is strored
 */
class MediaAddedEvent
{
    use Dispatchable, SerializesModels;

    /**
     * @return void
     */
    public function __construct(public Media $media)
    {
        //
    }
}
