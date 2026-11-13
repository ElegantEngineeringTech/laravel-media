<?php

declare(strict_types=1);

namespace Elegantly\Media\Rules;

use Closure;
use Elegantly\Media\Helpers\File;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validate a media name, as given to `addMedia(name: ...)` or `rename()`, once sanitized the way it will be stored.
 *
 * Fails when the sanitized name is empty (e.g. "!!!") or when the stored file name would exceed `$max` characters.
 *
 * @see File::sanitizeFilename()
 */
class ValidFilename implements ValidationRule
{
    /**
     * Room kept for the extension, see File::extension().
     */
    public const EXTENSION_MAX_LENGTH = 11;

    /**
     * @param  int  $max  Maximum length of the stored file name, extension included. 255 matches both the `file_name` column and common filesystem limits.
     */
    public function __construct(
        public int $max = 255,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('validation.string')->translate();

            return;
        }

        $name = File::sanitizeFilename($value);
        $max = $this->max - self::EXTENSION_MAX_LENGTH;

        if ($name === '') {
            $fail('media::validation.filename.empty')->translate();
        } elseif (mb_strlen($name) > $max) {
            $fail('media::validation.filename.too_long')->translate(['max' => $max]);
        }
    }
}
