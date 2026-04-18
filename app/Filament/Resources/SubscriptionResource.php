<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\LogsAdminActions;
use App\Filament\Resources\SubscriptionResource\Pages;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\App;

class SubscriptionResource extends Resource
{
    use LogsAdminActions;

    protected static ?string $model = Subscription::class;
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationGroup = 'Billing';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('status')->options([
                Subscription::STATUS_TRIALING => 'Trialing',
                Subscription::STATUS_ACTIVE => 'Active',
                Subscription::STATUS_EXPIRED => 'Expired',
                Subscription::STATUS_CANCELED => 'Canceled',
                Subscription::STATUS_REFUNDED => 'Refunded',
            ])->required(),
            DateTimePicker::make('expires_at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('telegramUser.first_name')->label('User'),
                Tables\Columns\TextColumn::make('plan_key')->badge(),
                Tables\Columns\TextColumn::make('tier')->badge(),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn ($state) => match ($state) {
                    Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING => 'success',
                    Subscription::STATUS_EXPIRED, Subscription::STATUS_CANCELED => 'warning',
                    Subscription::STATUS_REFUNDED => 'danger',
                    default => 'gray',
                }),
                Tables\Columns\TextColumn::make('stars_paid')->label('★')->sortable(),
                Tables\Columns\TextColumn::make('expires_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    Subscription::STATUS_TRIALING => 'Trialing',
                    Subscription::STATUS_ACTIVE => 'Active',
                    Subscription::STATUS_EXPIRED => 'Expired',
                    Subscription::STATUS_CANCELED => 'Canceled',
                    Subscription::STATUS_REFUNDED => 'Refunded',
                ]),
                Tables\Filters\SelectFilter::make('tier')->options([
                    Subscription::TIER_TRIAL => 'Trial',
                    Subscription::TIER_PRO => 'Pro',
                    Subscription::TIER_PRO_ANNUAL => 'Pro Annual',
                ]),
            ])
            ->actions([
                Tables\Actions\Action::make('extend_30')
                    ->label('+30 days')
                    ->icon('heroicon-o-arrow-trending-up')
                    ->requiresConfirmation()
                    ->action(function (Subscription $record) {
                        App::make(SubscriptionService::class)->extend($record, 30);
                        (new self)->logAdminAction('subscription.extend', $record, ['days' => 30]);
                    }),
                Tables\Actions\Action::make('expire')
                    ->color('danger')
                    ->icon('heroicon-o-no-symbol')
                    ->requiresConfirmation()
                    ->action(function (Subscription $record) {
                        App::make(SubscriptionService::class)->expire($record);
                        (new self)->logAdminAction('subscription.expire', $record);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptions::route('/'),
        ];
    }
}
