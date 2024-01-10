@props([
    'conversion' => null,
    'responsive' => true,
    'loading' => 'lazy',
    'media',
])

<img {!! $attributes !!} src="{{ $media->getUrl($conversion) }}" loading="{{ $loading }}"
    height="{{ $media->getHeight($conversion) }}" width="{{ $media->getWidth($conversion) }}"
    alt="{{ $media->getName($conversion) }}"
    @if ($responsive) srcset="{{ $media->getSrcset($conversion)->join(', ') }}" 
    onload="window.requestAnimationFrame(function(){if(!(size=getBoundingClientRect().width))return;onload=null;sizes=Math.ceil(size/window.innerWidth*100)+'vw';});"
    sizes="1px" @endif>
