<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('form_columns')->where('type', 'select')->orderBy('id')->each(function ($column) {
            $options = json_decode((string) $column->options, true);
            if (! is_array($options) || $options === []) {
                return;
            }

            // Already nested — skip.
            if (isset($options[0]) && is_array($options[0])) {
                return;
            }

            $nested = collect($options)
                ->map(fn ($opt) => collect(preg_split('/\r?\n/', (string) $opt))
                    ->map(fn ($s) => trim($s))
                    ->filter()
                    ->values()
                    ->all()
                )
                ->filter(fn ($row) => count($row) > 0)
                ->values()
                ->all();

            DB::table('form_columns')->where('id', $column->id)->update([
                'options' => json_encode($nested),
            ]);
        });
    }

    public function down(): void
    {
        // Reverse: flatten nested rows back to a flat array (preserves option text by joining within-row items with newlines).
        DB::table('form_columns')->where('type', 'select')->orderBy('id')->each(function ($column) {
            $options = json_decode((string) $column->options, true);
            if (! is_array($options) || $options === []) {
                return;
            }
            if (! isset($options[0]) || ! is_array($options[0])) {
                return;
            }

            $flat = collect($options)
                ->map(fn ($row) => implode("\n", $row))
                ->all();

            DB::table('form_columns')->where('id', $column->id)->update([
                'options' => json_encode($flat),
            ]);
        });
    }
};
