<?php

namespace ElegantEngineeringTech\Media\Tests\Models;

use ElegantEngineeringTech\Media\Contracts\InteractWithMedia;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestSoftDelete extends Test implements InteractWithMedia
{
    use SoftDeletes;
}
