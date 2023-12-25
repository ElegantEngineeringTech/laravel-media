@props([
    'conversion' => null,
    'responsive' => true,
    'media',
])
<img {!! $attributes !!} src="{{ $media->getUrl($conversion) }}"
    @if ($responsive) srcset="{{ $media->getSrcset()->join(', ') }}" @endif>
