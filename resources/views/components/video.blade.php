@props([
    'conversion' => null,
    'poster' => null,
    'src' => null,
    'autoplay' => false,
    'muted' => false,
    'playsinline' => false,
    'loop' => false,
    'media',
])

<video {!! $attributes !!} height="{{ $media->getHeight($conversion) }}" width="{{ $media->getWidth($conversion) }}"
    alt="{{ $media->getName($conversion) }}"
    poster="{{ $poster ?? $media->getUrl($conversion ? $conversion . '.poster' : 'poster') }}"
    src="{{ $src ?? $media->getUrl($conversion) }}" @if ($autoplay) autoplay @endif
    @if ($muted) muted @endif @if ($playsinline) playsinline @endif
    @if ($loop) loop @endif>
    {{ $slot }}
</video>
