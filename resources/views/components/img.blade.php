@props([
    'conversion' => null,
    'media',
    'responsive' => false,
    'loading' => 'lazy',
    'alt' => null,
    'width' => null,
    'height' => null,
    'sizes' => '1px',
])

<img {!! $attributes !!} src="{{ $media->getUrl($conversion) }}" loading="{{ $loading }}"
    height="{{ $height ?? $media->getHeight($conversion) }}" width="{{ $width ?? $media->getWidth($conversion) }}"
    alt="{{ $alt ?? $media->getName($conversion) }}"
    @if ($responsive) srcset="{{ $media->getSrcset($conversion)->join(', ') }}" 
    onload="window.requestAnimationFrame(function(){if(!(size=getBoundingClientRect().width))return;onload=null;sizes=Math.ceil(size/window.innerWidth*100)+'vw';});"
    sizes="{{ $sizes }}" @endif>
