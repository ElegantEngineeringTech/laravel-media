<?php

declare(strict_types=1);

use Elegantly\Media\Helpers\Bitrate;

it('correctly parses bitrate', function ($value, $raw, $formatted) {

    $bitrate = Bitrate::parse($value);

    expect($bitrate->value)->toBe($raw);
    expect($bitrate->format())->toBe($formatted);

})->with([
    ['256k', 256_000, '256k'],
    [256_000, 256_000, '256k'],
    ['256000', 256_000, '256k'],
    ['2072870', 2_072_870, '2072k'],
]);
