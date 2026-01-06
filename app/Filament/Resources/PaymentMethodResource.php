<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentMethodResource\Pages;
use App\Models\PaymentMethod;
use App\Models\Currency;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentMethodResource extends Resource
{
    protected static ?string $model = PaymentMethod::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'Métodos de Pago';
    protected static ?string $modelLabel = 'Método de Pago';
    protected static ?string $navigationGroup = 'Configuración';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre del Método')
                            ->placeholder('Ej: Binance Pay, Banco LAFISE')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('currency')
                            ->label('Moneda')
                            ->options(function () {
                                // Cargar monedas activas desde la BD
                                return Currency::where('is_active', true)
                                    ->orderByDesc('is_base')
                                    ->orderBy('code')
                                    ->get()
                                    ->mapWithKeys(function ($currency) {
                                        return [$currency->code => $currency->symbol . ' ' . $currency->name . ' (' . $currency->code . ')'];
                                    });
                            })
                            ->default('USD')
                            ->searchable()
                            ->required()
                            ->native(false),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Método')
                    ->searchable(),
                Tables\Columns\TextColumn::make('currency')
                    ->label('Moneda')
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'USD' => 'success',
                        'NIO' => 'warning',
                        'USDT' => 'info',
                        'EUR' => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Estado')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('currency')
                    ->label('Moneda')
                    ->options(function () {
                        return Currency::where('is_active', true)
                            ->pluck('name', 'code');
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentMethods::route('/'),
            'create' => Pages\CreatePaymentMethod::route('/create'),
            'edit' => Pages\EditPaymentMethod::route('/{record}/edit'),
        ];
    }
}