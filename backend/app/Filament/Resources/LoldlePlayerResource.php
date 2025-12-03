<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LeagueMembershipResource\Pages;
use App\Models\LoldlePlayer;
use Exception;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LoldlePlayerResource extends Resource
{
    protected static ?string $model = LoldlePlayer::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';
    protected static ?string $navigationGroup = 'KCDLE';

    protected static ?int $navigationSort = 50;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Joueur')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('player_id')
                            ->label('Joueur')
                            ->relationship('player', 'display_name',
                                modifyQueryUsing: function ($query, string $operation) {
                                    if ($operation === 'edit') {
                                        return $query;
                                    }
                                    $alreadyUsed = LoldlePlayer::all('player_id');
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

                Forms\Components\Section::make('Ligues LoL')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('league_id')
                            ->label('Ligue')
                            ->relationship('league', 'code')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('team_id')
                            ->label('Équipe')
                            ->relationship('team', 'display_name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm(TeamResource::getFormSchema())
                            ->createOptionAction(function (Forms\Components\Actions\Action $action) {
                                return $action
                                    ->modalHeading('Créer une nouvelle équipe')
                                    ->modalButton('Créer & sélectionner');
                            }),

                        Forms\Components\Select::make('lol_role')
                            ->label('Rôle LoL')
                            ->options([
                                'TOP' => 'Top',
                                'JNG' => 'Jungle',
                                'MID' => 'Mid',
                                'ADC' => 'Adc',
                                'SUP' => 'Support',
                            ])
                            ->required(),

                        Forms\Components\Toggle::make('active')
                            ->label('Actif dans la ligue ?')
                            ->default(true)
                            ->helperText('S’il est inactif, il ne sera pas pris en compte dans LFLDle / LECDle.')
                            ->live()
                            ->afterStateUpdated(function (bool $state, ?LoldlePlayer $record, Set $set) {
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
                    ->action(function (LoldlePlayer $record) {
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

                Tables\Columns\TextColumn::make('league.code')
                    ->label('Ligue')
                    ->badge()
                    ->colors([
                        'primary',
                        'success' => 'LEC',
                        'info'    => 'LFL',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('team.display_name')
                    ->label('Équipe')
                    ->sortable(),

                Tables\Columns\TextColumn::make('lol_role')
                    ->label('Rôle LoL')
                    ->sortable()
                    ->badge(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Actif ?'),

                Tables\Filters\SelectFilter::make('league_id')
                    ->label('Ligue')
                    ->options([
                        'LFL' => 'LFL',
                        'LEC' => 'LEC',
                    ]),

                Tables\Filters\SelectFilter::make('team_id')
                    ->label('Équipe')
                    ->relationship('team', 'display_name'),
            ])
            ->defaultSort('league_id')
            ->defaultSort('team.display_name')
            ->defaultSort('player.display_name')
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListLoldlePlayers::route('/'),
            'create' => Pages\CreateLoldlePlayer::route('/create'),
            'edit'   => Pages\EditLoldlePlayer::route('/{record}/edit'),
        ];
    }
}
