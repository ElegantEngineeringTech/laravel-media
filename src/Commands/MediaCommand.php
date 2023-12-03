<?php

namespace Finller\Media\Commands;

use Illuminate\Console\Command;

class MediaCommand extends Command
{
    public $signature = 'laravel-media';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
