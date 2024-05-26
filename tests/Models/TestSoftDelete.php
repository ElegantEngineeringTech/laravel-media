<?php

namespace Elegantly\Media\Tests\Models;

use Elegantly\Media\Contracts\InteractWithMedia;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestSoftDelete extends Test implements InteractWithMedia
{
    use SoftDeletes;
}
