<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\PricePackage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationLabel = 'Biblioteca de Servicios';
    protected static ?string $modelLabel = 'Producto/Servicio';
    protected static ?string $navigationGroup = 'Gestión';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'sku'];
    }

    public static function getGlobalSearchResultTitle($record): string
    {
        return $record->name;
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        $details = ['Precio Base' => '$' . number_format($record->base_price ?? 0, 2)];
        if ($record->sku) {
            $details['SKU'] = $record->sku;
        }
        return $details;
    }

    /**
     * Genera campos de precio dinámicos basados en los paquetes configurados
     */
    protected static function getPricePackageFields(): array
    {
        $packages = PricePackage::where('is_active', true)->orderBy('sort_order')->get();

        if ($packages->isEmpty()) {
            return [
                Forms\Components\Placeholder::make('no_packages')
                    ->label('')
                    ->content('No hay paquetes de precios configurados. Ve a Configuración > Price Packages para crear paquetes.')
                    ->columnSpanFull(),
            ];
        }

        $fields = [];
        foreach ($packages as $package) {
            // Usar el ID del paquete para mapear al campo correcto en products (soporta hasta 10)
            $packageId = $package->id;
            if ($packageId < 1 || $packageId > 10) {
                continue; // Soportamos hasta 10 paquetes (price_package_1 a price_package_10)
            }

            $fieldName = 'price_package_' . $packageId;
            $fields[] = Forms\Components\TextInput::make($fieldName)
                ->label("📦 {$package->name}")
                ->numeric()
                ->prefix('$')
                ->default(0)
                ->minValue(0)
                ->step(0.01)
                ->helperText($package->description ?? "Precio para paquete {$package->name}");
        }

        return [
            Forms\Components\Grid::make(2)->schema($fields),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detalles del Producto / Servicio')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre del Artículo / Servicio')
                            ->required()
                            ->minLength(3)
                            ->maxLength(255)
                            ->placeholder('Ejemplo: VPS Cloud 2GB RAM')
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('type')
                                    ->label('Tipo')
                                    ->options([
                                        'digital_product' => 'Artículo Tienda',
                                        'service' => 'Servicio Servidor',
                                        'server_credit' => 'Crédito Servidor',
                                    ])
                                    ->default('digital_product')
                                    ->required()
                                    ->native(false),

                                Forms\Components\TextInput::make('sku')
                                    ->label('SKU (Código)')
                                    ->placeholder('Sincronizable con Tienda')
                                    ->maxLength(255)
                                    ->alphaDash(),
                            ]),
                            
                        Forms\Components\Toggle::make('is_active')
                            ->label('Disponible para venta (Stock / Activo)')
                            ->default(true)
                            ->inline(false),
                    ])->columns(1),

                Forms\Components\Section::make('💰 Precios Base por Proveedor')
                    ->description('Configure el precio base (costo) para cada proveedor. Al vender, se usará el precio del proveedor seleccionado.')
                    ->schema([
                        Forms\Components\Repeater::make('supplierPrices')
                            ->relationship()
                            ->label('Precios por Proveedor')
                            ->schema([
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\Select::make('supplier_id')
                                            ->label('Proveedor')
                                            ->relationship('supplier', 'name')
                                            ->required()
                                            ->distinct()
                                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                            ->native(false)
                                            ->preload()
                                            ->live(),

                                        Forms\Components\TextInput::make('base_price')
                                            ->label('Precio Base (USDT)')
                                            ->numeric()
                                            ->prefix('$')
                                            ->default(0)
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->required()
                                            ->helperText('Costo en créditos USDT'),

                                        Forms\Components\TextInput::make('base_price_nio')
                                            ->label('Precio Base (NIO)')
                                            ->numeric()
                                            ->prefix('C$')
                                            ->default(null)
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->helperText('Precio en Córdobas para exportación al banco'),

                                        Forms\Components\TextInput::make('base_price_usd_nic')
                                            ->label('Precio Base (USD-Nicaragua)')
                                            ->numeric()
                                            ->prefix('$')
                                            ->default(null)
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->helperText('Precio en Dólares Nicaragua para exportación al banco'),
                                    ]),
                            ])
                            ->defaultItems(0)
                            ->addActionLabel('+ Agregar Proveedor')
                            ->collapsible()
                            ->itemLabel(function (array $state): ?string {
                                $supplier = \App\Models\Supplier::find($state['supplier_id']);
                                $label = $supplier?->name . ' - $' . number_format($state['base_price'] ?? 0, 2) . ' USDT';
                                if (($state['base_price_nio'] ?? 0) > 0) {
                                    $label .= ' / C$' . number_format($state['base_price_nio'], 2) . ' NIO';
                                }
                                if (($state['base_price_usd_nic'] ?? 0) > 0) {
                                    $label .= ' / $' . number_format($state['base_price_usd_nic'], 2) . ' USD-Nic';
                                }
                                return $label;
                            })
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('supplier_prices_note')
                            ->label('📝 Nota Importante')
                            ->content('Al reportar una venta, deberá seleccionar el proveedor y el sistema usará automáticamente el precio base de ese proveedor. Los paquetes de cliente (abajo) se aplicarán sobre el precio del proveedor elegido.')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(false),

                Forms\Components\Section::make('📦 Precios para Paquetes de Cliente')
                    ->description('Configure los precios de venta para cada paquete de cliente. Estos precios se mostrarán automáticamente según el paquete del cliente.')
                    ->schema(static::getPricePackageFields())
                    ->collapsible()
                    ->collapsed(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->weight('bold')
                    ->limit(50),

                // Indicador de precios pendientes
                Tables\Columns\TextColumn::make('supplierPrices')
                    ->label('Precios Base')
                    ->badge()
                    ->color(fn ($record) => $record->supplierPrices->count() > 0 ? 'success' : 'danger')
                    ->formatStateUsing(fn ($record) => $record->supplierPrices->count() > 0
                        ? '✓ ' . $record->supplierPrices->count() . ' proveedor(es)'
                        : '⚠️ Sin configurar'
                    )
                    ->tooltip(fn ($record) => $record->supplierPrices->count() > 0
                        ? $record->supplierPrices->map(fn($p) => $p->supplier?->name . ': $' . number_format($p->base_price, 2))->join(', ')
                        : 'Este producto no tiene precios base configurados. Haga clic para configurar.'
                    ),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'service' => 'warning',
                        'server_credit' => 'info',
                        'digital_product' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'service' => 'Servicio',
                        'server_credit' => 'Crédito',
                        'digital_product' => 'Tienda',
                        default => $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->toggleable()
                    ->color('gray'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            // Mostrar solo productos NO eliminados (deleted_at IS NULL)
            // La sincronización WooCommerce restaura productos con deleted_at = null
            ->modifyQueryUsing(fn ($query) => $query
                ->whereNull('deleted_at')
                ->orderByRaw("
                    CASE type
                        WHEN 'digital_product' THEN 1
                        WHEN 'service' THEN 2
                        WHEN 'server_credit' THEN 3
                        ELSE 4
                    END, name ASC
                ")
            )
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Filtrar por Tipo')
                    ->options([
                        'digital_product' => '🏪 Artículo Tienda',
                        'service' => '🖥️ Servicio Servidor',
                        'server_credit' => '💳 Crédito Servidor',
                    ])
                    ->native(false)
                    ->placeholder('Todos los tipos'),

                // Filtro para productos sin precios configurados
                Tables\Filters\Filter::make('sin_precios')
                    ->label('⚠️ Sin precios configurados')
                    ->query(fn ($query) => $query->whereDoesntHave('supplierPrices'))
                    ->toggle(),
            ])
            ->actions([
                // Acción rápida para configurar precios
                Tables\Actions\Action::make('configurar_precios')
                    ->label('Configurar')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('warning')
                    ->visible(fn ($record) => $record->supplierPrices->count() === 0)
                    ->url(fn ($record) => static::getUrl('edit', ['record' => $record])),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar Producto/Servicio')
                    ->modalDescription('¿Está seguro que desea eliminar este producto/servicio?')
                    ->modalSubmitActionLabel('Sí, eliminar'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar Productos/Servicios')
                        ->modalDescription('¿Está seguro que desea eliminar los productos/servicios seleccionados?')
                        ->modalSubmitActionLabel('Sí, eliminar todos'),
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}