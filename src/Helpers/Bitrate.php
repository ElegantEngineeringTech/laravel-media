<?php

declare(strict_types=1);

namespace Elegantly\Media\Helpers;

use Stringable;

class Bitrate implements Stringable
{
    final public function __construct(
        public readonly int $value,
    ) {
        //
    }

    /**
     * @return ($value is null ? null : static)
     */
    public static function parse(null|string|float|int|self $value): ?static
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return new static((int) floor(max(0, $value)));
        }

        if (is_numeric($value)) {
            return static::parse((int) $value);
        }

        if (is_string($value)) {

            preg_match('/^(?<value>\d+(?:\.\d+)?)(?<unit>[kmg])?$/', strtolower(trim($value)), $matches);

            $value = (float) ($matches['value'] ?? 0);
            $unit = $matches['unit'] ?? null;

            return match ($unit) {
                'k' => static::parse($value * 1_000),
                'm' => static::parse($value * 1_000_000),
                'g' => static::parse($value * 1_000_000_000),
                default => static::parse($value),
            };
        }

        return new static($value->value);
    }

    public function format(): string
    {
        return ((int) floor($this->value / 1_000)).'k';
    }

    public function __toString(): string
    {
        return $this->format();
    }

    public function max(null|string|float|int|self $max): static
    {
        if ($max = static::parse($max)) {
            return new static(min($max->value, $this->value));
        }

        return new static($this->value);
    }

    public function min(null|string|float|int|self $min): static
    {
        if ($min = static::parse($min)) {
            return new static(max($min->value, $this->value));
        }

        return new static($this->value);
    }
}
