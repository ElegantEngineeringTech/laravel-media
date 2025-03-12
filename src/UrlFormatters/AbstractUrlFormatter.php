<?php

declare(strict_types=1);

namespace Elegantly\Media\UrlFormatters;

abstract class AbstractUrlFormatter
{
    /**
     * @param  null|array<array-key, mixed>  $parameters
     */
    abstract public function format(string $url, ?array $parameters = null): string;
}
