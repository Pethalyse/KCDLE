<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GameResource\Pages;
use App\Models\Game;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class GameResource extends Resource
{
    protected static ?string $model = Game::class;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';
    protected static ?string $navigationGroup = 'KCDLE';
    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->label('Code interne')
                    ->required()
                    ->maxLength(32)
                    ->unique(ignoreRecord: true)
                    ->helperText('Identifiant interne (LOL, VALORANT, ROCKET_LEAGUE...).'),

                Forms\Components\TextInput::make('name')
                    ->label('Nom du jeu')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('icon_slug')
                    ->label('Slug d’icône')
                    ->required()
                    ->maxLength(64)
                    ->helperText('Correspond au nom de fichier sans extension (ex : LOL → LOL.png).'),

                FileUpload::make('logo')
                    ->label('Logo du jeu')
                    ->image()
                    ->acceptedFileTypes(['image/png', 'image/webp', 'image/jpeg'])
                    ->disk('public')
                    ->directory('games')
                    ->visibility('public')
                    ->imageEditor()
                    ->getUploadedFileNameForStorageUsing(
                        function (TemporaryUploadedFile $file, callable $get): string {
                            $slug = $get('icon_slug') ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                            $ext = strtolower($file->getClientOriginalExtension());
                            return $slug . '.' . $ext;
                        }
                    )
                    ->storeFileNamesIn('unused_field')
                    ->afterStateHydrated(function (FileUpload $component, $state, ?Game $record) {
                        if ($state || ! $record) {
                            return;
                        }

                        $slug = $record->getAttribute('icon_slug');
                        foreach (['png', 'webp', 'jpg', 'jpeg'] as $ext) {
                            $relativePath = "games/$slug.$ext";
                            if (Storage::disk('public')->exists($relativePath)) {
                                $component->state([$relativePath]);
                                return;
                            }
                        }
                    })
                    ->helperText('L’image sera enregistrée comme {icon_slug}.ext dans /storage/games'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo_url')
                    ->label('Logo')
                    ->square()
                    ->size(40),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('icon_slug')
                    ->label('Icon slug')
                    ->searchable(),
            ])
            ->defaultSort('name')
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
            'index'  => Pages\ListGames::route('/'),
            'create' => Pages\CreateGame::route('/create'),
            'edit'   => Pages\EditGame::route('/{record}/edit'),
        ];
    }
}
