<?php

namespace App\Services\Pvp;

use App\Models\Team;

/**
 * Normalise les valeurs d’indices PvP afin d’éviter les nulls incohérents côté frontend.
 */
final class PvpHintNormalizer
{
    private int $noneTeamId;

    public function __construct()
    {
        $this->noneTeamId = (int) Team::query()
            ->where('slug', 'none')
            ->value('id');
    }

    /**
     * @param array<string, mixed> $hints
     * @return array<string, mixed>
     */
    public function normalize(array $hints): array
    {
        foreach ($hints as $key => $value) {
            $hints[$key] = match ($key) {
                'country_code' => $this->normalizeCountry($value),
                'current_team_id',
                'previous_team_id' => $this->normalizeTeam($value),
                default => $value,
            };
        }

        return $hints;
    }

    private function normalizeCountry(mixed $value): string
    {
        $code = strtoupper((string) $value);
        return $code !== '' ? $code : 'NN';
    }

    private function normalizeTeam(mixed $value): int
    {
        $id = (int) $value;
        return $id > 0 ? $id : $this->noneTeamId;
    }
}
