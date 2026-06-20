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
    $source = $media->getMediaOrConversion(conversion: $conversion, fallback: $fallback, dispatch: $dispatch);

    $placeholder = $placeholder === true ? 'placeholder' : $placeholder;
    $placeholderContents = $placeholder ? $media->getConversion($placeholder)?->contents : null;
@endphp

<img {!! $attributes !!} loading="{{ $loading }}" src="{!! $src ?? $source?->getUrl(parameters: $parameters) !!}"
    height="{{ $height ?? $source?->height }}" width="{{ $width ?? $source?->width }}" alt="{{ $alt ?? $source?->name }}"
    @if ($placeholderContents) style="background-size:cover;background-image: url(data:image/jpeg;base64,{{ $placeholderContents }})" @endif />
