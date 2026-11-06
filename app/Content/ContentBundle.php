<?php

namespace App\Content;

use App\Support\Runtime;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lightwalker\Sync\SyncRegistry;

/**
 * The universal ITPs and wildcards that ship inside the mobile build (content bundle contract),
 * so they show offline and before sign-in.
 *
 * Whenever the bundled file differs from the one last applied, its rows are written to the
 * phone's database through the models. A bundled row is written only when the phone has no row
 * with that id, or the phone's row is older (`updated_at`), so an older bundle never overwrites
 * newer pulled content. Sync cursors are left alone: the first online pull still fetches every
 * row once, and is incremental after that.
 *
 * Cheap enough for every request: unless the file's size or modified time changed since it was
 * last checked, this is one query and a stat. Nothing is kept in memory between requests, since
 * the phone keeps one app booted across them.
 */
class ContentBundle
{
    /**
     * The `device_settings` row recording the applied bundle: `{signature, hash}`.
     */
    public const SETTING = 'content_bundle';

    /**
     * Columns the bundle never writes: the cursor position and the client's own change tracking.
     */
    protected const EXCLUDED_COLUMNS = ['sync_seq', 'sync_dirty', 'sync_rev'];

    public function __construct(protected SyncRegistry $registry) {}

    /**
     * Apply the bundle if it changed since the last time. Mobile only: the web build reads the
     * shared database, which already holds this content.
     *
     * @return bool whether rows were applied
     *
     * @throws \JsonException when the bundle isn't valid JSON
     */
    public function applyIfChanged(): bool
    {
        $path = (string) config('itp.content_bundle');

        if (Runtime::isServer() || ! is_file($path)) {
            return false;
        }

        clearstatcache(true, $path);
        $signature = filemtime($path).':'.filesize($path);
        $applied = $this->appliedState();

        if (($applied['signature'] ?? null) === $signature) {
            return false;
        }

        $hash = hash_file('sha256', $path);
        $changed = ($applied['hash'] ?? null) !== $hash;

        if ($changed) {
            $this->apply(json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR));
        }

        $this->storeAppliedState(['signature' => $signature, 'hash' => $hash]);

        return $changed;
    }

    /**
     * Write a snapshot's rows, type by type, for the `global` sync types it holds.
     *
     * @param  array{types?: array<string, list<array<string, mixed>>>}  $snapshot
     */
    public function apply(array $snapshot): void
    {
        foreach ($snapshot['types'] ?? [] as $type => $rows) {
            if (! $this->registry->hasType($type) || $this->registry->definition($type)['scope'] !== 'global') {
                continue;
            }

            $model = $this->registry->modelFor($type);
            $prototype = new $model;

            $prototype->getConnection()->transaction(function () use ($prototype, $rows): void {
                $columns = array_diff(Schema::connection($prototype->getConnectionName())->getColumnListing($prototype->getTable()), self::EXCLUDED_COLUMNS);

                foreach ($rows as $row) {
                    $this->applyRow($prototype, $this->rawAttributes($prototype, Arr::only($row, $columns)));
                }
            });
        }
    }

    /**
     * Write one row unless the phone already has the same or a newer version of it. Raw values,
     * as a sync pull would write them; the model's save keeps the write on the documented path.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function applyRow(Model $prototype, array $attributes): void
    {
        $id = $attributes[$prototype->getKeyName()] ?? null;

        if ($id === null) {
            return;
        }

        $existing = $prototype->newQueryWithoutScopes()->find($id);
        $bundledAt = $attributes[$prototype->getUpdatedAtColumn()] ?? null;

        if ($existing !== null && ! $this->isOlder($existing->getAttribute($prototype->getUpdatedAtColumn()), $bundledAt)) {
            return;
        }

        $model = $existing ?? $prototype->newInstance();
        $model->setRawAttributes([...$model->getAttributes(), ...$attributes]);
        $model->timestamps = false;
        $model->save();
    }

    /**
     * Whether the phone's row is older than the bundled one. A bundled row without a time never
     * replaces a row the phone has.
     */
    protected function isOlder(?CarbonInterface $local, ?string $bundled): bool
    {
        if ($bundled === null) {
            return false;
        }

        return $local === null || $local->lt(Carbon::parse($bundled));
    }

    /**
     * Raw column values: JSON columns as JSON text, times in the database's format, whichever
     * form the export wrote them in.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function rawAttributes(Model $prototype, array $row): array
    {
        $times = [$prototype->getCreatedAtColumn(), $prototype->getUpdatedAtColumn(), 'deleted_at'];

        foreach ($row as $column => $value) {
            if (is_array($value)) {
                $row[$column] = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif (is_bool($value)) {
                $row[$column] = (int) $value;
            } elseif ($value !== null && in_array($column, $times, true)) {
                $row[$column] = $prototype->fromDateTime(Carbon::parse($value));
            }
        }

        return $row;
    }

    /**
     * @return array{signature?: string, hash?: string}
     */
    protected function appliedState(): array
    {
        $value = DB::table('device_settings')->where('key', self::SETTING)->value('value');

        return is_string($value) ? (array) json_decode($value, true) : [];
    }

    /**
     * @param  array{signature: string, hash: string}  $state
     */
    protected function storeAppliedState(array $state): void
    {
        DB::table('device_settings')->upsert(
            [['key' => self::SETTING, 'value' => json_encode($state), 'created_at' => now(), 'updated_at' => now()]],
            ['key'],
            ['value', 'updated_at'],
        );
    }
}
