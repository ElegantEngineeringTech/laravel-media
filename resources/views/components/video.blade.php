@props([
    'media',
    'conversion' => null,
    'fallback' => false,
    'dispatch' => false,
    'parameters' => null,
    'src' => null,
    'height' => null,
    'width' => null,
    'alt' => null,
    'poster' => null,
    'posterConversion' => 'poster',
    'posterDispatch' => false,
    'autoplay' => false,
    'muted' => false,
    'playsinline' => false,
    'loop' => false,
])

@php

    $source = $conversion
        ? $media->getConversion(
            name: $conversion,
            state: \Elegantly\Media\Enums\MediaConversionState::Succeeded,
            fallback: is_bool($fallback) ? null : $fallback,
            dispatch: $dispatch,
        )
        : null;

    $source ??= $fallback === true ? $media : null;

    if ($posterConversion) {
        $poster ??= $media->getUrl(conversion: $posterConversion, dispatch: $posterDispatch);
    }

@endphp

<video {!! $attributes !!} src="{!! $src ?? $source->url !!}" height="{{ $height ?? $source->height }}"
    width="{{ $width ?? $source->width }}" alt="{{ $alt ?? $source->name }}" poster="{{ $poster }}"
    {{ when($autoplay, 'autoplay') }} {{ when($muted, 'muted') }} {{ when($playsinline, 'playsinline') }}
    {{ when($loop, 'loop') }}>
    {{ $slot }}
</video>
