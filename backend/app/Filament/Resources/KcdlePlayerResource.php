<?php

namespace App\Filament\Resources;

use App\Filament\Resources\KcdlePlayerResource\Pages;
use App\Models\KcdlePlayer;
use Exception;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class KcdlePlayerResource extends Resource
{
    protected static ?string $model = KcdlePlayer::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';
    protected static ?string $navigationGroup = 'KCDLE';

    protected static ?int $navigationSort = 40;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Joueur')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('player_id')
                            ->label('Personne')
                            ->relationship('player', 'display_name',
                                modifyQueryUsing: function ($query, string $operation) {
                                    if ($operation === 'edit') {
                                        return $query;
                                    }
                                    $alreadyUsed = KcdlePlayer::query()->pluck('player_id')->all();
                                    return $query->whereNotIn('id', $alreadyUsed);
                                })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabledOn('edit')
                            ->unique(ignoreRecord: true)
                            ->createOptionForm(PlayerResource::getFormSchema())
                            ->createOptionAction(function (Forms\Components\Actions\Action $action) {
                                return $action
                                    ->modalHeading('Créer un nouveau joueur')
                                    ->modalButton('Créer & sélectionner');
                            }),
                    ]),

                Forms\Components\Section::make('Profil Karmine Corp')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('game_id')
                            ->label('Jeu')
                            ->relationship('game', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('current_team_id')
                            ->label('Équipe actuelle')
                            ->relationship('currentTeam', 'display_name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm(TeamResource::getFormSchema())
                            ->createOptionAction(function (Forms\Components\Actions\Action $action) {
                                return $action
                                    ->modalHeading('Créer une nouvelle équipe')
                                    ->modalButton('Créer & sélectionner');
                            }),

                        Forms\Components\Select::make('previous_team_before_kc_id')
                            ->label('Équipe avant KC')
                            ->relationship('previousTeam', 'display_name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm(TeamResource::getFormSchema())
                            ->createOptionAction(function (Forms\Components\Actions\Action $action) {
                                return $action
                                    ->modalHeading('Créer une nouvelle équipe')
                                    ->modalButton('Créer & sélectionner');
                            }),

                        Forms\Components\TextInput::make('first_official_year')
                            ->label('Année du premier match KC')
                            ->numeric()
                            ->required()
                            ->minValue(2019)
                            ->maxValue(now()->year + 1)
                            ->helperText('Uniquement l’année (ex : 2021).'),

                        Forms\Components\TextInput::make('trophies_count')
                            ->label('Nombre de trophées')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),

                        Forms\Components\Toggle::make('active')
                            ->label('Actif dans le jeu KCdle ?')
                            ->default(true)
                            ->helperText('Permet de masquer/afficher ce profil dans le pool de joueurs sans le supprimer.')
                            ->live()
                            ->afterStateUpdated(function (bool $state, ?KcdlePlayer $record, Set $set) {
                                if (!$record) return;

                                if ($state === false && $record->cannotDeactivate()) {
                                    $set('active', true);
                                    Notification::make()
                                        ->danger()
                                        ->title('Impossible de désactiver ce joueur')
                                        ->body('Ce joueur est utilisé dans un daily game pour aujourd’hui ou une date future.')
                                        ->send();
                                }
                            }),
                    ]),
            ]);
    }

    /**
     * @throws Exception
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('active')
                    ->label('Actif')
                    ->boolean()
                    ->action(function (KcdlePlayer $record) {
                        if ($record->getAttribute('active') && $record->cannotDeactivate()) {
                            Notification::make()
                                ->danger()
                                ->title('Impossible de désactiver ce joueur')
                                ->body('Ce joueur est utilisé dans un daily game pour aujourd’hui ou une date future.')
                                ->send();
                            return;
                        }
                        $record->update(["active" => !$record->getAttribute('active')]);
                    }),

                Tables\Columns\ImageColumn::make('player.image_url')
                    ->label('Image')
                    ->square()
                    ->size(40),

                Tables\Columns\TextColumn::make('player.display_name')
                    ->label('Joueur')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('game.name')
                    ->label('Jeu')
                    ->sortable(),

                Tables\Columns\TextColumn::make('currentTeam.display_name')
                    ->label('Équipe actuelle')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('previousTeam.display_name')
                    ->label('Équipe avant KC')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('first_official_year')
                    ->label('Année 1er match KC')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('trophies_count')
                    ->label('Trophées')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Actif dans le jeu ?'),

                Tables\Filters\SelectFilter::make('game_id')
                    ->label('Jeu')
                    ->relationship('game', 'name'),

                Tables\Filters\Filter::make('currentTeam.is_karmine_corp')
                    ->label('Karmine Corp uniquement')
                    ->toggle()
                    ->query(function (Builder $query) {
                        return $query->whereHas('currentTeam', function (Builder $q) {
                            $q->where('is_karmine_corp', true);
                        });
                    }),
            ])
            ->defaultSort('game')
            ->defaultSort('first_official_year', "desc")
            ->defaultSort('player.display_name')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('addTrophy')
                    ->label('+1 trophée')
                    ->icon('heroicon-m-trophy')
                    ->action(function (KcdlePlayer $record) {
                        $record->incrementTrophiesCount();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListKcdlePlayers::route('/'),
            'create' => Pages\CreateKcdlePlayer::route('/create'),
            'edit'   => Pages\EditKcdlePlayer::route('/{record}/edit'),
        ];
    }
}
