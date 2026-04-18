<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServerResource\Pages;
use App\Models\Server;
use App\Services\ServerStatsService;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\App;

class ServerResource extends Resource
{
    protected static ?string $model = Server::class;
    protected static ?string $navigationIcon = 'heroicon-o-server-stack';
    protected static ?string $navigationGroup = 'Infrastructure';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Toggle::make('is_active'),
            Toggle::make('is_coming_soon'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('slug')->searchable(),
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('country'),
                Tables\Columns\TextColumn::make('host'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\IconColumn::make('is_coming_soon')->boolean(),
                Tables\Columns\TextColumn::make('load_percent')->label('Load %')->sortable(),
                Tables\Columns\TextColumn::make('ping_ms')->label('Ping ms')->sortable(),
                Tables\Columns\TextColumn::make('last_error')->limit(40)->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime(),
            ])
            ->actions([
                Tables\Actions\Action::make('probe')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (Server $record) {
                        try {
                            App::make(ServerStatsService::class)->refresh($record);
                            Notification::make()->success()->title('Probed')->send();
                        } catch (\Throwable $e) {
                            Notification::make()->danger()->title('Probe failed')->body($e->getMessage())->send();
                        }
                    }),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServers::route('/'),
            'edit' => Pages\EditServer::route('/{record}/edit'),
        ];
    }
}
