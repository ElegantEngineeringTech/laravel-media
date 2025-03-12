<?php

declare(strict_types=1);

namespace Elegantly\Media\UrlFormatters;

/**
 * @see https://developers.cloudflare.com/stream/transform-videos/
 */
class CloudflareVideoUrlFormatter extends AbstractUrlFormatter
{
    public function format(string $url, ?array $parameters = null): string
    {
        if (empty($parameters)) {
            return $url;
        }

        $query = http_build_query($parameters, '', ',');

        return "/cdn-cgi/media/{$query}/{$url}";
    }
}
