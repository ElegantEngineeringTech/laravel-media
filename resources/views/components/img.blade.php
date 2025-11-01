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
    if ($conversion) {
        $source = $media->getConversion(
            name: $conversion,
            state: \Elegantly\Media\Enums\MediaConversionState::Succeeded,
            fallback: is_bool($fallback) ? null : $fallback,
            dispatch: $dispatch,
        );

        $source ??= $fallback === true ? $media : null;
    } else {
        $source = $media;
    }

    $placeholder = $placeholder === true ? 'placeholder' : $placeholder;
    $placeholderContents = $placeholder ? $media->getConversion($placeholder)?->contents : null;
@endphp

<img {!! $attributes !!} loading="{{ $loading }}" src="{!! $src ?? $source?->getUrl($parameters) !!}"
    height="{{ $height ?? $source?->height }}" width="{{ $width ?? $source?->width }}" alt="{{ $alt ?? $source?->name }}"
    @if ($placeholderContents) style="background-size:cover;background-image: url(data:image/jpeg;base64,{{ $placeholderContents }})" @endif>
