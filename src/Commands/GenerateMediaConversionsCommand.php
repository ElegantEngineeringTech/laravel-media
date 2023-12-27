<?php

namespace Finller\Media\Commands;

use Finller\Media\Models\Media;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class GenerateMediaConversionsCommand extends Command
{
    public $signature = 'media:generate-conversions {ids?*} {--force} {--pretend} {--conversions=*} {--models=*}';

    public $description = 'Generate all media conversions';

    public function handle(): int
    {
        $ids = (array) $this->argument('ids');
        $force = (bool) $this->option('force');
        $pretend = (bool) $this->option('pretend');
        $conversions = (array) $this->option('conversions');
        $models = (array) $this->option('models');

        $model = config('media.model');

        /** @var Collection<int, Media> */
        $media = $model::query()
            ->with(['model'])
            ->when(! empty($ids), fn (Builder $query) => $query->whereIn('id', $ids))
            ->when(! empty($models), fn (Builder $query) => $query->whereIn('model_type', $models))
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

        if ($pretend) {
            return self::SUCCESS;
        }

        $this->withProgressBar($media, function (Media $media) use ($conversions, $force) {
            $model = $media->model;
            $modelConversions = $model->getMediaConversions($media); // @phpstan-ignore-line

            $conversions = empty($conversions) ? $modelConversions : array_intersect($modelConversions, $conversions);

            foreach ($conversions as $conversion) {
                if ($force || ! $media->hasGeneratedConversion($conversion)) {
                    $model->dispatchConversion($media, $conversion); // @phpstan-ignore-line
                }
            }
        });

        return self::SUCCESS;
    }
}
