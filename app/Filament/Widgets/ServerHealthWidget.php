<?php

namespace App\Filament\Widgets;

use App\Models\Server;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as Base;

class ServerHealthWidget extends Base
{
    protected int|string|array $columnSpan = 'full';
    protected static ?string $heading = 'Server health';

    public function table(Table $table): Table
    {
        return $table
            ->query(Server::query()->where('is_active', true))
            ->columns([
                Tables\Columns\TextColumn::make('slug'),
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('load_percent')->label('Load %')->sortable(),
                Tables\Columns\TextColumn::make('ping_ms')->label('Ping')->sortable(),
                Tables\Columns\TextColumn::make('last_error')->limit(40),
                Tables\Columns\TextColumn::make('updated_at')->dateTime(),
            ])
            ->paginated(false);
    }
}
