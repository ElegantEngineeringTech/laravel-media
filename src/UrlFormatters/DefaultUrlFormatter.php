<?php

declare(strict_types=1);

namespace Elegantly\Media\UrlFormatters;

class DefaultUrlFormatter extends AbstractUrlFormatter
{
    public function format(string $url, ?array $parameters = null): string
    {
        if (empty($parameters)) {
            return $url;
        }

        return $url.'?'.http_build_query($parameters);
    }
}
