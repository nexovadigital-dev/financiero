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
use Illuminate\Support\Facades\Session;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    /**
     * Obtener productos de WooCommerce (guarda en sesión para persistir entre requests)
     */
    protected static function fetchWooCommerceProducts(bool $forceRefresh = false): array
    {
        // Verificar si tenemos datos en sesión y no se fuerza refresh
        if (!$forceRefresh && Session::has('woo_products_data')) {
            $sessionData = Session::get('woo_products_data');
            // Verificar que los datos no tengan más de 5 minutos
            if (isset($sessionData['timestamp']) && (time() - $sessionData['timestamp']) < 300) {
                return $sessionData;
            }
        }

        $wooUrl = ApiSettings::getWooUrl();
        $wooKey = ApiSettings::getWooKey();
        $wooSecret = ApiSettings::getWooSecret();

        if (!$wooUrl || !$wooKey || !$wooSecret) {
            return ['error' => 'Credenciales no configuradas. Configure en Configuración > Configuración API'];
        }

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

                        $wooProducts[(string)$variation->id] = [
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
                    $wooProducts[(string)$product->id] = [
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

            $result = [
                'products' => $wooProducts,
                'timestamp' => time(),
            ];

            // Guardar en sesión para persistir entre requests
            Session::put('woo_products_data', $result);

            Log::info('WooCommerce Products Fetched', [
                'count' => count($wooProducts),
                'product_ids' => array_keys($wooProducts),
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Error WooCommerce: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

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
                // Forzar refresh para obtener datos frescos
                $result = self::fetchWooCommerceProducts(forceRefresh: true);

                if (isset($result['error'])) {
                    return [
                        Forms\Components\Placeholder::make('error')
                            ->label('')
                            ->content(new \Illuminate\Support\HtmlString(
                                '<div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                                    <p class="text-red-800 font-medium">❌ Error</p>
                                    <p class="text-red-600 text-sm mt-1">' . htmlspecialchars($result['error']) . '</p>
                                </div>'
                            )),
                    ];
                }

                $wooProducts = $result['products'] ?? [];

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
            })
            ->action(function (array $data): void {
                $selectedIds = $data['selected_products'] ?? [];

                Log::info('WooCommerce Action Started', [
                    'selected_ids_raw' => $selectedIds,
                    'data_keys' => array_keys($data),
                ]);

                if (empty($selectedIds)) {
                    Notification::make()
                        ->warning()
                        ->title('Sin selección')
                        ->body('No seleccionó ningún producto para importar')
                        ->send();
                    return;
                }

                // Normalizar selectedIds a strings
                $selectedIds = array_map('strval', $selectedIds);

                // Obtener los productos de sesión (fueron guardados cuando se abrió el modal)
                $result = self::fetchWooCommerceProducts(forceRefresh: false);

                if (isset($result['error']) || empty($result['products'])) {
                    Log::error('WooCommerce Import: No products in session', [
                        'result' => $result,
                        'session_has' => Session::has('woo_products_data'),
                    ]);
                    Notification::make()
                        ->danger()
                        ->title('Error')
                        ->body('No se pudieron obtener los productos. Cierre el modal e intente nuevamente.')
                        ->send();
                    return;
                }

                $productsData = $result['products'];

                Log::info('WooCommerce Import Starting', [
                    'selected_count' => count($selectedIds),
                    'selected_ids' => $selectedIds,
                    'available_count' => count($productsData),
                    'available_ids' => array_keys($productsData),
                ]);

                // Obtener el primer proveedor para asignar precio base
                $firstSupplier = Supplier::first();

                $count = 0;
                $updated = 0;
                $errors = [];

                $savedProducts = [];

                foreach ($productsData as $id => $product) {
                    $idStr = (string)$id;
                    if (!in_array($idStr, $selectedIds, true)) {
                        continue;
                    }

                    try {
                        $isActive = (($product['status'] ?? '') === 'publish' && ($product['stock_status'] ?? 'instock') === 'instock');
                        $wooId = (int)$product['id'];

                        Log::info('WooCommerce: Attempting to save product', [
                            'woo_id' => $wooId,
                            'name' => $product['name'] ?? 'Sin nombre',
                            'price' => $product['price'] ?? 0,
                        ]);

                        // Crear o actualizar producto - usar firstOrNew + save para mejor control
                        $dbProduct = Product::withTrashed()
                            ->where('woocommerce_product_id', $wooId)
                            ->first();

                        $isNew = !$dbProduct;

                        if (!$dbProduct) {
                            $dbProduct = new Product();
                            $dbProduct->woocommerce_product_id = $wooId;
                        }

                        $dbProduct->name = $product['name'] ?? 'Sin nombre';
                        $dbProduct->price = floatval($product['price'] ?? 0);
                        $dbProduct->sku = !empty($product['sku']) ? $product['sku'] : null;
                        $dbProduct->type = 'digital_product';
                        $dbProduct->is_active = $isActive;
                        $dbProduct->deleted_at = null;
                        $dbProduct->save();

                        Log::info('WooCommerce Product Saved Successfully', [
                            'woo_id' => $wooId,
                            'db_id' => $dbProduct->id,
                            'name' => $dbProduct->name,
                            'is_new' => $isNew,
                        ]);

                        $savedProducts[] = [
                            'id' => $dbProduct->id,
                            'name' => $dbProduct->name,
                            'is_new' => $isNew,
                        ];

                        // Asignar precio base al primer proveedor si existe
                        if ($firstSupplier && floatval($product['price'] ?? 0) > 0) {
                            ProductSupplierPrice::updateOrCreate(
                                [
                                    'product_id' => $dbProduct->id,
                                    'supplier_id' => $firstSupplier->id,
                                ],
                                [
                                    'base_price' => floatval($product['price']),
                                ]
                            );
                        }

                        if ($isNew) {
                            $count++;
                        } else {
                            $updated++;
                        }
                    } catch (\Exception $e) {
                        $errors[] = ($product['name'] ?? $id) . ': ' . $e->getMessage();
                        Log::error('WooCommerce Product Import Error', [
                            'id' => $id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }

                // Limpiar sesión después de importar
                Session::forget('woo_products_data');

                // Verificar cuántos productos WooCommerce hay realmente en la BD
                $totalWooProducts = Product::whereNotNull('woocommerce_product_id')->count();

                Log::info('WooCommerce Import Completed', [
                    'new_count' => $count,
                    'updated_count' => $updated,
                    'errors_count' => count($errors),
                    'saved_products' => $savedProducts,
                    'total_woo_products_in_db' => $totalWooProducts,
                ]);

                $message = "Se guardaron {$count} productos nuevos y se actualizaron {$updated} existentes.";
                if (!empty($savedProducts)) {
                    $names = array_slice(array_column($savedProducts, 'name'), 0, 3);
                    $message .= " Productos: " . implode(', ', $names);
                    if (count($savedProducts) > 3) {
                        $message .= "...";
                    }
                }
                $message .= " (Total WooCommerce en BD: {$totalWooProducts})";

                if (!empty($errors)) {
                    Notification::make()
                        ->warning()
                        ->title('⚠️ Importación con errores')
                        ->body($message . ". Errores: " . count($errors))
                        ->persistent()
                        ->send();

                    Log::warning('WooCommerce Import completed with errors', ['errors' => $errors]);
                } else if ($count > 0 || $updated > 0) {
                    Notification::make()
                        ->success()
                        ->title('✅ Importación completada')
                        ->body($message)
                        ->persistent()
                        ->send();
                } else {
                    Notification::make()
                        ->warning()
                        ->title('⚠️ Sin cambios')
                        ->body('No se guardó ningún producto. Verifique la selección.')
                        ->persistent()
                        ->send();
                }
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
