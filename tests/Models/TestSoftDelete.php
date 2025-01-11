<?php

declare(strict_types=1);

namespace Elegantly\Media\Tests\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class TestSoftDelete extends Test
{
    use SoftDeletes;
}
