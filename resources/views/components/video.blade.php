@props([
    'conversion' => null,
    'poster' => null,
    'src' => null,
    'media',
])

<video {!! $attributes !!} height="{{ $media->getHeight($conversion) }}" width="{{ $media->getWidth($conversion) }}"
    alt="{{ $media->getName($conversion) }}"
    poster="{{ $poster ?? $media->getUrl($conversion ? $conversion . '.poster' : 'poster') }}"
    src="{{ $src ?? $media->getUrl() }}">
    {{ $slot }}
</video>
