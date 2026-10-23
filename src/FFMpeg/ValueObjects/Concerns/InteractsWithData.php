<?php

declare(strict_types=1);

namespace Elegantly\Media\FFMpeg\ValueObjects\Concerns;

/**
 * Typed accessors over the raw ffprobe output held in `$this->data`.
 */
trait InteractsWithData
{
    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }

    protected function getInt(string $key): ?int
    {
        $value = $this->get($key);

        return is_numeric($value) ? (int) $value : null;
    }

    protected function getFloat(string $key): ?float
    {
        $value = $this->get($key);

        return is_numeric($value) ? (float) $value : null;
    }

    protected function getString(string $key): ?string
    {
        $value = $this->get($key);

        return is_string($value) ? $value : null;
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function getArray(string $key): array
    {
        $value = $this->get($key);

        return is_array($value) ? $value : [];
    }
}
