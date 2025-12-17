<?php

namespace App\Services\Pvp\Rounds;

/**
 * Validates and evaluates Whois questions against a player wrapper.
 */
readonly class WhoisQuestionService
{
    public function __construct(private HintValueService $hints)
    {
    }

    /**
     * Validate a whois question payload.
     *
     * @param array $question Question payload.
     *
     * @return array{key:string, op:string, value:mixed}
     */
    public function validate(array $question): array
    {
        $key = (string) ($question['key'] ?? '');
        $op = (string) ($question['op'] ?? '');
        $value = $question['value'] ?? null;

        $allowedKeys = [
            'country_code',
            'role_id',
            'game_id',
            'current_team_id',
            'previous_team_id',
            'trophies_count',
            'first_official_year',
        ];

        $allowedOps = ['eq', 'lt', 'gt'];

        if (!in_array($key, $allowedKeys, true)) {
            abort(422, 'Invalid question key.');
        }

        if (!in_array($op, $allowedOps, true)) {
            abort(422, 'Invalid question operator.');
        }

        if ($op === 'eq' && $value === null) {
            abort(422, 'Invalid question value.');
        }

        return [
            'key' => $key,
            'op' => $op,
            'value' => $value,
        ];
    }

    /**
     * Evaluate a question against a wrapper and return boolean.
     *
     * @param mixed  $wrapper Player wrapper model.
     * @param string $key     Question key.
     * @param string $op      Operator.
     * @param mixed  $value   Value.
     *
     * @return bool
     */
    public function evaluate(mixed $wrapper, string $key, string $op, mixed $value): bool
    {
        $actual = $this->hints->readHintValue($wrapper, $key);

        return match ($op) {
            'eq' => $actual === $value,
            'lt' => is_numeric($actual) && is_numeric($value) && (float)$actual < (float)$value,
            'gt' => is_numeric($actual) && is_numeric($value) && (float)$actual > (float)$value,
            default => false,
        };
    }
}
