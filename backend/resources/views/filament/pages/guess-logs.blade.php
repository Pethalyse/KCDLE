<x-filament::page>

    <x-filament::section>
        <x-slot name="heading">
            Logs des guesses
        </x-slot>

        <x-slot name="description">
            Historique des guesses, joueurs qui trouvent, et tentatives throttlées.
        </x-slot>

        <div class="flex gap-4 my-4">
            <button wire:click="$set('filter', 'all')" class="px-3 py-1 rounded bg-gray-200">
                Tous
            </button>
            <button wire:click="$set('filter', 'correct')" class="px-3 py-1 rounded bg-green-200">
                Correct
            </button>
            <button wire:click="$set('filter', 'guess')" class="px-3 py-1 rounded bg-blue-200">
                Guess
            </button>
            <button wire:click="$set('filter', 'throttle')" class="px-3 py-1 rounded bg-red-200">
                Throttle
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                <tr class="bg-black-100">
                    <th class="px-3 py-2 text-left">Horodatage</th>
                    <th class="px-3 py-2 text-left">Type</th>
                    <th class="px-3 py-2 text-left">IP</th>
                    <th class="px-3 py-2 text-left">Jeu</th>
                    <th class="px-3 py-2 text-left">Données</th>
                </tr>
                </thead>

                <tbody>
                @foreach($this->filteredLogs as $log)
                    <tr class="border-b">
                        <td class="px-3 py-2">{{ $log['timestamp'] }}</td>
                        <td class="px-3 py-2 capitalize">
                            @if($log['type'] === 'correct')
                                <span class="text-green-600 font-bold">Correct</span>
                            @elseif($log['type'] === 'throttle')
                                <span class="text-red-600 font-bold">Throttle</span>
                            @else
                                <span class="text-blue-600 font-bold">Guess</span>
                            @endif
                        </td>
                        <td class="px-3 py-2">{{ $log['data']['ip'] ?? '-' }}</td>
                        <td class="px-3 py-2">{{ $log['data']['game'] ?? '-' }}</td>
                        <td class="px-3 py-2">
                            <pre class="text-xs">{{ json_encode($log['data'], JSON_PRETTY_PRINT) }}</pre>
                        </td>
                    </tr>
                @endforeach
                </tbody>

            </table>
        </div>
    </x-filament::section>

</x-filament::page>
