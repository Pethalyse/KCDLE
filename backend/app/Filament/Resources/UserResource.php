<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filament resource for administrating application users.
 *
 * This resource is available inside the Filament "admin" panel and allows
 * administrators to manage user accounts while explicitly preventing changes
 * to critical or sensitive information (e.g. password, email).
 */
class UserResource extends Resource
{
    /**
     * The model this resource corresponds to.
     *
     * @var class-string<User>
     */
    protected static ?string $model = User::class;

    /**
     * @var string|null
     */
    protected static ?string $navigationIcon = 'heroicon-o-users';

    /**
     * @var string|null
     */
    protected static ?string $navigationGroup = 'Administration';

    /**
     * @var int|null
     */
    protected static ?int $navigationSort = 1;

    /**
     * Build the create / edit form schema.
     *
     * @param Form $form The Filament form builder.
     *
     * @return Form
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Account')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Username')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        TextInput::make('email')
                            ->label('Email')
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('discord_id')
                            ->label('Discord ID')
                            ->nullable()
                            ->maxLength(64),

                        TextInput::make('discord_avatar_hash')
                            ->label('Discord Avatar Hash')
                            ->nullable()
                            ->maxLength(255),
                    ]),

                Section::make('Roles')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_admin')
                            ->label('Admin')
                            ->inline(false),

                        Toggle::make('is_streamer')
                            ->label('Streamer')
                            ->inline(false),
                    ]),

                Section::make('Profile customization')
                    ->columns(2)
                    ->schema([
                        FileUpload::make('avatar_path')
                            ->label('Custom avatar')
                            ->image()
                            ->disk('public')
                            ->directory('users')
                            ->visibility('public')
                            ->imageEditor()
                            ->nullable(),

                        ColorPicker::make('avatar_frame_color')
                            ->label('Avatar frame color')
                            ->nullable(),
                    ]),
            ]);
    }

    /**
     * Build the "view" page infolist.
     *
     * @param Infolist $infolist The infolist builder.
     *
     * @return Infolist
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make('Overview')
                    ->columns(3)
                    ->schema([
                        ImageEntry::make('avatar_url')
                            ->label('Avatar')
                            ->circular(),

                        TextEntry::make('id')
                            ->label('ID'),

                        TextEntry::make('name')
                            ->label('Username'),
                    ]),

                InfolistSection::make('Account')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('email')
                            ->label('Email'),

                        TextEntry::make('email_verified_at')
                            ->label('Email verified at')
                            ->dateTime(),

                        TextEntry::make('created_at')
                            ->label('Created at')
                            ->dateTime(),

                        TextEntry::make('updated_at')
                            ->label('Updated at')
                            ->dateTime(),
                    ]),

                InfolistSection::make('Roles')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('is_admin')
                            ->label('Admin')
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No'),

                        TextEntry::make('is_streamer')
                            ->label('Streamer')
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No'),
                    ]),

                InfolistSection::make('Discord')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('discord_id')
                            ->label('Discord ID')
                            ->placeholder('-'),

                        TextEntry::make('discord_avatar_hash')
                            ->label('Discord avatar hash')
                            ->placeholder('-'),
                    ]),

                InfolistSection::make('Profile customization')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('avatar_path')
                            ->label('Stored avatar path')
                            ->placeholder('-'),

                        TextEntry::make('avatar_frame_color')
                            ->label('Avatar frame color')
                            ->placeholder('-'),
                    ]),

                InfolistSection::make('Activity')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('game_results_count')
                            ->label('Daily results')
                            ->state(fn (User $record): int => $record->gameResults()->count()),

                        TextEntry::make('guesses_count')
                            ->label('Guesses')
                            ->state(fn (User $record): int => $record->guesses()->count()),

                        TextEntry::make('achievements_count')
                            ->label('Achievements')
                            ->state(fn (User $record): int => $record->achievements()->count()),
                    ]),
            ]);
    }

    /**
     * Build the table definition used by the list page.
     *
     * @param Table $table The table builder.
     *
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar_url')
                    ->label('')
                    ->circular()
                    ->size(36),

                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Username')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_admin')
                    ->label('Admin')
                    ->boolean()
                    ->sortable(),

                IconColumn::make('is_streamer')
                    ->label('Streamer')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('discord_id')
                    ->label('Discord')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn (?string $state): string => $state ? 'Linked' : '-'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_admin')
                    ->label('Admin'),
                TernaryFilter::make('is_streamer')
                    ->label('Streamer'),
            ])
            ->actions([
                \Filament\Tables\Actions\ViewAction::make(),
                \Filament\Tables\Actions\EditAction::make(),
                \Filament\Tables\Actions\DeleteAction::make()
                    ->hidden(fn (User $record): bool => auth()->id() === $record->getKey()),
            ])
            ->bulkActions([
                \Filament\Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    /**
     * Configure Filament resource routes.
     *
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    /**
     * Disable creation of users from the admin panel.
     *
     * User accounts should be created through the application flows to ensure
     * verification, password rules, and external integrations are handled.
     *
     * @return bool
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Base query used by the resource.
     *
     * @return Builder
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }
}
