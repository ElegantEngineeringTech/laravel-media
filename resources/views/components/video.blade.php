@props([
    'media',
    'conversion' => null,
    'fallback' => false,
    'parameters' => null,
    'src' => null,
    'height' => null,
    'width' => null,
    'alt' => null,
    'poster' => null,
    'posterConversion' => 'poster',
    'autoplay' => false,
    'muted' => false,
    'playsinline' => false,
    'loop' => false,
])

<video {!! $attributes !!} src="{!! $src ?? $media->getUrl($conversion, $parameters) !!}" height="{{ $height ?? $media->getHeight($conversion) }}"
    width="{{ $width ?? $media->getWidth($conversion) }}" alt="{{ $alt ?? $media->getName($conversion) }}"
    poster="{{ $poster ?? $media->getUrl($posterConversion) }}" {{ when($autoplay, 'autoplay') }}
    {{ when($muted, 'muted') }} {{ when($playsinline, 'playsinline') }} {{ when($loop, 'loop') }}>
    {{ $slot }}
</video>
