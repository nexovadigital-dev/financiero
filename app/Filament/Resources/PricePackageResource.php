<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PricePackageResource\Pages;
use App\Models\PricePackage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PricePackageResource extends Resource
{
    protected static ?string $model = PricePackage::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationLabel = 'Paquetes de Precios';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información del Paquete')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre del Paquete')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ej: Paquete Premium'),

                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->placeholder('Descripción del paquete de precios'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true)
                            ->helperText('Los paquetes inactivos no aparecerán en las ventas'),

                        Forms\Components\Select::make('currency')
                            ->label('Moneda del Paquete')
                            ->options([
                                'USD' => '$ USD - Dólares',
                                'NIO' => 'C$ NIO - Córdobas',
                            ])
                            ->default('USD')
                            ->required()
                            ->helperText('Si es NIO, la venta debe ser en córdobas')
                            ->native(false),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Orden')
                            ->numeric()
                            ->default(0)
                            ->helperText('Orden en que aparece en la lista'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Orden')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('currency')
                    ->label('Moneda')
                    ->badge()
                    ->color(fn ($state) => $state === 'NIO' ? 'warning' : 'success')
                    ->formatStateUsing(fn ($state) => $state === 'NIO' ? 'C$ NIO' : '$ USD'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(50)
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('sales_count')
                    ->label('Ventas')
                    ->counts('sales')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activo')
                    ->placeholder('Todos')
                    ->trueLabel('Solo activos')
                    ->falseLabel('Solo inactivos'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
            'index' => Pages\ListPricePackages::route('/'),
            'create' => Pages\CreatePricePackage::route('/create'),
            'edit' => Pages\EditPricePackage::route('/{record}/edit'),
        ];
    }
}
