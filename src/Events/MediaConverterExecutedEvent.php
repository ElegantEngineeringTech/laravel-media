<?php

declare(strict_types=1);

namespace Elegantly\Media\Events;

use Elegantly\Media\Converters\MediaConverter;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MediaConverterExecutedEvent
{
    use Dispatchable, SerializesModels;

    /**
     * @return void
     */
    public function __construct(public MediaConverter $converter)
    {
        //
    }
}
