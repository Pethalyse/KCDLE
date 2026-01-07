<?php
namespace App\Services\Pvp;

use App\Models\Team;
use Illuminate\Support\Facades\Schema;

/**
 * Normalise les valeurs d’indices PvP afin d’éviter les nulls incohérents côté frontend.
 */
final class PvpHintNormalizer
{
    private ?int $noneTeamId = null;

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
        $code = strtoupper((string)$value);
        return $code !== '' ? $code : 'NN';
    }

    private function normalizeTeam(mixed $value): int
    {
        $id = (int)$value;
        return $id > 0 ? $id : $this->getNoneTeamId();
    }

    private function getNoneTeamId(): int
    {
        if ($this->noneTeamId !== null) {
            return $this->noneTeamId;
        }

        if (!Schema::hasTable('teams')) {
            $this->noneTeamId = 0;
            return $this->noneTeamId;
        }

        $this->noneTeamId = (int)(Team::query()->where('slug', 'none')->value('id') ?? 0);

        return $this->noneTeamId;
    }
}
