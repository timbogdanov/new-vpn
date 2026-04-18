<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AnnouncementResource\Pages;
use App\Models\Announcement;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;
    protected static ?string $navigationIcon = 'heroicon-o-megaphone';
    protected static ?string $navigationGroup = 'Communication';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('title')->required()->maxLength(120),
            Textarea::make('body_md')->rows(4)->required(),
            Select::make('severity')->options([
                'info' => 'Info', 'warn' => 'Warn', 'danger' => 'Danger',
            ])->default('info')->required(),
            Select::make('target')->options([
                Announcement::TARGET_ALL => 'All users',
                Announcement::TARGET_TIER_PRO => 'Pro tier',
                Announcement::TARGET_LANG_RU => 'Russian',
                Announcement::TARGET_LANG_EN => 'English',
            ])->default(Announcement::TARGET_ALL)->required(),
            DateTimePicker::make('starts_at'),
            DateTimePicker::make('ends_at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable(),
                Tables\Columns\TextColumn::make('severity')->badge(),
                Tables\Columns\TextColumn::make('target')->badge(),
                Tables\Columns\TextColumn::make('starts_at')->dateTime(),
                Tables\Columns\TextColumn::make('ends_at')->dateTime(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAnnouncements::route('/'),
            'create' => Pages\CreateAnnouncement::route('/create'),
            'edit' => Pages\EditAnnouncement::route('/{record}/edit'),
        ];
    }
}
