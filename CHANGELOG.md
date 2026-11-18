# Changelog

## 1.0.0 (2026-11-18)

First release.

- Versions of an entity recorded by an explicit call: a snapshot of the fields described in a schema, relations
  (`object`, `collection`) at any depth, a schema may contain itself (replies, subcategories), a required comment and author, a per-entity number and a UUID v7.
- Schemas with typed fields: string, int, float, bool, array, object, collection (dates and enums are plain values); `nullable()`,
  `readUsing()` / `writeUsing()`; two base classes: `RootSchema` for the entity, `RelationSchema` for its parts.
- Reading: history with paging (metadata only, snapshots are not loaded), a version with its stored plain values, `checkout()` returning a copy of the entity with the
  values of a version (restore = checkout + save by the project).
- Pruning (optional): `VersionPruner` keeps the newest N versions per entity, N given to it or per schema; `yii versions/prune`.
- Storage in any database supported by `yii\db`, with a migration; in-memory storage for tests;
  a contract test case for custom storages.
- Yii 2 integration through plain DI container definitions (no component, no factory): `VersionService` is built
  on first use, every part (storage, author, clock, field accessor) can be replaced; one
  `ActiveRecordAccessor` reaches ActiveRecord models and plain objects alike; the author is the logged in user
  or the one passed to `record()`, there is no default author; `VersionsController` for pruning.
