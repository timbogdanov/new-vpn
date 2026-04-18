<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TelegramUserResource\Pages;
use App\Models\TelegramUser;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TelegramUserResource extends Resource
{
    protected static ?string $model = TelegramUser::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Users';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Textarea::make('admin_notes')->rows(4)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('telegram_id')->label('TG ID')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('first_name')->searchable(),
                Tables\Columns\TextColumn::make('username')->searchable(),
                Tables\Columns\TextColumn::make('language_code')->label('Lang')->badge(),
                Tables\Columns\TextColumn::make('vpn_clients_count')
                    ->counts('vpnClients')->label('Clients'),
                Tables\Columns\TextColumn::make('subscriptions_count')
                    ->counts('subscriptions')->label('Subs'),
                Tables\Columns\TextColumn::make('last_active_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('trial_used_at')->dateTime()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('last_active_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTelegramUsers::route('/'),
            'view' => Pages\ViewTelegramUser::route('/{record}'),
            'edit' => Pages\EditTelegramUser::route('/{record}/edit'),
        ];
    }
}
