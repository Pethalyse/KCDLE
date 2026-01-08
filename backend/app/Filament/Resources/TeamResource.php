<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TeamResource\Pages;
use App\Models\Team;
use Exception;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class TeamResource extends Resource
{
    protected static ?string $model = Team::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'KCDLE';
    protected static ?int $navigationSort = 20;

    public static function getFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('slug')
                ->label('Slug')
                ->required()
                ->maxLength(64)
                ->unique(ignoreRecord: true)
                ->helperText('Identifiant interne (ex: karmine_corp, vitality_bee, team_go).'),

            Forms\Components\TextInput::make('display_name')
                ->label('Nom complet')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('short_name')
                ->label('Tag')
                ->maxLength(32)
                ->helperText('Ex : KC, VIT, TH, SK...'),

            Forms\Components\Select::make('country_code')
                ->label('Pays')
                ->relationship('country', 'name')
                ->searchable()
                ->preload()
                ->helperText('Optionnel : utilisé pour les drapeaux, filtres, etc.'),

            Forms\Components\Toggle::make('is_karmine_corp')
                ->label('Équipe Karmine Corp ?')
                ->inline(false),

            FileUpload::make('logo')
                ->label('Logo de l’équipe')
                ->image()
                ->acceptedFileTypes(['image/png', 'image/webp', 'image/jpeg'])
                ->disk('public')
                ->directory('teams')
                ->visibility('public')
                ->imageEditor()
                ->getUploadedFileNameForStorageUsing(
                    function (TemporaryUploadedFile $file, callable $get): string {
                        $slug = $get('slug') ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                        $ext = strtolower($file->getClientOriginalExtension());
                        return $slug . '.' . $ext;
                    }
                )
                ->storeFileNamesIn('unused_field')
                ->afterStateHydrated(function (FileUpload $component, $state, ?Team $record) {
                    if ($state || ! $record) {
                        return;
                    }

                    $slug = $record->getAttribute('slug');
                    foreach (['png', 'webp', 'jpg', 'jpeg'] as $ext) {
                        $relativePath = "teams/$slug.$ext";
                        if (Storage::disk('public')->exists($relativePath)) {
                            $component->state([$relativePath]);
                            return;
                        }
                    }
                })
                ->helperText('L’image sera enregistrée comme {slug}.ext dans /storage/teams')
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
                Tables\Columns\IconColumn::make('is_karmine_corp')
                    ->label('KC')
                    ->boolean(),

                Tables\Columns\ImageColumn::make('logo_url')
                    ->label('Logo')
                    ->square()
                    ->size(40),

                Tables\Columns\TextColumn::make('display_name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('short_name')
                    ->label('Tag')
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('country.name')
                    ->label('Pays')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_karmine_corp')
                    ->label('Karmine Corp uniquement'),
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
            'index'  => Pages\ListTeams::route('/'),
            'create' => Pages\CreateTeam::route('/create'),
            'edit'   => Pages\EditTeam::route('/{record}/edit'),
        ];
    }
}
