<?php

namespace Elegantly\Media\Events;

use Elegantly\Media\Models\Media;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disptached when any new file
 */
class MediaFileStoredEvent
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(public Media $media, public string $path)
    {
        //
    }
}
