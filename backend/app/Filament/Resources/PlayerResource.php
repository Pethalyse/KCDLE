<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PersonResource\Pages;
use App\Models\Player;
use Exception;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class PlayerResource extends Resource
{
    protected static ?string $model = Player::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';
    protected static ?string $navigationGroup = 'KCDLE';

    protected static ?int $navigationSort = 30;

    public static function getFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('slug')
                ->label('Slug')
                ->required()
                ->maxLength(64)
                ->unique(ignoreRecord: true)
                ->helperText('Identifiant interne unique (ex : cabochard, saken).'),

            Forms\Components\TextInput::make('display_name')
                ->label('Nom affiché')
                ->required()
                ->maxLength(255),

            Forms\Components\Select::make('role_id')
                ->label('Rôle global')
                ->relationship('role', 'label')
                ->searchable()
                ->preload()
                ->required(),

            Forms\Components\Select::make('country_code')
                ->label('Pays')
                ->relationship('country', 'name')
                ->searchable()
                ->preload(),

            Forms\Components\DatePicker::make('birthdate')
                ->label('Date de naissance')
                ->displayFormat('d/m/Y')
                ->required()
                ->closeOnDateSelection(),

            FileUpload::make('avatar')
                ->label('Photo du joueur')
                ->image()
                ->disk('public')
                ->directory('players')
                ->visibility('public')
                ->imageEditor()
                ->getUploadedFileNameForStorageUsing(
                    function (TemporaryUploadedFile $file, callable $get): string {
                        $slug = $get('slug') ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                        return $slug . '.' . $file->getClientOriginalExtension();
                    }
                )
                ->storeFileNamesIn('unused_field')
                ->afterStateHydrated(function (FileUpload $component, $state, ?Player $record) {
                    if ($state || !$record) {
                        return;
                    }
                    $slug = $record->getAttribute('slug');
                    $relativePath = "players/$slug.png";
                    if (Storage::disk('public')->exists($relativePath)) {
                        $component->state([$relativePath]);
                    }
                })
                ->helperText('L’image sera enregistrée comme {slug}.ext dans /storage/players'),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema(static::getFormSchema());
    }

    /**
     * @throws Exception
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label('Image')
                    ->square()
                    ->size(40),

                Tables\Columns\TextColumn::make('display_name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('role.label')
                    ->label('Rôle')
                    ->sortable(),

                Tables\Columns\TextColumn::make('country.name')
                    ->label('Pays')
                    ->sortable(),

                Tables\Columns\TextColumn::make('birthdate')
                    ->label('Date de naissance')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role_id')
                    ->label('Rôle')
                    ->relationship('role', 'label'),
            ])
            ->defaultSort('display_name')
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
            'index'  => Pages\ListPlayers::route('/'),
            'create' => Pages\CreatePlayer::route('/create'),
            'edit'   => Pages\EditPlayer::route('/{record}/edit'),
        ];
    }
}
