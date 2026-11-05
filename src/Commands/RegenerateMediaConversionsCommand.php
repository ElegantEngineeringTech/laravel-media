<?php

declare(strict_types=1);

namespace Elegantly\Media\Commands;

use Carbon\Carbon;
use Elegantly\Media\Enums\MediaConversionState;
use Elegantly\Media\MediaServiceProvider;
use Elegantly\Media\Models\MediaConversion;
use Illuminate\Console\Command;
use Laravel\Prompts\Progress;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;

class RegenerateMediaConversionsCommand extends Command
{
    public $signature = 'media-conversions:regen
                        {--conversions=* : Conversions names}
                        {--states=* : Conversions states}
                        {--models=* : Model classes}
                        {--collections=* : Collection names}
                        {--before= : Only conversions created before this date}
                        {--after= : Only conversions created after this date}
                        {--queue= : Queue}
                        {--with-force-children : Force children to be re-generated}';

    public $description = 'Regenerate existing media conversions';

    public function handle(): int
    {
        /** @var string[] $conversions */
        $conversions = (array) $this->option('conversions');
        /** @var string[] $states */
        $states = (array) $this->option('states');
        /** @var string[] $models */
        $models = array_map(
            fn (string $model): string => ltrim($model, '\\'),
            (array) $this->option('models'),
        );
        /** @var string[] $collections */
        $collections = (array) $this->option('collections');
        /** @var ?string $before */
        $before = $this->option('before');
        /** @var ?string $after */
        $after = $this->option('after');
        /** @var ?string $queue */
        $queue = $this->option('queue');
        $withForceChildren = (bool) $this->option('with-force-children');

        $states = array_map(
            fn (string $state) => MediaConversionState::from($state),
            $states,
        );

        $model = MediaServiceProvider::getMediaConversionModel();

        $query = $model::query()->with(['media.model']);

        if ($conversions) {
            $query->whereIn('conversion_name', $conversions);
        }

        if ($states) {
            $query->whereIn('state', $states);
        }

        if ($models || $collections) {
            $query->whereRelation('media', function ($query) use ($models, $collections) {
                if ($models) {
                    $query->whereIn('media.model_type', $models);
                }
                if ($collections) {
                    $query->whereIn('media.collection_name', $collections);
                }
            });
        }

        if ($before) {
            $query->where('created_at', '<=', Carbon::parse($before));
        }

        if ($after) {
            $query->where('created_at', '>=', Carbon::parse($after));
        }

        $count = $query->count();

        if ($count === 0) {
            info('Nothing to dispatch.');

            return self::SUCCESS;
        }

        if (! confirm("{$count} Media conversions found. Continue?")) {
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
