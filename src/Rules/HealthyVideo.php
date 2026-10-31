<?php

declare(strict_types=1);

namespace Elegantly\Media\Rules;

use Closure;
use Elegantly\Media\Facades\FFMpeg;
use Elegantly\Media\FFMpeg\Video;
use Illuminate\Contracts\Validation\ValidationRule;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Validate that a file is a readable video.
 *
 * Notes:
 * - Only file instances are accepted, never string paths, so that request input can't probe the server's files.
 * - Throws when the check itself fails (misconfigured binary, timeout) instead of failing the validation.
 *
 * @see Video::issue()
 */
class HealthyVideo implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof File) {
            $fail('validation.file')->translate();

            return;
        }

        if ($issue = FFMpeg::video()->issue($value->getPathname())) {
            $fail("media::validation.video.{$issue->value}")->translate();
        }
    }
}
