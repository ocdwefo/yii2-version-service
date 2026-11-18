<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Infrastructure\Yii2\Console;

use Ocdwefo\VersionService\Application\VersionPruner;
use yii\base\Module;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Maintenance of the version history. Register it in the console config: `controllerMap => ['versions' => VersionsController::class]`.
 *
 *     yii versions/prune    removes the versions beyond the limit, for every schema listed in VersionPruner
 */
final class VersionsController extends Controller
{
    /** @var string */
    public $defaultAction = 'prune';

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        string $id,
        Module $module,
        private readonly VersionPruner $pruner,
        array $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    public function actionPrune(): int
    {
        $removed = $this->pruner->prune();
        $this->stdout(sprintf("Removed %d outdated version(s).\n", $removed));

        return ExitCode::OK;
    }
}
