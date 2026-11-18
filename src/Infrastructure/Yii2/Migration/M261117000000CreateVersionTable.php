<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Infrastructure\Yii2\Migration;

use yii\base\InvalidConfigException;
use yii\db\Connection;
use yii\db\Migration;

/**
 * Table of {@see \Ocdwefo\VersionService\Infrastructure\Yii2\DbVersionRepository}.
 *
 * Register the namespace in the console config:
 *
 * ```php
 * 'controllerMap' => [
 *     'migrate' => [
 *         'class' => \yii\console\controllers\MigrateController::class,
 *         'migrationNamespaces' => ['Ocdwefo\VersionService\Infrastructure\Yii2\Migration'],
 *     ],
 * ],
 * ```
 *
 * For another table name extend the class in the project and override {@see self::$tableName}.
 * Needs MySQL 5.7.8+ (JSON column) or any other database Yii supports.
 */
class M261117000000CreateVersionTable extends Migration
{
    public string $tableName = '{{%version}}';

    public function safeUp(): void
    {
        $this->createTable($this->tableName, [
            'id' => $this->char(36)->notNull(),
            'entity_type' => $this->smallInteger()->unsigned()->notNull(),
            'entity_id' => $this->bigInteger()->unsigned()->notNull(),
            'number' => $this->integer()->unsigned()->notNull(),
            'snapshot' => $this->json()->notNull(),
            'comment' => $this->string(255)->notNull(),
            'author_id' => $this->bigInteger()->unsigned()->notNull(),
            'created_at' => $this->dateTime(6)->notNull(),
            'PRIMARY KEY ([[id]])',
        ], $this->connection()->driverName === 'mysql' ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB' : null);

        $name = $this->connection()->getSchema()->getRawTableName($this->tableName);

        // Keeps version numbers unique per entity (a concurrent duplicate fails and is retried) and serves
        // every query of the repository: history, count, next number, pruning, removal.
        $this->createIndex("ux_{$name}_entity_number", $this->tableName, ['entity_type', 'entity_id', 'number'], true);
    }

    public function safeDown(): void
    {
        $this->dropTable($this->tableName);
    }

    /**
     * The connection, resolved by Migration::init() from the configured id or config.
     */
    private function connection(): Connection
    {
        if (!$this->db instanceof Connection) {
            throw new InvalidConfigException('Migration database connection is not initialized.');
        }

        return $this->db;
    }
}
