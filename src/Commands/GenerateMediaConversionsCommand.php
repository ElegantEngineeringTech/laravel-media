<?php

declare(strict_types=1);

namespace Elegantly\Media\Commands;

use Elegantly\Media\MediaConversionDefinition;
use Elegantly\Media\Models\Media;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Prompts\Progress;

use function Laravel\Prompts\confirm;

class GenerateMediaConversionsCommand extends Command
{
    public $signature = 'media:generate-conversions {ids?*} {--force} {--immediate} {--conversions=*} {--collections=*} {--models=*}';

    public $description = 'Generate all media conversions';

    public function handle(): int
    {
        $ids = (array) $this->argument('ids');
        $immediate = (bool) $this->option('immediate');
        $force = (bool) $this->option('force');
        /** @var string[] $conversions */
        $conversions = (array) $this->option('conversions');
        /** @var string[] $models */
        $models = (array) $this->option('models');
        /** @var string[] $collections */
        $collections = (array) $this->option('collections');

        $filter = function (MediaConversionDefinition $definition) use ($immediate) {

            if ($immediate) {
                return true;
            }

            return $definition->immediate;
        };

        /**
         * @var class-string<Media> $model
         */
        $model = config('media.model');

        $query = $model::query()
            ->with(['model', 'conversions'])
            ->when(! empty($ids), fn (Builder $query) => $query->whereIn('id', $ids))
            ->when(! empty($models), fn (Builder $query) => $query->whereIn('model_type', $models))
            ->when(! empty($collections), fn (Builder $query) => $query->whereIn('collection_name', $collections));

        $count = $query->count();

        if (! confirm("{$count} Media found. Continue?")) {
            return self::SUCCESS;
        }

        $progress = new Progress('Dispatching Media conversions', $count);

        $query->chunkById(5_000, function ($items) use ($progress, $force, $conversions, $filter) {

            foreach ($items as $media) {
                /** @var Media $media */
                if (! empty($conversions)) {
                    foreach ($conversions as $conversion) {
                        $media->dispatchConversion(
                            conversion: $conversion,
                            force: $force
                        );
                    }
                } else {

                    /**
                     * Generate missing children conversions
                     */
                    $media->conversions->each(function ($conversion) use ($media, $force, $filter) {
                        $media->generateConversions(
                            parent: $conversion,
                            queued: true,
                            force: $force,
                            filter: $filter,
                        );
                    });

                    /**
                     * Generate missing root conversions
                     */
                    $media->generateConversions(
                        queued: true,
                        force: $force,
                        filter: $filter,
                    );
                }

                $progress->advance();
            }

        });

        return self::SUCCESS;
    }
}
