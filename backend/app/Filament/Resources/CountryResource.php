<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CountryResource\Pages;
use App\Models\Country;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CountryResource extends Resource
{
    protected static ?string $model = Country::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';
    protected static ?string $navigationGroup = 'KCDLE';
    protected static ?int $navigationSort = 15;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->label('Code')
                    ->required()
                    ->minLength(2)
                    ->maxLength(2)
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn (?string $state): ?string => $state ? strtoupper(trim($state)) : null)
                    ->rule('regex:/^[A-Z]{2}$/')
                    ->disabledOn('edit')
                    ->helperText('Code sur 2 lettres (ex: FR, US).'),

                Forms\Components\TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(255),

                FileUpload::make('flag')
                    ->label('Drapeau')
                    ->image()
                    ->acceptedFileTypes(['image/png'])
                    ->disk('public')
                    ->directory('countries')
                    ->visibility('public')
                    ->imageEditor()
                    ->getUploadedFileNameForStorageUsing(
                        function (TemporaryUploadedFile $file, callable $get): string {
                            $code = $get('code') ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                            return strtoupper($code) . '.png';
                        }
                    )
                    ->storeFileNamesIn('unused_field')
                    ->afterStateHydrated(function (FileUpload $component, $state, ?Country $record) {
                        if ($state || ! $record) {
                            return;
                        }
                        $code = $record->getAttribute('code');
                        $relativePath = "countries/$code.png";
                        if (Storage::disk('public')->exists($relativePath)) {
                            $component->state([$relativePath]);
                        }
                    })
                    ->helperText('Enregistré comme {CODE}.png dans /storage/countries'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('flag_url')
                    ->label('Drapeau')
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
            'index'  => Pages\ListCountries::route('/'),
//            'create' => Pages\CreateCountry::route('/create'),
            'edit'   => Pages\EditCountry::route('/{record}/edit'),
        ];
    }
}
