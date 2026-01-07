<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GuessLogs extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static string $view = 'filament.pages.guess-logs';
    protected static ?string $navigationLabel = 'Guess Logs';
    protected static ?string $navigationGroup = 'Monitoring';

    public array $logs = [];

    public string $filter = 'all';

    public function mount(): void
    {
        $path = storage_path('logs/guess.log');

        if (! File::exists($path)) {
            $this->logs = [];
            return;
        }

        $lines = File::lines($path);

        $parsed = [];

        foreach ($lines as $line) {
            $date = Str::before($line, ']');
            $date = Str::after($date, '[');

            $json = Str::after($line, '{');
            $json = '{' . trim($json);

            $data = json_decode($json, true);

            $parsed[] = [
                'timestamp' => $date,
                'type'      => Str::contains($line, 'Correct guess') ? 'correct'
                    : (Str::contains($line, 'Throttle exceeded') ? 'throttle'
                        : 'guess'),
                'data'      => $data ?? [],
            ];
        }

        $this->logs = array_reverse($parsed);
    }

    public function getFilteredLogsProperty(): array
    {
        if ($this->filter === 'all') {
            return $this->logs;
        }

        return array_values(array_filter(
            $this->logs,
            fn (array $log) => $log['type'] === $this->filter
        ));
    }
}
