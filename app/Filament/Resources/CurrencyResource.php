<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CurrencyResource\Pages;
use App\Models\Currency;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CurrencyResource extends Resource
{
    protected static ?string $model = Currency::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Divisas';
    protected static ?string $modelLabel = 'Divisa';
    protected static ?string $pluralModelLabel = 'Divisas';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información de la Divisa')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('code')
                                    ->label('Código')
                                    ->placeholder('USD, EUR, NIO')
                                    ->required()
                                    ->maxLength(3)
                                    ->unique(ignoreRecord: true)
                                    ->uppercase(),

                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre')
                                    ->placeholder('Dólar Estadounidense')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('symbol')
                                    ->label('Símbolo')
                                    ->placeholder('$, €, C$')
                                    ->required()
                                    ->maxLength(10),

                                Forms\Components\TextInput::make('country_code')
                                    ->label('Código de País (para bandera)')
                                    ->placeholder('US, EU, NI')
                                    ->helperText('Código ISO de 2 letras para mostrar la bandera')
                                    ->required()
                                    ->maxLength(2)
                                    ->uppercase(),
                            ]),
                    ]),

                Forms\Components\Section::make('Tasa de Cambio')
                    ->description('1 USD = X unidades de esta moneda')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('exchange_rate')
                                    ->label('Tasa de Cambio')
                                    ->helperText('Ejemplo: 1 USD = 37 NIO, entonces ingresa 37')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0.000001)
                                    ->step(0.000001)
                                    ->default(1)
                                    ->disabled(fn (Forms\Get $get) => $get('is_base') === true),

                                Forms\Components\Toggle::make('is_base')
                                    ->label('¿Es moneda base? (USD)')
                                    ->helperText('Solo una moneda puede ser la base (USD)')
                                    ->inline(false)
                                    ->disabled(fn ($record) => $record?->is_base === true),
                            ]),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Activa para uso')
                            ->default(true)
                            ->inline(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Divisa')
                    ->searchable(['code', 'name'])
                    ->sortable(['code'])
                    ->weight('bold')
                    ->size('lg'),

                Tables\Columns\TextColumn::make('symbol')
                    ->label('Símbolo')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('exchange_rate')
                    ->label('Tasa (1 USD =)')
                    ->formatStateUsing(fn ($state, $record) =>
                        $record->is_base ? 'BASE' : number_format($state, 6) . ' ' . $record->code
                    )
                    ->badge()
                    ->color(fn ($record) => $record->is_base ? 'success' : 'info'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Última Actualización')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('is_base', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCurrencies::route('/'),
            'create' => Pages\CreateCurrency::route('/create'),
            'edit' => Pages\EditCurrency::route('/{record}/edit'),
        ];
    }
}
