<?php

use Finller\Media\Helpers\File;
use Illuminate\Http\UploadedFile;

it('get the correct name from Uploaded file', function () {

    $file = UploadedFile::fake()->image('foo.jpg', width: 16, height: 9);

    $name = File::name($file);

    expect($name)->toBe('foo');
});
