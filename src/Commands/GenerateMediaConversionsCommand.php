<?php

namespace Elegantly\Media\Commands;

use Elegantly\Media\Models\Media;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

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
        $models = (array) $this->option('models');
        $collections = (array) $this->option('collections');

        /**
         * @var class-string<Media> $model
         */
        $model = config('media.model');

        /** @var Collection<int, Media> */
        $media = $model::query()
            ->with(['model', 'conversions'])
            ->when(! empty($ids), fn (Builder $query) => $query->whereIn('id', $ids))
            ->when(! empty($models), fn (Builder $query) => $query->whereIn('model_type', $models))
            ->when(! empty($collections), fn (Builder $query) => $query->whereIn('collection_name', $collections))
            ->get();

        $mediaByModel = $media->countBy('model_type');

        $this->table(
            ['Model', 'Count'],
            $mediaByModel->map(function (int $count, ?string $model_type) {
                return [
                    $model_type,
                    $count,
                ];
            })
        );

        if ($pretend || ! confirm('Continue?')) {
            return self::SUCCESS;
        }

        $this->withProgressBar($media, function (Media $media) use ($conversions, $force) {

            $conversions = empty($conversions) ? array_keys($media->getConversionsDefinitions()) : $conversions;

            foreach ($conversions as $name) {
                $conversion = $media->getConversion((string) $name);

                if ($force || ! $conversion) {
                    $media->dispatchConversion($name);
                }

            }
        });

        return self::SUCCESS;
    }
}
