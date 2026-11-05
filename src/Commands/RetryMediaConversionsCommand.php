<?php

declare(strict_types=1);

namespace Elegantly\Media\Commands;

use Elegantly\Media\Enums\MediaConversionState;
use Elegantly\Media\MediaServiceProvider;
use Elegantly\Media\Models\MediaConversion;
use Illuminate\Console\Command;
use Laravel\Prompts\Progress;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;

class RetryMediaConversionsCommand extends Command
{
    public $signature = 'media-conversions:retry 
                        {--conversions=* : Conversions names}
                        {--media=* : Media Ids}
                        {--models=* : Model classes}
                        {--collections=* : Collection names}
                        {--queue= : Queue}
                        {--with-force-children : Force children to be re-generated}';

    public $description = 'Retry failed media conversions';

    public function handle(): int
    {
        /** @var string[] $conversions */
        $conversions = (array) $this->option('conversions');
        /** @var int[] $media */
        $media = (array) $this->option('media');
        /** @var string[] $models */
        $models = array_map(
            fn (string $model): string => ltrim($model, '\\'),
            (array) $this->option('models'),
        );
        /** @var string[] $collections */
        $collections = (array) $this->option('collections');
        /** @var ?string $queue */
        $queue = $this->option('queue');
        $withForceChildren = (bool) $this->option('with-force-children');

        $model = MediaServiceProvider::getMediaConversionModel();

        $query = $model::query()
            ->with(['media.model'])
            ->where('state', MediaConversionState::Failed);

        if ($conversions) {
            $query->whereIn('conversion_name', $conversions);
        }

        if ($media || $models || $collections) {
            $query->whereRelation('media', function ($query) use ($media, $models, $collections) {
                if ($media) {
                    $query->whereIn('media.id', $media);
                }
                if ($models) {
                    $query->whereIn('media.model_type', $models);
                }
                if ($collections) {
                    $query->whereIn('media.collection_name', $collections);
                }
            });
        }

        $count = $query->count();

        if ($count === 0) {
            info('Nothing to dispatch.');

            return self::SUCCESS;
        }

        if (! confirm("{$count} failed Media conversions found. Continue?")) {
            return self::SUCCESS;
        }

        $progress = new Progress('Dispatching Media conversions', $count);

        $query->chunkById(1_000, function ($mediaConversions) use ($progress, $queue, $withForceChildren) {

            /** @var MediaConversion $mediaConversion */
            foreach ($mediaConversions as $mediaConversion) {

                $mediaConversion->media->dispatchConversion(
                    conversion: $mediaConversion->conversion_name,
                    force: true,
                    withChildren: true,
                    withForceChildren: $withForceChildren,
                    queue: $queue,
                );

                $progress->advance();
            }

        });

        return self::SUCCESS;
    }
}
