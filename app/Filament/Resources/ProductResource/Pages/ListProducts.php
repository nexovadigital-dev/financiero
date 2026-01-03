<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Pages\ApiSettings;
use App\Filament\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductSupplierPrice;
use App\Models\Supplier;
use Automattic\WooCommerce\Client;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Crear Nuevo'),

            // GRUPO DE ACCIONES DE SINCRONIZACIÓN
            Actions\ActionGroup::make([
                // 1. SINCRONIZAR WOOCOMMERCE
                $this->getWooCommerceAction(),

                // 2. SINCRONIZAR DHRU FUSION
                $this->getDhruAction(),
            ])
                ->label('Sincronizar')
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->button(),
        ];
    }

    /**
     * Acción para sincronizar WooCommerce
     */
    protected function getWooCommerceAction(): Actions\Action
    {
        return Actions\Action::make('syncWoo')
            ->label('🏪 Tienda (WooCommerce)')
            ->icon('heroicon-o-shopping-bag')
            ->color('info')
            ->modalHeading('📦 Sincronizar productos de WooCommerce')
            ->modalWidth('7xl')
            ->modalSubmitActionLabel('Importar Seleccionados')
            ->modalCancelActionLabel('Cancelar')
            ->form(function (): array {
                // Verificar credenciales
                $wooUrl = ApiSettings::getWooUrl();
                $wooKey = ApiSettings::getWooKey();
                $wooSecret = ApiSettings::getWooSecret();

                if (!$wooUrl || !$wooKey || !$wooSecret) {
                    return [
                        Forms\Components\Placeholder::make('error')
                            ->label('')
                            ->content(new \Illuminate\Support\HtmlString(
                                '<div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                                    <p class="text-red-800 font-medium">❌ Credenciales no configuradas</p>
                                    <p class="text-red-600 text-sm mt-1">Configure las credenciales de WooCommerce en Configuración > Configuración API</p>
                                </div>'
                            )),
                    ];
                }

                // Obtener productos de WooCommerce
                try {
                    $woocommerce = new Client(
                        $wooUrl,
                        $wooKey,
                        $wooSecret,
                        ['version' => 'wc/v3', 'verify_ssl' => true, 'timeout' => 120]
                    );

                    $products = $woocommerce->get('products', ['per_page' => 100, 'status' => 'any']);
                    $wooProducts = [];

                    foreach ($products as $product) {
                        if ($product->type === 'variable') {
                            $variations = $woocommerce->get("products/{$product->id}/variations", ['per_page' => 50]);
                            foreach ($variations as $variation) {
                                $attributes = array_map(fn($attr) => $attr->option, $variation->attributes);
                                $variationName = $product->name . ' - ' . implode(', ', $attributes);
                                $varExistsInDb = Product::where('woocommerce_product_id', $variation->id)->exists();

                                $wooProducts[$variation->id] = [
                                    'id' => $variation->id,
                                    'name' => $variationName,
                                    'price' => floatval($variation->price ?: 0),
                                    'sku' => $variation->sku ?: '',
                                    'status' => $product->status,
                                    'stock_status' => $variation->stock_status ?? 'instock',
                                    'exists' => $varExistsInDb,
                                ];
                            }
                        } else {
                            $existsInDb = Product::where('woocommerce_product_id', $product->id)->exists();
                            $wooProducts[$product->id] = [
                                'id' => $product->id,
                                'name' => $product->name,
                                'price' => floatval($product->price ?: 0),
                                'sku' => $product->sku ?: '',
                                'status' => $product->status,
                                'stock_status' => $product->stock_status ?? 'instock',
                                'exists' => $existsInDb,
                            ];
                        }
                    }

                    if (empty($wooProducts)) {
                        return [
                            Forms\Components\Placeholder::make('empty')
                                ->label('')
                                ->content('No se encontraron productos en WooCommerce'),
                        ];
                    }

                    // Crear opciones y descripciones
                    $options = [];
                    $descriptions = [];
                    foreach ($wooProducts as $id => $product) {
                        $status = $product['exists'] ? '🔄' : '🆕';
                        $options[(string)$id] = $product['name'];
                        $descriptions[(string)$id] = sprintf(
                            '%s %s | $%.2f USD | SKU: %s',
                            $status,
                            $product['exists'] ? 'Actualizar' : 'Nuevo',
                            $product['price'],
                            $product['sku'] ?: 'N/A'
                        );
                    }

                    $total = count($wooProducts);
                    $existing = count(array_filter($wooProducts, fn($p) => $p['exists']));
                    $new = $total - $existing;

                    return [
                        Forms\Components\Hidden::make('woo_products_data')
                            ->default(json_encode($wooProducts)),

                        Forms\Components\Section::make('✅ Conexión Exitosa')
                            ->schema([
                                Forms\Components\Placeholder::make('stats')
                                    ->label('')
                                    ->content(new \Illuminate\Support\HtmlString(
                                        "<div class='flex gap-4 flex-wrap'>
                                            <span class='px-3 py-1 bg-blue-100 text-blue-800 rounded-full font-medium'>📦 Total: {$total}</span>
                                            <span class='px-3 py-1 bg-green-100 text-green-800 rounded-full font-medium'>🆕 Nuevos: {$new}</span>
                                            <span class='px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full font-medium'>🔄 Existentes: {$existing}</span>
                                        </div>"
                                    )),
                            ]),

                        Forms\Components\Section::make('Seleccionar Productos')
                            ->description('Marque los productos que desea importar. El precio se asignará como costo base al primer proveedor.')
                            ->schema([
                                Forms\Components\CheckboxList::make('selected_products')
                                    ->label('')
                                    ->options($options)
                                    ->descriptions($descriptions)
                                    ->columns(1)
                                    ->searchable()
                                    ->bulkToggleable()
                                    ->default(array_keys($options)),
                            ])
                            ->collapsible(),
                    ];

                } catch (\Exception $e) {
                    Log::error('Error WooCommerce: ' . $e->getMessage());
                    return [
                        Forms\Components\Placeholder::make('error')
                            ->label('')
                            ->content(new \Illuminate\Support\HtmlString(
                                '<div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                                    <p class="text-red-800 font-medium">❌ Error de conexión</p>
                                    <p class="text-red-600 text-sm mt-1">' . htmlspecialchars($e->getMessage()) . '</p>
                                </div>'
                            )),
                    ];
                }
            })
            ->action(function (array $data): void {
                $selectedIds = $data['selected_products'] ?? [];
                $productsData = json_decode($data['woo_products_data'] ?? '{}', true);

                if (empty($selectedIds)) {
                    Notification::make()
                        ->warning()
                        ->title('Sin selección')
                        ->body('No seleccionó ningún producto para importar')
                        ->send();
                    return;
                }

                // Obtener el primer proveedor para asignar precio base
                $firstSupplier = Supplier::first();

                $count = 0;
                $updated = 0;

                foreach ($productsData as $id => $product) {
                    if (!in_array((string)$id, $selectedIds)) {
                        continue;
                    }

                    $exists = $product['exists'] ?? false;
                    $isActive = ($product['status'] === 'publish' && $product['stock_status'] === 'instock');

                    // Crear o actualizar producto
                    $dbProduct = Product::withTrashed()->updateOrCreate(
                        ['woocommerce_product_id' => $id],
                        [
                            'name' => $product['name'],
                            'price' => $product['price'],
                            'sku' => $product['sku'],
                            'type' => 'digital_product',
                            'is_active' => $isActive,
                            'deleted_at' => null,
                        ]
                    );

                    // Asignar precio base al primer proveedor si existe
                    if ($firstSupplier && $product['price'] > 0) {
                        ProductSupplierPrice::updateOrCreate(
                            [
                                'product_id' => $dbProduct->id,
                                'supplier_id' => $firstSupplier->id,
                            ],
                            [
                                'base_price' => $product['price'],
                            ]
                        );
                    }

                    if ($exists) {
                        $updated++;
                    } else {
                        $count++;
                    }
                }

                Notification::make()
                    ->success()
                    ->title('✅ Importación completada')
                    ->body("Se importaron {$count} productos nuevos y se actualizaron {$updated} existentes" .
                        ($firstSupplier ? ". Precio base asignado a: {$firstSupplier->name}" : ""))
                    ->send();
            });
    }

    /**
     * Acción para sincronizar DHRU Fusion
     */
    protected function getDhruAction(): Actions\Action
    {
        return Actions\Action::make('syncDhru')
            ->label('🖥️ Servidor (DHRU)')
            ->icon('heroicon-o-server')
            ->color('warning')
            ->modalHeading('🖥️ Sincronizar servicios de DHRU Fusion')
            ->modalWidth('7xl')
            ->modalSubmitActionLabel('Importar Seleccionados')
            ->modalCancelActionLabel('Cancelar')
            ->form(function (): array {
                // Verificar credenciales
                $url = ApiSettings::getDhruUrl();
                $username = ApiSettings::getDhruUsername();
                $key = ApiSettings::getDhruKey();

                if (!$url || !$key) {
                    return [
                        Forms\Components\Placeholder::make('error')
                            ->label('')
                            ->content(new \Illuminate\Support\HtmlString(
                                '<div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                                    <p class="text-red-800 font-medium">❌ Credenciales no configuradas</p>
                                    <p class="text-red-600 text-sm mt-1">Configure las credenciales de DHRU Fusion en Configuración > Configuración API</p>
                                </div>'
                            )),
                    ];
                }

                // Obtener servicios de DHRU
                try {
                    $response = Http::asForm()->timeout(120)->post(rtrim($url, '/') . '/api/index.php', [
                        'username' => $username,
                        'apiaccesskey' => $key,
                        'action' => 'imeiservicelist',
                        'requestformat' => 'JSON',
                    ]);

                    if ($response->failed()) {
                        throw new \Exception('Error conectando al servidor DHRU (HTTP ' . $response->status() . ')');
                    }

                    $json = $response->json();

                    if (!isset($json['SUCCESS']) && !isset($json['success'])) {
                        $errorMsg = $json['ERROR'] ?? $json['error'] ?? 'Respuesta inválida o credenciales incorrectas';
                        throw new \Exception($errorMsg);
                    }

                    $dhruProducts = [];
                    $successData = $json['SUCCESS'] ?? $json['success'] ?? [];

                    // Parsear estructura DHRU
                    self::parseDhruServicesStatic($successData, $dhruProducts);

                    if (empty($dhruProducts)) {
                        return [
                            Forms\Components\Placeholder::make('empty')
                                ->label('')
                                ->content('No se encontraron servicios en DHRU Fusion'),
                        ];
                    }

                    // Crear opciones y descripciones
                    $options = [];
                    $descriptions = [];
                    foreach ($dhruProducts as $id => $service) {
                        $status = $service['exists'] ? '🔄' : '🆕';
                        $group = $service['group'] ? "[{$service['group']}] " : '';
                        $options[(string)$id] = $group . $service['name'];
                        $descriptions[(string)$id] = sprintf(
                            '%s %s | 💳 %.2f Créditos | ⏱️ %s',
                            $status,
                            $service['exists'] ? 'Actualizar' : 'Nuevo',
                            $service['price'],
                            $service['time']
                        );
                    }

                    $total = count($dhruProducts);
                    $existing = count(array_filter($dhruProducts, fn($p) => $p['exists']));
                    $new = $total - $existing;

                    return [
                        Forms\Components\Hidden::make('dhru_products_data')
                            ->default(json_encode($dhruProducts)),

                        Forms\Components\Section::make('✅ Conexión Exitosa')
                            ->schema([
                                Forms\Components\Placeholder::make('stats')
                                    ->label('')
                                    ->content(new \Illuminate\Support\HtmlString(
                                        "<div class='flex gap-4 flex-wrap'>
                                            <span class='px-3 py-1 bg-purple-100 text-purple-800 rounded-full font-medium'>🖥️ Total: {$total}</span>
                                            <span class='px-3 py-1 bg-green-100 text-green-800 rounded-full font-medium'>🆕 Nuevos: {$new}</span>
                                            <span class='px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full font-medium'>🔄 Existentes: {$existing}</span>
                                        </div>"
                                    )),
                            ]),

                        Forms\Components\Section::make('Seleccionar Servicios')
                            ->description('Marque los servicios que desea importar. El precio en créditos se asignará como costo base al primer proveedor.')
                            ->schema([
                                Forms\Components\CheckboxList::make('selected_services')
                                    ->label('')
                                    ->options($options)
                                    ->descriptions($descriptions)
                                    ->columns(1)
                                    ->searchable()
                                    ->bulkToggleable()
                                    ->default(array_keys($options)),
                            ])
                            ->collapsible(),
                    ];

                } catch (\Exception $e) {
                    Log::error('Error DHRU: ' . $e->getMessage());
                    return [
                        Forms\Components\Placeholder::make('error')
                            ->label('')
                            ->content(new \Illuminate\Support\HtmlString(
                                '<div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                                    <p class="text-red-800 font-medium">❌ Error de conexión</p>
                                    <p class="text-red-600 text-sm mt-1">' . htmlspecialchars($e->getMessage()) . '</p>
                                </div>'
                            )),
                    ];
                }
            })
            ->action(function (array $data): void {
                $selectedIds = $data['selected_services'] ?? [];
                $servicesData = json_decode($data['dhru_products_data'] ?? '{}', true);

                if (empty($selectedIds)) {
                    Notification::make()
                        ->warning()
                        ->title('Sin selección')
                        ->body('No seleccionó ningún servicio para importar')
                        ->send();
                    return;
                }

                // Obtener el primer proveedor para asignar precio base
                $firstSupplier = Supplier::first();

                $count = 0;
                $updated = 0;

                foreach ($servicesData as $id => $service) {
                    if (!in_array((string)$id, $selectedIds)) {
                        continue;
                    }

                    $exists = $service['exists'] ?? false;

                    // Crear o actualizar producto
                    $dbProduct = Product::updateOrCreate(
                        ['sku' => $service['sku']],
                        [
                            'name' => $service['name'],
                            'price' => $service['price'],
                            'type' => 'service',
                            'is_active' => true,
                        ]
                    );

                    // Asignar precio base al primer proveedor si existe
                    if ($firstSupplier && $service['price'] > 0) {
                        ProductSupplierPrice::updateOrCreate(
                            [
                                'product_id' => $dbProduct->id,
                                'supplier_id' => $firstSupplier->id,
                            ],
                            [
                                'base_price' => $service['price'],
                            ]
                        );
                    }

                    if ($exists) {
                        $updated++;
                    } else {
                        $count++;
                    }
                }

                Notification::make()
                    ->success()
                    ->title('✅ Importación completada')
                    ->body("Se importaron {$count} servicios nuevos y se actualizaron {$updated} existentes" .
                        ($firstSupplier ? ". Precio base asignado a: {$firstSupplier->name}" : ""))
                    ->send();
            });
    }

    /**
     * Parsear estructura de servicios DHRU
     */
    protected static function parseDhruServicesStatic(array $data, array &$dhruProducts): void
    {
        if (isset($data[0]['LIST'])) {
            $data = $data[0]['LIST'];
        }

        foreach ($data as $item) {
            if (isset($item['GROUPNAME']) && isset($item['SERVICES'])) {
                $groupName = $item['GROUPNAME'];
                foreach ($item['SERVICES'] as $service) {
                    self::addDhruServiceStatic($service, $groupName, $dhruProducts);
                }
            } elseif (isset($item['SERVICEID'])) {
                self::addDhruServiceStatic($item, null, $dhruProducts);
            } elseif (isset($item['ID'])) {
                self::addDhruServiceStatic([
                    'SERVICEID' => $item['ID'],
                    'SERVICENAME' => $item['NAME'] ?? $item['SERVICENAME'] ?? 'Servicio',
                    'CREDIT' => $item['CREDIT'] ?? $item['PRICE'] ?? 0,
                    'TIME' => $item['TIME'] ?? 'N/A',
                ], null, $dhruProducts);
            }
        }
    }

    protected static function addDhruServiceStatic(array $service, ?string $group, array &$dhruProducts): void
    {
        $serviceId = $service['SERVICEID'] ?? $service['ID'] ?? null;
        if (!$serviceId) return;

        $sku = 'DHRU-' . $serviceId;
        $existsInDb = Product::where('sku', $sku)->exists();

        $dhruProducts[$serviceId] = [
            'id' => $serviceId,
            'name' => $service['SERVICENAME'] ?? $service['NAME'] ?? 'Servicio #' . $serviceId,
            'group' => $group,
            'price' => floatval($service['CREDIT'] ?? $service['PRICE'] ?? 0),
            'time' => $service['TIME'] ?? 'N/A',
            'sku' => $sku,
            'exists' => $existsInDb,
        ];
    }
}
