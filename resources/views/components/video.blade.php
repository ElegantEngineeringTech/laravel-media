@props([
    'media',
    // conversion
    'conversion' => null,
    'fallback' => false,
    'dispatch' => false,
    'parameters' => null,
    // poster
    'posterConversion' => 'poster',
    'posterFallback' => false,
    'posterDispatch' => false,
    'posterParameters' => null,
    // attributes
    'src' => null,
    'poster' => null,
    'height' => null,
    'width' => null,
    'alt' => null,
    'autoplay' => false,
    'muted' => false,
    'playsinline' => false,
    'loop' => false,
])

@php
    $source = $media->getMediaOrConversion(conversion: $conversion, fallback: $fallback, dispatch: $dispatch);

    $src ??= $source?->getUrl(parameters: $parameters);
    $height ??= $source?->height;
    $width ??= $source?->width;

    $poster ??= $media
        ->getConversion(
            name: $posterConversion,
            state: \Elegantly\Media\Enums\MediaConversionState::Succeeded,
            fallback: $posterFallback,
            dispatch: $posterDispatch,
        )
        ?->getUrl(parameters: $posterParameters);

@endphp

<video {{ $attributes }} src="{{ $src }}" height="{{ $height }}" width="{{ $width }}"
    poster="{{ $poster }}" {{ when($autoplay, 'autoplay') }} {{ when($muted, 'muted') }}
    {{ when($playsinline, 'playsinline') }} {{ when($loop, 'loop') }}>
    {{ $slot }}
</video>
