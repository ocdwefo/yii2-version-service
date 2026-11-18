# ocdwefo/yii2-version-service

Entity versioning for Yii 2: store the state of an entity together with its relations, list its history,
open an old version in the usual form and restore it.

The package is deliberately small. It stores and returns versions; everything else stays with the project:

| The package does | The project does |
|---|---|
| Takes a snapshot of the fields described in a schema, relations included | Decides **when** to record a version: an explicit call, no ORM hooks |
| Numbers versions per entity (1, 2, 3…) and gives each a UUID v7 | Decides **whether** anything changed: `record()` always stores a new version |
| Returns the history, a version with its stored values, a copy of the entity with old values | Renders everything and compares versions: there is no UI and no diff |
| Removes versions beyond a limit | Saves the entity: the package never writes to the project's tables |

## Requirements

- PHP 8.5 with `ext-mbstring`
- Yii 2.0.55+
- MySQL 5.7.8+ (JSON column). Other databases of `yii\db` should work, but only MySQL is tested.

## Quick start

### 1. Install and migrate

```json
{
    "repositories": [
        {"type": "vcs", "url": "https://github.com/ocdwefo/yii2-version-service"}
    ],
    "require": {
        "ocdwefo/yii2-version-service": "^1.0"
    }
}
```

Add the migration namespace to the console config and run `yii migrate`; it creates the table `{{%version}}`:

```php
'controllerMap' => [
    'migrate' => [
        'class' => \yii\console\controllers\MigrateController::class,
        'migrationNamespaces' => ['Ocdwefo\VersionService\Infrastructure\Yii2\Migration'],
    ],
],
```

