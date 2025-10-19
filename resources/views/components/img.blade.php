@props([
    'media',
    'conversion' => null,
    'fallback' => false,
    'dispatch' => false,
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

    $url =
        $src ??
        $media->getUrl(conversion: $conversion, fallback: $fallback, parameters: $parameters, dispatch: $dispatch);
@endphp

<img {!! $attributes !!} loading="{{ $loading }}" src="{!! $url !!}"
    height="{{ $height ?? $media->getHeight($conversion, $fallback) }}"
    width="{{ $width ?? $media->getWidth($conversion, $fallback) }}"
    alt="{{ $alt ?? $media->getName($conversion, $fallback) }}"
    @if ($placeholderValue) style="background-size:cover;background-image: url(data:image/jpeg;base64,{{ $placeholderValue }})" @endif>
