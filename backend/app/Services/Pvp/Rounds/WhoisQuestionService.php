<?php

namespace App\Services\Pvp\Rounds;

use Illuminate\Support\Arr;

readonly class WhoisQuestionService
{
    public function __construct(private HintValueService $hints)
    {
    }

    public function validate(array $question, string $game): array
    {
        $key = (string) ($question['key'] ?? '');
        $op = (string) ($question['op'] ?? '');
        $value = $question['value'] ?? null;

        $allowedKeys = (array) config("pvp.whois.keys.{$game}", []);
        if (!in_array($key, $allowedKeys, true)) {
            abort(422, 'Invalid question key.');
        }

        $meta = (array) config("pvp.whois.meta.{$key}", []);
        $type = (string) Arr::get($meta, 'type', 'enum');
        $allowedOps = (array) Arr::get($meta, 'ops', ['eq']);

        if (!in_array($op, $allowedOps, true)) {
            abort(422, 'Invalid question operator.');
        }

        if ($value === null) {
            abort(422, 'Invalid question value.');
        }

        $cast = (string) Arr::get($meta, 'cast', 'string');
        $value = $this->castValue($value, $cast);

        if ($value === null) {
            abort(422, 'Invalid question value.');
        }

        if ($type === 'number' && !is_numeric($value)) {
            abort(422, 'Invalid question value.');
        }

        if ($type === 'enum' && ($op !== 'eq')) {
            abort(422, 'Invalid question operator.');
        }

        return [
            'key' => $key,
            'op' => $op,
            'value' => $value,
        ];
    }

    public function evaluate(mixed $wrapper, string $key, string $op, mixed $value): bool
    {
        $meta = (array) config("pvp.whois.meta.{$key}", []);
        $cast = (string) Arr::get($meta, 'cast', 'string');

        $actual = $this->hints->readHintValue($wrapper, $key);
        $actual = $this->castValue($actual, $cast);

        return match ($op) {
            'eq' => $actual === $value,
            'lt' => is_numeric($actual) && is_numeric($value) && (float) $actual < (float) $value,
            'gt' => is_numeric($actual) && is_numeric($value) && (float) $actual > (float) $value,
            default => false,
        };
    }

    private function castValue(mixed $value, string $cast): mixed
    {
        return match ($cast) {
            'int' => is_numeric($value) ? (int) $value : null,
            'upper' => is_string($value) ? strtoupper(trim($value)) : null,
            'string' => is_string($value) ? (string) $value : (is_numeric($value) ? (string) $value : null),
            default => $value,
        };
    }
}