For another table name extend `M261117000000CreateVersionTable` in the project, override `$tableName` and pass
the same name to `DbVersionRepository` (see [Replacing parts](#replacing-parts)).

### 2. Configure the container

Plain DI definitions, no component and no factory. Put them into the config shared by the web and console
applications:

```php
use Psr\Clock\ClockInterface;
use Ocdwefo\VersionService\Application\AuthorResolverInterface;
use Ocdwefo\VersionService\Application\VersionPruner;
use Ocdwefo\VersionService\Application\VersionService;
use Ocdwefo\VersionService\Domain\Repository\VersionRepositoryInterface;
use Ocdwefo\VersionService\Domain\Schema\FieldAccessorInterface;
use Ocdwefo\VersionService\Infrastructure\SystemClock;
use Ocdwefo\VersionService\Infrastructure\Yii2\DbVersionRepository;
use Ocdwefo\VersionService\Infrastructure\Yii2\Schema\ActiveRecordAccessor;
use Ocdwefo\VersionService\Infrastructure\Yii2\YiiUserAuthorResolver;

'container' => [
    'singletons' => [
        VersionRepositoryInterface::class => DbVersionRepository::class,     // table {{%version}}, component db
        AuthorResolverInterface::class => YiiUserAuthorResolver::class,      // the logged in user
        ClockInterface::class => SystemClock::class,
        FieldAccessorInterface::class => ActiveRecordAccessor::class,        // ActiveRecord and plain objects
        VersionService::class => VersionService::class,

        // Optional, see Pruning
        VersionPruner::class => ['__construct()' => [
            'keepVersions' => 20,
            'schemas' => [\app\versioning\ArticleSchema::class],
        ]],
    ],
],
```

Nothing is built until `VersionService` is first injected, so requests that do not use versions pay nothing.

### 3. List the versioned entities

One int backed enum of the project gives every versioned kind of entity a code:

```php
enum VersionType: int
{
    case Article = 1;
    case Template = 2;
}
```

> The code is stored with every version. **Never change a value** once versions exist, and never give a removed
> value to another entity: its old versions would move to that entity.

### 4. Describe the entity with a schema

```php
use Ocdwefo\VersionService\Domain\Schema\Field;
use Ocdwefo\VersionService\Domain\Schema\RelationSchema;
use Ocdwefo\VersionService\Domain\Schema\RootSchema;

/** @extends RootSchema<Article> */
final class ArticleSchema extends RootSchema
{
    public function entityType(): VersionType { return VersionType::Article; }
    public function entityClass(): string { return Article::class; }

    protected function fields(): array
    {
        return [
            Field::string('title'),
            Field::int('status'),
            Field::string('published_at')->nullable(),
            Field::collection('translations', ArticleTranslationSchema::class),
        ];
    }
}

/** @extends RelationSchema<ArticleTranslation> */
final class ArticleTranslationSchema extends RelationSchema
{
    public function entityClass(): string { return ArticleTranslation::class; }

    protected function fields(): array
    {
        return [Field::string('locale'), Field::string('title')];
    }
}
```

### 5. Record versions

```php
final class ArticleService
{
    /** @var EntityVersions<Article> */
    private readonly EntityVersions $versions;

    public function __construct(VersionService $versionService)
    {
        $this->versions = $versionService->of(new ArticleSchema());   // once
    }

    public function update(Article $article, ArticleForm $form): void
    {
        Yii::$app->db->transaction(function () use ($article, $form): void {
            $this->save($article, $form);                         // the project saves the article
            $this->versions->record($article, $form->comment);    // then the version, in the same transaction
        });
    }
}
```

## Schemas

A schema lists the fields that are versioned: only what the user edits. Counters, statuses owned by other
processes and timestamps do not belong there. A schema is a description, its constructor takes no arguments.

Two base classes, chosen by one question: **does the class have a history of its own?**

| Base class | For | Methods |
|---|---|---|
| `RootSchema` | the entity passed to `$service->of()` | `entityType()`, `entityClass()`, `fields()`; optional `idField()` (default `'id'`, must hold an int) and `keepVersions()` |
| `RelationSchema` | a class inside the entity, reached through `object` / `collection` | `entityClass()`, `fields()` |

Both may override `create()` when the class needs constructor arguments. Whether a class is an ActiveRecord model
or a plain object does not matter: the configured accessor reaches both.

### Field types

| Field | Value in the object |
|---|---|
| `Field::string('title')` | `string`. ActiveRecord dates and DECIMAL columns are strings too |
| `Field::int('status')` | `int` |
| `Field::float('price')` | `float`; an `int` is accepted |
| `Field::bool('active')` | `bool` |
| `Field::array('tags')` | array of plain values, e.g. a JSON column |
| `Field::object('seo', SeoSchema::class)` | one related object |
| `Field::collection('translations', TranslationSchema::class)` | list of related objects, never null: no items is `[]` |

- A field is not nullable unless marked `->nullable()`.
- `object` and `collection` accept only a `RelationSchema`. A schema may contain itself, e.g. replies of a
  comment; the data decides the depth.

### Field names

The name is used **exactly as written**, nothing is converted:

- ActiveRecord attribute: the column name, `published_at`;
- ActiveRecord relation: the relation name, `translations` for `getTranslations()`;
- any other object: `metaTitle` reaches `getMetaTitle()` / `isMetaTitle()` / `setMetaTitle()` or `$metaTitle`
  (`meta_title` reaches none of them).

Anything else is described explicitly. This is also how date and enum objects are stored, since there are no such
field types:

```php
Field::string('publishedAt')->nullable()
    ->readUsing(fn (Article $a): ?string => $a->publishedAt?->format('Y-m-d H:i:s'))
    ->writeUsing(fn (Article $a, ?string $v) => $a->publishedAt = $v === null ? null : new DateTimeImmutable($v)),
Field::string('status')
    ->readUsing(fn (Article $a): string => $a->status->value)
    ->writeUsing(fn (Article $a, string $v) => $a->status = Status::from($v)),
```

The name is the key in stored versions: when a method of the class is renamed, keep the field name and describe
the new method with `readUsing()` / `writeUsing()`.

### Changing a schema later

Old versions stay readable: a field missing in an old version keeps the current value on checkout, a stored field
the schema no longer has is ignored, a collection is replaced as a whole.

## Using versions

| `EntityVersions` method | Returns |
|---|---|
| `record($article, 'Fixed the title')` | `VersionView` of the new version. Third argument `authorId`, see [Author](#author) |
| `history($article->id, limit: 20, offset: 0)` | `VersionPage`: `items` (`VersionSummary`, newest first) and `total`. Metadata only, snapshots are not loaded |
| `get($article->id, $versionId)` | `VersionView`: `id`, `number`, `authorId`, `createdAt`, `comment` and `fields`, the stored plain values (a relation is an array) |
| `checkout($article, $versionId)` | A **copy** of `$article` with the values of the version. `$article` stays untouched, nothing is saved |
| `removeAll($article->id)` | Number of removed versions: call it when the entity itself is deleted |

Versions are addressed by their UUID (`$view->id`); `number` is for people. A version of another entity is
reported as not found.

### Showing and restoring an old version

```php
public function actionVersion(int $id, string $versionId): string
{
    $article = $this->findArticle($id);
    try {
        $old = $this->versions->checkout($article, $versionId);
    } catch (VersionNotFoundException | InvalidValueException) {   // unknown, foreign or malformed id
        throw new NotFoundHttpException();
    }

    return $this->render('update', ['form' => new ArticleForm($old), 'readOnly' => true]);
}

public function actionRestore(int $id, string $versionId): Response
{
    $article = $this->findArticle($id);
    $old = $this->versions->checkout($article, $versionId);
    $number = $this->versions->get($article->id, $versionId)->number;

    Yii::$app->db->transaction(function () use ($old, $number): void {
        $this->writer->saveRestored($old);   // the project's save code
        $this->versions->record($old, "Restored version $number");
    });

    return $this->redirect(['update', 'id' => $id]);
}
```

What the copy holds:

- Fields outside the schema, the primary key and `isNewRecord = false` come from the current entity, so `save()`
  updates it.
- Relations are **new, unsaved** objects with the old values. The project decides how to write them, typically:
  delete the current translations, insert these.
- The copy is made with `clone`, and Yii drops event handlers attached at run time with `$model->on(...)`.
  Behaviors from `behaviors()` work. If saving relies on such a handler, attach it again or copy the values into a
  freshly loaded model: `$fresh->setAttributes($old->getAttributes(), false)`.

The package stores author ids only; names come from the project's own user table.

## Transactions

The package opens no transactions: wrap saving the entity and `record()` in one transaction of the project, so a
version never describes a state that was rolled back.

Numbers are taken with `MAX(number) + 1` and guarded by a unique index. When concurrent requests take the same
number, `record()` retries up to three times, then throws `VersionConflictException`. On PostgreSQL a failed insert
aborts the surrounding transaction, so there the project repeats the whole operation instead.

## Author

Every version has an author id: the `authorId` argument of `record()`, otherwise the logged in user. There is no
default author. Where nobody is logged in (console, cron, import, a guest) pass it explicitly, or `record()` throws
`UnknownAuthorException`:

```php
$versions->record($article, 'Imported from CRM', authorId: $importUserId);
```

User ids must be integers: `getId()` of the identity class returns `int`.

## Pruning

Optional, but without it versions are never removed. `VersionPruner` (see the configuration above) keeps the newest
versions of every entity of the listed schemas: `keepVersions` of it, or `keepVersions()` of the schema. The
latest version is never removed; schemas missing from the list are not pruned.

Register the command in the console config and run it from cron:

```php
'controllerMap' => [
    'versions' => \Ocdwefo\VersionService\Infrastructure\Yii2\Console\VersionsController::class,
],
```

```cron
15 3 * * * php /path/to/yii versions/prune
```

From code: inject `VersionPruner` and call `prune()`.

## Errors

Every exception extends `Ocdwefo\VersionService\Domain\Exception\VersioningException`.

| Exception | When |
|---|---|
| `InvalidValueException` | Empty or too long comment (max 255), a field value of the wrong type, `null` in a not nullable field, malformed version id |
| `InvalidSchemaException` | A schema is wrong: unknown field, a relation to a root schema, two schemas with one entity type… |
| `VersionNotFoundException` | No such version, or it belongs to another entity |
| `VersionConflictException` | Concurrent writes took the version number on every attempt |
| `UnknownAuthorException` | `record()` without `authorId` where nobody is logged in |

## Replacing parts

Every definition of the configuration can point to the project's own class.

| Definition | Change it to |
|---|---|
| `VersionRepositoryInterface` | another connection or table: `['class' => DbVersionRepository::class, '__construct()' => ['db' => 'historyDb', 'table' => 'ms_versions']]`; another storage: see below |
| `AuthorResolverInterface` | another user component: `['class' => YiiUserAuthorResolver::class, '__construct()' => ['component' => 'account']]`; another source: own class, `current()` throws `UnknownAuthorException` when there is nobody |
| `FieldAccessorInterface` | a project without ActiveRecord: `ObjectAccessor`; other kinds of objects: own class, one for the whole project |
| `ClockInterface` | any PSR-20 clock |

Another storage implements `VersionRepositoryInterface` and is checked with the shared contract test:

```php
final class MyVersionRepositoryTest extends \Ocdwefo\VersionService\Testing\VersionRepositoryContractTestCase
{
    protected function createRepository(): VersionRepositoryInterface
    {
        return new MyVersionRepository(/* a clean storage */);
    }
}
```

## Development

```bash
composer install
composer check              # composer validate, deptrac, PHPStan (level max), all tests
composer test:unit          # no database needed
composer test:integration   # skipped without a database
```

Integration tests create and drop their own tables in an empty database given by `VERSION_SERVICE_TEST_DSN`,
`VERSION_SERVICE_TEST_USER` and `VERSION_SERVICE_TEST_PASSWORD`: export them, or copy `phpunit.xml.dist` to
`phpunit.xml` (ignored by git) and set them in `<php><env .../></php>`.

`src/` must stay parseable by the PHP parser bundled with deptrac: write `(new Foo())->bar()`, not
`new Foo()->bar()`, and avoid `clone with` and the pipe operator there.

## License

MIT, see [LICENSE](LICENSE).
