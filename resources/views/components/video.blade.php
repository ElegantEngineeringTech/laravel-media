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
    $url =
        $src ??
        $media->getUrl(conversion: $conversion, fallback: $fallback, parameters: $parameters, dispatch: $dispatch);

    $posterUrl = $poster ?? $media->getUrl(conversion: $posterConversion, dispatch: $posterDispatch);
@endphp

<video {!! $attributes !!} src="{!! $url !!}" height="{{ $height ?? $media->getHeight($conversion) }}"
    width="{{ $width ?? $media->getWidth($conversion) }}" alt="{{ $alt ?? $media->getName($conversion) }}"
    poster="{{ $posterUrl }}" {{ when($autoplay, 'autoplay') }} {{ when($muted, 'muted') }}
    {{ when($playsinline, 'playsinline') }} {{ when($loop, 'loop') }}>
    {{ $slot }}
</video>
