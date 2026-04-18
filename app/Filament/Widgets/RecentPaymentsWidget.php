<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as Base;

class RecentPaymentsWidget extends Base
{
    protected int|string|array $columnSpan = 'full';
    protected static ?string $heading = 'Recent payments';

    public function table(Table $table): Table
    {
        return $table
            ->query(Payment::query()->latest()->limit(15))
            ->columns([
                Tables\Columns\TextColumn::make('telegram_user_id')->label('TG'),
                Tables\Columns\TextColumn::make('plan_key'),
                Tables\Columns\TextColumn::make('stars_amount')->label('★'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('paid_at')->dateTime(),
            ])
            ->paginated(false);
    }
}
