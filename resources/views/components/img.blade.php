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
    x-data="{
        sizes: '1px',
        resize() {
            this.$nextTick(() => {
                setTimeout(() => {
                    size = this.$el.getBoundingClientRect().width ?? 1;
                    this.sizes = Math.ceil(size / window.innerWidth * 100) + 'vw';
                }, 50);
            });
        }
    }"
    x-intersect.once="resize" x-bind:sizes="sizes" @endif>
