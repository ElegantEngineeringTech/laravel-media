<?php

declare(strict_types=1);

namespace Elegantly\Media\Tests\Models;

use Elegantly\Media\Concerns\HasMedia;
use Illuminate\Database\Eloquent\Model;

class Test extends Model
{
    use HasMedia;

    protected $table = 'tests';

    protected $guarded = [];
}
