<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\LogsAdminActions;
use App\Filament\Resources\VpnClientResource\Pages;
use App\Models\VpnClient;
use App\Services\XuiClientFactory;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\App;

class VpnClientResource extends Resource
{
    use LogsAdminActions;

    protected static ?string $model = VpnClient::class;
    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?string $navigationGroup = 'Infrastructure';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('telegram_user_id')->label('TG ID')->searchable(),
                Tables\Columns\TextColumn::make('server.slug')->label('Server'),
                Tables\Columns\TextColumn::make('email')->limit(28),
                Tables\Columns\IconColumn::make('enabled')->boolean(),
                Tables\Columns\TextColumn::make('quota_bytes')->label('Quota')
                    ->formatStateUsing(fn ($state) => $state === null ? '∞' : self::fmtBytes((int) $state)),
                Tables\Columns\TextColumn::make('last_traffic_up')->label('Used')
                    ->formatStateUsing(fn ($record) => self::fmtBytes((int) $record->last_traffic_up + (int) $record->last_traffic_down)),
                Tables\Columns\TextColumn::make('expires_at')->dateTime(),
                Tables\Columns\TextColumn::make('disabled_reason')->badge()->toggleable(),
            ])
            ->actions([
                Tables\Actions\Action::make('toggle_enabled')
                    ->label(fn (VpnClient $record) => $record->enabled ? 'Disable' : 'Enable')
                    ->icon('heroicon-o-power')
                    ->requiresConfirmation()
                    ->action(function (VpnClient $record) {
                        $newState = !$record->enabled;
                        $record->update([
                            'enabled' => $newState,
                            'disabled_reason' => $newState ? null : 'admin',
                        ]);
                        if ($record->server) {
                            try {
                                $xui = App::make(XuiClientFactory::class)->forServer($record->server);
                                $newState ? null : $xui->disableClient($record->uuid);
                            } catch (\Throwable) {
                            }
                        }
                        (new self)->logAdminAction($newState ? 'vpn_client.enable' : 'vpn_client.disable', $record);
                        Notification::make()->success()->title('Updated')->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVpnClients::route('/'),
        ];
    }

    private static function fmtBytes(int $b): string
    {
        if ($b <= 0) return '0 B';
        $u = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($b >= 1024 && $i < count($u) - 1) { $b = (int) ($b / 1024); $i++; }
        return $b . ' ' . $u[$i];
    }
}
