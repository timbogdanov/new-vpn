<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\LogsAdminActions;
use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use App\Services\SubscriptionService;
use App\Services\TelegramBillingService;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\App;

class PaymentResource extends Resource
{
    use LogsAdminActions;

    protected static ?string $model = Payment::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Billing';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('telegramUser.first_name')->label('User'),
                Tables\Columns\TextColumn::make('plan_key')->badge(),
                Tables\Columns\TextColumn::make('stars_amount')->label('★')->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn ($state) => match ($state) {
                    Payment::STATUS_PAID => 'success',
                    Payment::STATUS_REFUNDED => 'warning',
                    Payment::STATUS_FAILED => 'danger',
                    default => 'gray',
                }),
                Tables\Columns\TextColumn::make('telegram_payment_charge_id')->label('Charge ID')->copyable()->limit(20),
                Tables\Columns\TextColumn::make('paid_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    Payment::STATUS_PENDING => 'Pending',
                    Payment::STATUS_PAID => 'Paid',
                    Payment::STATUS_REFUNDED => 'Refunded',
                    Payment::STATUS_FAILED => 'Failed',
                ]),
            ])
            ->actions([
                Tables\Actions\Action::make('refund')
                    ->label('Refund')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('This calls Telegram refundStarPayment and disables the user\'s clients. Reversible only via admin support.')
                    ->visible(fn (Payment $record) => $record->status === Payment::STATUS_PAID && $record->telegram_payment_charge_id)
                    ->action(function (Payment $record) {
                        $billing = App::make(TelegramBillingService::class);
                        $ok = $billing->refundStarPayment($record->telegram_user_id, $record->telegram_payment_charge_id);
                        if (!$ok) {
                            Notification::make()->danger()->title('Telegram refund failed')->send();
                            return;
                        }
                        if ($record->subscription) {
                            App::make(SubscriptionService::class)->refund($record->subscription, $record);
                        } else {
                            $record->update([
                                'status' => Payment::STATUS_REFUNDED,
                                'refunded_at' => now(),
                            ]);
                        }
                        (new self)->logAdminAction('payment.refund', $record);
                        Notification::make()->success()->title('Refunded')->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
        ];
    }
}
