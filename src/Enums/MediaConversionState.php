<?php

declare(strict_types=1);

namespace Elegantly\Media\Enums;

enum MediaConversionState: string
{
    case Succeeded = 'succeeded';
    case Skipped = 'skipped';
    case Pending = 'pending';
    case Failed = 'failed';
}
