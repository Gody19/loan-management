<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait FillsTables
{
    protected const ORG_ID = 1;

    protected const ACTOR_ID = 4;

    protected const TARGET = 50;

    protected const TZ = 'Africa/Dar_es_Salaam';

    private array $tableCounts = [];

    private array $tableColumns = [];

    protected function gap(string $table, int $target = self::TARGET): int
    {
        return max(0, $target - $this->tableCount($table));
    }

    protected function bump(string $table, int $by = 1): void
    {
        $this->tableCounts[$table] = ($this->tableCounts[$table] ?? $this->tableCount($table)) + $by;
    }

    protected function tableCount(string $table): int
    {
        if (! isset($this->tableCounts[$table])) {
            $query = DB::table($table);

            if ($this->hasColumn($table, 'organization_id')) {
                $query->where('organization_id', self::ORG_ID);
            }

            $this->tableCounts[$table] = $query->count();
        }

        return $this->tableCounts[$table];
    }

    protected function hasColumn(string $table, string $column): bool
    {
        if (! isset($this->tableColumns[$table])) {
            $this->tableColumns[$table] = Schema::getColumnListing($table);
        }

        return in_array($column, $this->tableColumns[$table], true);
    }

    protected function pick(array $options): mixed
    {
        return $options[array_rand($options)];
    }

    protected function money(float|int $min, float|int $max, int $step = 5000): float
    {
        $steps = (int) max(1, floor(($max - $min) / $step));

        return round($min + fake()->numberBetween(0, $steps) * $step, 2);
    }

    protected function daysAgo(int $from, int $to = 1): Carbon
    {
        return now()->subDays(fake()->numberBetween($from, max($from, $to)));
    }

    protected function enumValue(mixed $value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    }

    protected function report(string $message): void
    {
        if (isset($this->command) && $this->command) {
            $this->command->line($message);
        }
    }
}
