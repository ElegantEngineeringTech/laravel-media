@props([
    'conversion' => null,
    'media',
    'responsive' => false,
    'loading' => 'lazy',
    'alt' => null,
    'width' => null,
    'height' => null,
    'sizes' => '1px',
    'fallback' => false,
])

<img {!! $attributes !!} loading="{{ $loading }}" src="{{ $media->getUrl($conversion, $fallback) }}"
    height="{{ $height ?? $media->getHeight($conversion, $fallback) }}"
    width="{{ $width ?? $media->getWidth($conversion, $fallback) }}"
    alt="{{ $alt ?? $media->getName($conversion, $fallback) }}"
    @if ($responsive) srcset="{{ $media->getSrcset($conversion)->join(', ') }}" 
    onload="window.requestAnimationFrame(function(){if(!(size=getBoundingClientRect().width))return;onload=null;sizes=Math.ceil(size/window.innerWidth*100)+'vw';});"
    sizes="{{ $sizes }}" @endif>
