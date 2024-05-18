<?php

namespace Finller\Media\Tests\Models;

use Finller\Media\Contracts\InteractWithMedia;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestSoftDelete extends Test implements InteractWithMedia
{
    use SoftDeletes;
}
