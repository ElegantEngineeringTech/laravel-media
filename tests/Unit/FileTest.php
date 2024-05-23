<?php

use ElegantEngineeringTech\Media\Enums\MediaType;
use ElegantEngineeringTech\Media\Helpers\File;
use Illuminate\Http\UploadedFile;

it('get the correct name from Uploaded file', function () {

    $file = UploadedFile::fake()->image('foo.jpg', width: 16, height: 9);

    $name = File::name($file);

    expect($name)->toBe('foo');
});

it('identity a mov file without video as an Audio', function () {

    $file = $this->getTestFile('audios/audio-only.mov');

    $type = File::type($file);

    expect($type)->toBe(MediaType::Audio);
});
