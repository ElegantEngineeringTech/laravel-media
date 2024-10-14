<?php

namespace Elegantly\Media\Commands;

use Elegantly\Media\Models\Media;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Prompts\Progress;

use function Laravel\Prompts\confirm;

class GenerateMediaConversionsCommand extends Command
{
    public $signature = 'media:generate-conversions {ids?*} {--force} {--pretend} {--conversions=*} {--collections=*} {--models=*}';

    public $description = 'Generate all media conversions';

    public function handle(): int
    {
        $ids = (array) $this->argument('ids');
        $force = (bool) $this->option('force');
        $pretend = (bool) $this->option('pretend');
        /** @var string[] $conversions */
        $conversions = (array) $this->option('conversions');
        /** @var string[] $models */
        $models = (array) $this->option('models');
        /** @var string[] $collections */
        $collections = (array) $this->option('collections');

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

        if ($pretend || ! confirm("{$count} Media found. Continue?")) {
            return self::SUCCESS;
        }

        $progress = new Progress('Dispatching Media conversions', $count);

        $query->chunkById(5_000, function ($items) use ($progress, $force, $conversions) {

            foreach ($items as $media) {
                /** @var Media $media */
                $media->dispatchConversions(
                    queued: true,
                    filter: function ($definition) use ($media, $force, $conversions) {

                        if (! $definition->immediate) {
                            return false;
                        }

                        if (
                            ! empty($conversions) &&
                            ! in_array($definition->name, $conversions)
                        ) {
                            return false;
                        }

                        if (
                            ! $force &&
                            $media->hasConversion($definition->name)
                        ) {
                            return false;
                        }

                        return true;
                    }
                );

                $progress->advance();
            }

        });

        return self::SUCCESS;
    }
}
