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
    'placeholder' => null,
])

@php
    $placeholder = $placeholder === true ? 'placeholder' : $placeholder;
    $placeholderValue = $placeholder ? $media->getConversion($placeholder)?->contents : null;
@endphp

<img {!! $attributes !!} loading="{{ $loading }}" src="{!! $src ?? $media->getUrl($conversion, $fallback, $parameters) !!}"
    height="{{ $height ?? $media->getHeight($conversion, $fallback) }}"
    width="{{ $width ?? $media->getWidth($conversion, $fallback) }}"
    alt="{{ $alt ?? $media->getName($conversion, $fallback) }}"
    @if ($placeholderValue) style="background-size:cover;background-image: url(data:image/jpeg;base64,{{ $placeholderValue }})" @endif>
