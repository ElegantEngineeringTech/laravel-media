@props([
    'media',
    'conversion' => null,
    'fallback' => false,
    'parameters' => null,
    'loading' => 'lazy',
    'alt' => null,
    'src' => null,
    'width' => null,
    'height' => null,
])

<img {!! $attributes !!} loading="{{ $loading }}" src="{!! $src ?? $media->getUrl($conversion, $fallback, $parameters) !!}"
    height="{{ $height ?? $media->getHeight($conversion, $fallback) }}"
    width="{{ $width ?? $media->getWidth($conversion, $fallback) }}"
    alt="{{ $alt ?? $media->getName($conversion, $fallback) }}">
