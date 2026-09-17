<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only audit trail: who changed which protocol field, from what to what. */
class ProtocolRevision extends Model
{
    public const UPDATED_AT = null;

    /** @var array<string, string> */
    public const FIELD_LABELS = [
        'registered_voters' => 'Upisanih birača',
        'ballots_received' => 'Primljeno listića',
        'ballots_unused' => 'Neupotrebljeno',
        'voters_voted' => 'Glasalo',
        'ballots_in_box' => 'U kutiji',
        'ballots_valid' => 'Važećih',
        'ballots_invalid' => 'Nevažećih',
        'reason' => 'Razlog',
    ];

    protected $fillable = ['protocol_id', 'user_id', 'action', 'changes', 'created_at'];

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionColor(): string
    {
        return match ((string) $this->action) {
            'created' => 'info',
            'updated' => 'gray',
            'verified' => 'success',
            'unverified' => 'warning',
            'annulled' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Filament RepeatableEntry reads this as a model attribute so the infolist
     * does not have to re-bind nested state (array diffs would otherwise dump as JSON).
     *
     * @return list<string>|null
     */
    public function getChangesSummaryAttribute(): ?array
    {
        $lines = $this->changeLines();

        return $lines === [] ? null : $lines;
    }

    /** @return list<string> */
    public function changeLines(): array
    {
        // Column is `changes`, but Eloquent already uses the `$changes` property
        // for dirty-tracking — `$this->changes` inside the class is always [].
        /** @var array<string, mixed>|null $changes */
        $changes = $this->getAttribute('changes');

        return self::formatChangeLines(is_array($changes) ? $changes : null, $this->listNamesFromChanges());
    }

    public static function formatActionLabel(string $action): string
    {
        return match ($action) {
            'created' => 'Unet',
            'updated' => 'Izmenjen',
            'verified' => 'Verifikovan',
            'unverified' => 'Vraćen na ispravku',
            'annulled' => 'Poništen',
            default => $action,
        };
    }

    /**
     * Human-readable audit lines. Filament TextEntry iterates array state
     * item-by-item, so the infolist must pass this already-formatted list.
     *
     * @param  array<string, mixed>|null  $changes
     * @param  array<int, string>  $listNames  electoral_list_id => "1. Naziv"
     * @return list<string>
     */
    public static function formatChangeLines(?array $changes, array $listNames = []): array
    {
        if ($changes === null || $changes === []) {
            return [];
        }

        $lines = [];
        foreach ($changes as $field => $pair) {
            if ($field === 'items') {
                foreach (self::itemLines(is_array($pair) ? $pair : [], $listNames) as $line) {
                    $lines[] = $line;
                }

                continue;
            }

            $label = self::FIELD_LABELS[$field] ?? (string) $field;
            if (! is_array($pair) || count($pair) < 2) {
                $lines[] = $label.': '.self::display($pair);

                continue;
            }

            $lines[] = self::pairLine($label, $pair[0], $pair[1]);
        }

        return $lines;
    }

    /**
     * @param  array<int|string, mixed>  $pair
     * @param  array<int, string>  $listNames
     * @return list<string>
     */
    private static function itemLines(array $pair, array $listNames): array
    {
        $before = self::intKeyed($pair[0] ?? null);
        $after = self::intKeyed($pair[1] ?? null);
        $ids = array_values(array_unique([...array_keys($before), ...array_keys($after)]));
        sort($ids);

        $lines = [];
        foreach ($ids as $id) {
            $old = $before[$id] ?? null;
            $new = $after[$id] ?? null;
            if ($old == $new) {
                continue;
            }
            if ($old === null && (int) $new === 0) {
                continue;
            }
            $label = $listNames[$id] ?? 'Lista #'.$id;
            $lines[] = self::pairLine($label, $old, $new);
        }

        return $lines;
    }

    private static function pairLine(string $label, mixed $old, mixed $new): string
    {
        if ($old === null || $old === '') {
            return $label.': '.self::display($new);
        }

        return $label.': '.self::display($old).' → '.self::display($new);
    }

    private static function display(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'da' : 'ne';
        }
        if (is_int($value) || (is_string($value) && is_numeric($value) && ! str_contains((string) $value, '.'))) {
            return number_format((int) $value, 0, ',', '.');
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '—';
        }

        return (string) $value;
    }

    /** @return array<int, int|string> */
    private static function intKeyed(mixed $map): array
    {
        if (! is_array($map)) {
            return [];
        }

        $out = [];
        foreach ($map as $key => $value) {
            $out[(int) $key] = $value;
        }

        return $out;
    }

    /** Votes from the last snapshot, for protocols whose items relation is empty. */
    public function itemSnapshotLines(): array
    {
        $after = $this->getAttribute('changes')['items'][1] ?? null;
        if (! is_array($after) || $after === []) {
            return [];
        }

        $names = $this->listNamesFromChanges();
        $lines = [];
        foreach (self::intKeyed($after) as $id => $votes) {
            if ((int) $votes === 0) {
                continue;
            }
            $label = $names[$id] ?? 'Lista #'.$id;
            $lines[] = $label.' — '.self::display($votes);
        }

        return $lines;
    }

    /** @return array<int, string> */
    private function listNamesFromChanges(): array
    {
        $items = $this->getAttribute('changes')['items'] ?? null;
        if (! is_array($items)) {
            return [];
        }

        $ids = array_values(array_unique([
            ...array_keys(self::intKeyed($items[0] ?? null)),
            ...array_keys(self::intKeyed($items[1] ?? null)),
        ]));
        if ($ids === []) {
            return [];
        }

        $names = [];
        foreach (ElectoralList::query()->whereIn('id', $ids)->get() as $list) {
            $label = trim($list->number.'. '.$list->name);
            $names[$list->id] = $label;
            $names[(int) $list->number] = $label;
        }

        $missing = array_values(array_diff($ids, array_keys($names)));
        if ($missing === []) {
            return $names;
        }

        $electionId = Protocol::query()->whereKey($this->getAttribute('protocol_id'))->value('election_id');
        if (! $electionId) {
            return $names;
        }

        foreach (ElectoralList::query()->where('election_id', $electionId)->whereIn('number', $missing)->get() as $list) {
            $label = trim($list->number.'. '.$list->name);
            $names[(int) $list->number] = $label;
            $names[$list->id] = $label;
        }

        return $names;
    }
}
