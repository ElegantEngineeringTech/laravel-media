<?php

declare(strict_types=1);

namespace Elegantly\Media\Commands;

use Elegantly\Media\Enums\MediaConversionState;
use Elegantly\Media\Models\MediaConversion;
use Illuminate\Console\Command;
use Laravel\Prompts\Progress;

use function Laravel\Prompts\confirm;

class RetryMediaConversionsCommand extends Command
{
    public $signature = 'media-conversions:retry 
                        {--conversions=* : Conversions names}
                        {--media=* : Media Ids}
                        {--models=* : Model classes}';

    public $description = 'Retry failed media conversions';

    public function handle(): int
    {
        /** @var string[] $conversions */
        $conversions = (array) $this->option('conversions');
        /** @var int[] $media */
        $media = (array) $this->option('media');
        /** @var string[] $models */
        $models = (array) $this->option('models');

        /**
         * @var class-string<MediaConversion> $model
         */
        $model = config('media.media_conversion_model');

        $query = $model::query()
            ->with(['media.model'])
            ->where('state', MediaConversionState::Failed);

        if ($conversions) {
            $query->whereIn('conversion_name', $conversions);
        }

        if ($media || $models) {
            $query->whereRelation('media', function ($query) use ($media, $models) {
                if ($media) {
                    $query->whereIn('media.id', $media);
                }
                if ($models) {
                    $query->whereIn('media.model_type', $models);
                }
            });
        }

        $count = $query->count();

        if (! confirm("{$count} failed Media conversions found. Continue?")) {
            return self::SUCCESS;
        }

        $progress = new Progress('Dispatching Media conversions', $count);

        $query->chunkById(1_000, function ($mediaConversions) use ($progress) {

            foreach ($mediaConversions as $mediaConversion) {

                $mediaConversion->media->dispatchConversion(
                    conversion: $mediaConversion->conversion_name,
                    force: true
                );

                $progress->advance();
            }

        });

        return self::SUCCESS;
    }
}
