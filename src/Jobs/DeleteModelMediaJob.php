<?php

namespace ElegantEngineeringTech\Media\Jobs;

use ElegantEngineeringTech\Media\Models\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Deleting a lot of media can take some time
 * In might even fail
 */
class DeleteModelMediaJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Media $media)
    {
        $this->media = $media->withoutRelations();

        $this->onConnection(config('media.queue_connection'));
        $this->onQueue(config('media.queue'));
    }

    public function uniqueId()
    {
        return (string) $this->media->id;
    }

    public function handle()
    {
        $this->media->delete();
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array
     */
    public function tags()
    {
        return [
            'media',
            get_class($this),
        ];
    }
}
