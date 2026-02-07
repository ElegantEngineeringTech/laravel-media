<?php

declare(strict_types=1);

namespace Elegantly\Media\Commands;

use Elegantly\Media\Models\Media;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Prompts\Progress;

use function Laravel\Prompts\confirm;

class GenerateMediaConversionsCommand extends Command
{
    public $signature = 'media:generate-conversions 
                        {ids?* : Media Ids}
                        {--queue : Queue}
                        {--force : Replace existing conversions}
                        {--with-children : Generate children conversions}
                        {--with-force-children : Force children to be re-generated}
                        {--immediate : Only generates immediate conversions}
                        {--conversions=* : Conversions names}
                        {--collections=* : Collection names}
                        {--models=* : Models class}';

    public $description = 'Generate media conversions';

    public function handle(): int
    {
        $ids = (array) $this->argument('ids');
        $force = (bool) $this->option('force');
        /** @var ?string $queue */
        $queue = $this->option('queue');
        $withChildren = (bool) $this->option('with-children');
        $withForceChildren = (bool) $this->option('with-force-children');
        $immediate = (bool) $this->option('immediate');
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
            ->when($ids, fn (Builder $query) => $query->whereIn('id', $ids))
            ->when($models, fn (Builder $query) => $query->whereIn('model_type', $models))
            ->when($collections, fn (Builder $query) => $query->whereIn('collection_name', $collections));

        $count = $query->count();

        if (! confirm("{$count} Media found. Continue?")) {
            return self::SUCCESS;
        }

        $progress = new Progress('Dispatching Media conversions', $count);

        $query->chunkById(1_000, function ($media) use ($progress, $queue, $force, $immediate, $conversions, $withChildren, $withForceChildren) {

            /** @var Media $medium */
            foreach ($media as $medium) {

                if ($conversions) {
                    foreach ($conversions as $conversion) {
                        $medium->dispatchConversion(
                            conversion: $conversion,
                            force: $force,
                            withChildren: $withChildren,
                            withForceChildren: $withForceChildren,
                            queue: $queue,
                        );
                    }
                } else {
                    $medium->generateConversions(
                        filter: fn ($definition) => $immediate ? $definition->immediate : true,
                        queued: true,
                        force: $force,
                        withChildren: $withChildren,
                        withForceChildren: $withForceChildren,
                    );
                }

                $progress->advance();
            }

        });

        return self::SUCCESS;
    }
}
