<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DailyGameResource\Pages;
use App\Models\DailyGame;
use Exception;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DailyGameResource extends Resource
{
    protected static ?string $model = DailyGame::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Daily games';
    protected static ?string $pluralLabel = 'Daily games';
    protected static ?string $modelLabel = 'Daily game';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
            ]);
    }

    /**
     * @throws Exception
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('selected_for_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('game_label')
                    ->label('Jeu')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('game', $direction))
                    ->badge()
                    ->colors([
                        'primary' => 'KCDLE',
                        'success' => 'LFLDLE',
                        'info'    => 'LECDLE',
                    ]),

                Tables\Columns\TextColumn::make('player_display_name')
                    ->label('Joueur')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        $direction = $direction === 'desc' ? 'DESC' : 'ASC';
                        return $query->orderByRaw("
                            CASE
                                WHEN game = 'kcdle' THEN (
                                    SELECT players.display_name
                                    FROM kcdle_players
                                    JOIN players ON players.id = kcdle_players.player_id
                                    WHERE kcdle_players.id = daily_games.player_id
                                    LIMIT 1
                                )
                                WHEN game IN ('lfldle', 'lecdle') THEN (
                                    SELECT players.display_name
                                    FROM loldle_players
                                    JOIN players ON players.id = loldle_players.player_id
                                    WHERE loldle_players.id = daily_games.player_id
                                    LIMIT 1
                                )
                                ELSE ''
                            END $direction
                        ");
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $q) use ($search) {
                            $q->where(function (Builder $sub) use ($search) {
                                $sub->where('game', 'kcdle')
                                    ->whereIn('player_id', function ($sub2) use ($search) {
                                        $sub2->select('kcdle_players.id')
                                            ->from('kcdle_players')
                                            ->join('players', 'players.id', '=', 'kcdle_players.player_id')
                                            ->whereRaw('players.display_name ILIKE ?', ['%' . $search . '%']);
                                    });
                            });

                            $q->orWhere(function (Builder $sub) use ($search) {
                                $sub->whereIn('game', ['lfldle', 'lecdle'])
                                    ->whereIn('player_id', function ($sub2) use ($search) {
                                        $sub2->select('loldle_players.id')
                                            ->from('loldle_players')
                                            ->join('players', 'players.id', '=', 'loldle_players.player_id')
                                            ->whereRaw('players.display_name ILIKE ?', ['%' . $search . '%']);
                                    });
                            });
                        });
                    }),

                Tables\Columns\TextColumn::make('solvers_count')
                    ->label('Nombre de joueurs ayant trouvé')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_guesses')
                    ->label('Total des guesses')
                    ->sortable(),

                Tables\Columns\TextColumn::make('average_guesses')
                    ->label('Moyenne de guesses')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format($state, 2) : '—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('game')
                    ->label('Jeu')
                    ->options([
                        'kcdle'  => 'KCDLE',
                        'lfldle' => 'LFLDLE',
                        'lecdle' => 'LECDLE',
                    ]),

                Tables\Filters\Filter::make('selected_for_date')
                    ->label('Date')
                    ->form([
                        DatePicker::make('date'),
                    ])
                    ->query(function ($query, array $data) {
                        return isset($data['date'])
                            ? $query->whereDate('selected_for_date', $data['date'])
                            : $query;
                    }),
            ])
            ->defaultSort('selected_for_date', 'desc')
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (DailyGame $record) => $record->getAttribute('selected_for_date')->isFuture()),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDailyGames::route('/'),
            // 'create' => Pages\CreateDailyGame::route('/create'),
            // 'edit' => Pages\EditDailyGame::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }
}
