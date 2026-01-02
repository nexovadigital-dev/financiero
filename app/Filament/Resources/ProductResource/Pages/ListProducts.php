<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Pages\ApiSettings;
use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Automattic\WooCommerce\Client;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\On;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    // Propiedades para almacenar productos temporales
    public array $wooProducts = [];
    public array $dhruProducts = [];
    public array $selectedWooProducts = [];
    public array $selectedDhruProducts = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Crear Nuevo'),

            // GRUPO DE ACCIONES DE SINCRONIZACIÓN
            Actions\ActionGroup::make([

                // 1. SINCRONIZAR WOOCOMMERCE
                Actions\Action::make('syncWoo')
                    ->label('🏪 Tienda (WooCommerce)')
                    ->icon('heroicon-o-shopping-bag')
                    ->color('info')
                    ->action(fn () => $this->loadWooCommerceProducts()),

                // 2. SINCRONIZAR DHRU FUSION
                Actions\Action::make('syncDhru')
                    ->label('🖥️ Servidor (DHRU)')
                    ->icon('heroicon-o-server')
                    ->color('warning')
                    ->action(fn () => $this->loadDhruProducts()),
            ])
                ->label('Sincronizar')
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->button(),

            // Modal para selección de WooCommerce
            Actions\Action::make('selectWooProducts')
                ->label('Seleccionar Productos WooCommerce')
                ->modalHeading('📦 Productos de WooCommerce')
                ->modalDescription('Seleccione los productos que desea importar. Los productos ya existentes se actualizarán.')
                ->modalWidth('7xl')
                ->modalSubmitActionLabel('Importar Seleccionados')
                ->modalCancelActionLabel('Cancelar')
                ->form(fn () => $this->getWooProductsForm())
                ->action(fn (array $data) => $this->importSelectedWooProducts($data))
                ->visible(fn () => count($this->wooProducts) > 0)
                ->extraAttributes(['class' => 'hidden']),

            // Modal para selección de DHRU
            Actions\Action::make('selectDhruProducts')
                ->label('Seleccionar Servicios DHRU')
                ->modalHeading('🖥️ Servicios de DHRU Fusion')
                ->modalDescription('Seleccione los servicios que desea importar.')
                ->modalWidth('7xl')
                ->modalSubmitActionLabel('Importar Seleccionados')
                ->modalCancelActionLabel('Cancelar')
                ->form(fn () => $this->getDhruProductsForm())
                ->action(fn (array $data) => $this->importSelectedDhruProducts($data))
                ->visible(fn () => count($this->dhruProducts) > 0)
                ->extraAttributes(['class' => 'hidden']),
        ];
    }

    /**
     * Cargar productos de WooCommerce y mostrar modal
     */
    public function loadWooCommerceProducts(): void
    {
        // Usar credenciales de BD (ApiSettings) con fallback a .env
        $wooUrl = ApiSettings::getWooUrl();
        $wooKey = ApiSettings::getWooKey();
        $wooSecret = ApiSettings::getWooSecret();

        if (!$wooUrl || !$wooKey || !$wooSecret) {
            Notification::make()
                ->danger()
                ->title('❌ Credenciales no configuradas')
                ->body('Configure las credenciales de WooCommerce en Configuración > Configuración API')
                ->persistent()
                ->actions([
                    \Filament\Notifications\Actions\Action::make('configure')
                        ->label('Ir a Configuración')
                        ->url(ApiSettings::getUrl())
                        ->button(),
                ])
                ->send();
            return;
        }

        try {
            Notification::make()
                ->info()
                ->title('🔄 Conectando con WooCommerce...')
                ->body('Obteniendo lista de productos')
                ->send();

            $woocommerce = new Client(
                $wooUrl,
                $wooKey,
                $wooSecret,
                ['version' => 'wc/v3', 'verify_ssl' => true, 'timeout' => 60]
            );

            // Traer productos
            $products = $woocommerce->get('products', ['per_page' => 100, 'status' => 'any']);
            $this->wooProducts = [];

            foreach ($products as $product) {
                // Verificar si ya existe en la BD
                $existsInDb = Product::where('woocommerce_product_id', $product->id)->exists();

                if ($product->type === 'variable') {
                    // Obtener variantes
                    $variations = $woocommerce->get("products/{$product->id}/variations", ['per_page' => 50]);

                    foreach ($variations as $variation) {
                        $attributes = array_map(fn($attr) => $attr->option, $variation->attributes);
                        $variationName = $product->name . ' - ' . implode(', ', $attributes);
                        $varExistsInDb = Product::where('woocommerce_product_id', $variation->id)->exists();

                        $this->wooProducts[] = [
                            'id' => $variation->id,
                            'parent_id' => $product->id,
                            'name' => $variationName,
                            'price' => floatval($variation->price ?: 0),
                            'sku' => $variation->sku ?: '',
                            'status' => $product->status,
                            'stock_status' => $variation->stock_status ?? 'instock',
                            'type' => 'variable',
                            'exists' => $varExistsInDb,
                        ];
                    }
                } else {
                    $this->wooProducts[] = [
                        'id' => $product->id,
                        'parent_id' => null,
                        'name' => $product->name,
                        'price' => floatval($product->price ?: 0),
                        'sku' => $product->sku ?: '',
                        'status' => $product->status,
                        'stock_status' => $product->stock_status ?? 'instock',
                        'type' => $product->type,
                        'exists' => $existsInDb,
                    ];
                }
            }

            if (empty($this->wooProducts)) {
                Notification::make()
                    ->warning()
                    ->title('Sin productos')
                    ->body('No se encontraron productos en WooCommerce')
                    ->send();
                return;
            }

            Notification::make()
                ->success()
                ->title('✅ Conexión exitosa')
                ->body('Se encontraron ' . count($this->wooProducts) . ' productos/variantes')
                ->send();

            // Abrir modal de selección
            $this->dispatch('open-modal', id: 'selectWooProducts');
            $this->mountAction('selectWooProducts');

        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('❌ Error de conexión')
                ->body('Error: ' . $e->getMessage())
                ->persistent()
                ->send();
        }
    }

    /**
     * Formulario para seleccionar productos de WooCommerce
     */
    protected function getWooProductsForm(): array
    {
        if (empty($this->wooProducts)) {
            return [
                Forms\Components\Placeholder::make('empty')
                    ->content('No hay productos para mostrar')
            ];
        }

        // Crear opciones para el CheckboxList
        $options = [];
        $descriptions = [];

        foreach ($this->wooProducts as $product) {
            $key = (string) $product['id'];
            $status = $product['exists'] ? '🔄 Actualizar' : '🆕 Nuevo';
            $stockBadge = $product['stock_status'] === 'instock' ? '✅' : '❌';

            $options[$key] = $product['name'];
            $descriptions[$key] = sprintf(
                '%s | $%.2f USD | SKU: %s | %s',
                $status,
                $product['price'],
                $product['sku'] ?: 'N/A',
                $stockBadge . ' ' . ucfirst($product['stock_status'])
            );
        }

        return [
            Forms\Components\Section::make('Resumen')
                ->schema([
                    Forms\Components\Placeholder::make('stats')
                        ->label('')
                        ->content(function () {
                            $total = count($this->wooProducts);
                            $existing = count(array_filter($this->wooProducts, fn($p) => $p['exists']));
                            $new = $total - $existing;

                            return new \Illuminate\Support\HtmlString(
                                "<div class='flex gap-4'>
                                    <span class='px-3 py-1 bg-blue-100 text-blue-800 rounded-full font-medium'>📦 Total: {$total}</span>
                                    <span class='px-3 py-1 bg-green-100 text-green-800 rounded-full font-medium'>🆕 Nuevos: {$new}</span>
                                    <span class='px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full font-medium'>🔄 Existentes: {$existing}</span>
                                </div>"
                            );
                        }),
                ])
                ->collapsed(false),

            Forms\Components\Section::make('Seleccionar Productos')
                ->description('Marque los productos que desea importar')
                ->schema([
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('selectAll')
                            ->label('Seleccionar Todos')
                            ->icon('heroicon-o-check-circle')
                            ->color('success')
                            ->action(function (Forms\Set $set) {
                                $allIds = array_map(fn($p) => (string) $p['id'], $this->wooProducts);
                                $set('selected_products', $allIds);
                            }),
                        Forms\Components\Actions\Action::make('selectNew')
                            ->label('Solo Nuevos')
                            ->icon('heroicon-o-plus-circle')
                            ->color('info')
                            ->action(function (Forms\Set $set) {
                                $newIds = array_map(
                                    fn($p) => (string) $p['id'],
                                    array_filter($this->wooProducts, fn($p) => !$p['exists'])
                                );
                                $set('selected_products', $newIds);
                            }),
                        Forms\Components\Actions\Action::make('deselectAll')
                            ->label('Deseleccionar Todos')
                            ->icon('heroicon-o-x-circle')
                            ->color('gray')
                            ->action(function (Forms\Set $set) {
                                $set('selected_products', []);
                            }),
                    ]),

                    Forms\Components\CheckboxList::make('selected_products')
                        ->label('')
                        ->options($options)
                        ->descriptions($descriptions)
                        ->columns(1)
                        ->searchable()
                        ->bulkToggleable()
                        ->default(array_map(fn($p) => (string) $p['id'], $this->wooProducts)),
                ])
                ->collapsible(),
        ];
    }

    /**
     * Importar productos seleccionados de WooCommerce
     */
    public function importSelectedWooProducts(array $data): void
    {
        $selectedIds = $data['selected_products'] ?? [];

        if (empty($selectedIds)) {
            Notification::make()
                ->warning()
                ->title('Sin selección')
                ->body('No seleccionó ningún producto para importar')
                ->send();
            return;
        }

        $count = 0;
        $updated = 0;

        foreach ($this->wooProducts as $product) {
            if (!in_array((string) $product['id'], $selectedIds)) {
                continue;
            }

            $isActive = ($product['status'] === 'publish' && $product['stock_status'] === 'instock');
            $exists = $product['exists'];

            Product::withTrashed()->updateOrCreate(
                ['woocommerce_product_id' => $product['id']],
                [
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'sku' => $product['sku'],
                    'type' => 'digital_product', // Artículos de tienda
                    'is_active' => $isActive,
                    'deleted_at' => null,
                ]
            );

            if ($exists) {
                $updated++;
            } else {
                $count++;
            }
        }

        $this->wooProducts = []; // Limpiar

        Notification::make()
            ->success()
            ->title('✅ Importación completada')
            ->body("Se importaron {$count} productos nuevos y se actualizaron {$updated} existentes")
            ->send();
    }

    /**
     * Cargar servicios de DHRU Fusion
     */
    public function loadDhruProducts(): void
    {
        // Usar credenciales de BD (ApiSettings)
        $url = ApiSettings::getDhruUrl();
        $username = ApiSettings::getDhruUsername();
        $key = ApiSettings::getDhruKey();

        if (!$url || !$key) {
            Notification::make()
                ->danger()
                ->title('❌ Credenciales no configuradas')
                ->body('Configure las credenciales de DHRU Fusion en Configuración > Configuración API')
                ->persistent()
                ->actions([
                    \Filament\Notifications\Actions\Action::make('configure')
                        ->label('Ir a Configuración')
                        ->url(ApiSettings::getUrl())
                        ->button(),
                ])
                ->send();
            return;
        }

        try {
            Notification::make()
                ->info()
                ->title('🔄 Conectando con DHRU Fusion...')
                ->body('Obteniendo lista de servicios')
                ->send();

            // DHRU usa POST estándar
            $response = Http::asForm()->timeout(60)->post(rtrim($url, '/') . '/api/index.php', [
                'username' => $username,
                'apiaccesskey' => $key,
                'action' => 'imeiservicelist',
                'requestformat' => 'JSON',
            ]);

            if ($response->failed()) {
                throw new \Exception('Error conectando al servidor DHRU (HTTP ' . $response->status() . ')');
            }

            $json = $response->json();

            // Validar respuesta exitosa
            if (!isset($json['SUCCESS']) && !isset($json['success'])) {
                $errorMsg = $json['ERROR'] ?? $json['error'] ?? 'Respuesta inválida o credenciales incorrectas';
                throw new \Exception($errorMsg);
            }

            $this->dhruProducts = [];
            $successData = $json['SUCCESS'] ?? $json['success'] ?? [];

            // Procesar estructura de DHRU (puede variar)
            $this->parseDhruServices($successData);

            if (empty($this->dhruProducts)) {
                Notification::make()
                    ->warning()
                    ->title('Sin servicios')
                    ->body('No se encontraron servicios en DHRU Fusion')
                    ->send();
                return;
            }

            Notification::make()
                ->success()
                ->title('✅ Conexión exitosa')
                ->body('Se encontraron ' . count($this->dhruProducts) . ' servicios')
                ->send();

            // Abrir modal de selección
            $this->mountAction('selectDhruProducts');

        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('❌ Error de conexión')
                ->body('Error: ' . $e->getMessage())
                ->persistent()
                ->send();
        }
    }

    /**
     * Parsear estructura de servicios DHRU (puede variar según versión)
     */
    protected function parseDhruServices(array $data): void
    {
        // DHRU puede devolver estructura agrupada o plana
        if (isset($data[0]['LIST'])) {
            $data = $data[0]['LIST'];
        }

        foreach ($data as $item) {
            // Si es un grupo con servicios
            if (isset($item['GROUPNAME']) && isset($item['SERVICES'])) {
                $groupName = $item['GROUPNAME'];
                foreach ($item['SERVICES'] as $service) {
                    $this->addDhruService($service, $groupName);
                }
            }
            // Si es un servicio directo
            elseif (isset($item['SERVICEID'])) {
                $this->addDhruService($item, null);
            }
            // Estructura alternativa
            elseif (isset($item['ID'])) {
                $this->addDhruService([
                    'SERVICEID' => $item['ID'],
                    'SERVICENAME' => $item['NAME'] ?? $item['SERVICENAME'] ?? 'Servicio',
                    'CREDIT' => $item['CREDIT'] ?? $item['PRICE'] ?? 0,
                    'TIME' => $item['TIME'] ?? 'N/A',
                ], null);
            }
        }
    }

    protected function addDhruService(array $service, ?string $group): void
    {
        $serviceId = $service['SERVICEID'] ?? $service['ID'] ?? null;
        if (!$serviceId) return;

        $sku = 'DHRU-' . $serviceId;
        $existsInDb = Product::where('sku', $sku)->exists();

        $this->dhruProducts[] = [
            'id' => $serviceId,
            'name' => $service['SERVICENAME'] ?? $service['NAME'] ?? 'Servicio #' . $serviceId,
            'group' => $group,
            'price' => floatval($service['CREDIT'] ?? $service['PRICE'] ?? 0),
            'time' => $service['TIME'] ?? 'N/A',
            'sku' => $sku,
            'exists' => $existsInDb,
        ];
    }

    /**
     * Formulario para seleccionar servicios de DHRU
     */
    protected function getDhruProductsForm(): array
    {
        if (empty($this->dhruProducts)) {
            return [
                Forms\Components\Placeholder::make('empty')
                    ->content('No hay servicios para mostrar')
            ];
        }

        $options = [];
        $descriptions = [];

        foreach ($this->dhruProducts as $service) {
            $key = (string) $service['id'];
            $status = $service['exists'] ? '🔄 Actualizar' : '🆕 Nuevo';
            $group = $service['group'] ? "[{$service['group']}] " : '';

            $options[$key] = $group . $service['name'];
            $descriptions[$key] = sprintf(
                '%s | 💳 %.2f Créditos | ⏱️ %s',
                $status,
                $service['price'],
                $service['time']
            );
        }

        return [
            Forms\Components\Section::make('Resumen')
                ->schema([
                    Forms\Components\Placeholder::make('stats')
                        ->label('')
                        ->content(function () {
                            $total = count($this->dhruProducts);
                            $existing = count(array_filter($this->dhruProducts, fn($p) => $p['exists']));
                            $new = $total - $existing;

                            return new \Illuminate\Support\HtmlString(
                                "<div class='flex gap-4'>
                                    <span class='px-3 py-1 bg-purple-100 text-purple-800 rounded-full font-medium'>🖥️ Total: {$total}</span>
                                    <span class='px-3 py-1 bg-green-100 text-green-800 rounded-full font-medium'>🆕 Nuevos: {$new}</span>
                                    <span class='px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full font-medium'>🔄 Existentes: {$existing}</span>
                                </div>"
                            );
                        }),
                ]),

            Forms\Components\Section::make('Seleccionar Servicios')
                ->description('Marque los servicios que desea importar')
                ->schema([
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('selectAll')
                            ->label('Seleccionar Todos')
                            ->icon('heroicon-o-check-circle')
                            ->color('success')
                            ->action(function (Forms\Set $set) {
                                $allIds = array_map(fn($p) => (string) $p['id'], $this->dhruProducts);
                                $set('selected_services', $allIds);
                            }),
                        Forms\Components\Actions\Action::make('selectNew')
                            ->label('Solo Nuevos')
                            ->icon('heroicon-o-plus-circle')
                            ->color('info')
                            ->action(function (Forms\Set $set) {
                                $newIds = array_map(
                                    fn($p) => (string) $p['id'],
                                    array_filter($this->dhruProducts, fn($p) => !$p['exists'])
                                );
                                $set('selected_services', $newIds);
                            }),
                        Forms\Components\Actions\Action::make('deselectAll')
                            ->label('Deseleccionar Todos')
                            ->icon('heroicon-o-x-circle')
                            ->color('gray')
                            ->action(function (Forms\Set $set) {
                                $set('selected_services', []);
                            }),
                    ]),

                    Forms\Components\CheckboxList::make('selected_services')
                        ->label('')
                        ->options($options)
                        ->descriptions($descriptions)
                        ->columns(1)
                        ->searchable()
                        ->bulkToggleable()
                        ->default(array_map(fn($p) => (string) $p['id'], $this->dhruProducts)),
                ])
                ->collapsible(),
        ];
    }

    /**
     * Importar servicios seleccionados de DHRU
     */
    public function importSelectedDhruProducts(array $data): void
    {
        $selectedIds = $data['selected_services'] ?? [];

        if (empty($selectedIds)) {
            Notification::make()
                ->warning()
                ->title('Sin selección')
                ->body('No seleccionó ningún servicio para importar')
                ->send();
            return;
        }

        $count = 0;
        $updated = 0;

        foreach ($this->dhruProducts as $service) {
            if (!in_array((string) $service['id'], $selectedIds)) {
                continue;
            }

            $exists = $service['exists'];

            Product::updateOrCreate(
                ['sku' => $service['sku']],
                [
                    'name' => $service['name'],
                    'price' => $service['price'],
                    'base_price' => $service['price'], // El precio de créditos es el costo base
                    'type' => 'service', // Servicio de servidor
                    'is_active' => true,
                ]
            );

            if ($exists) {
                $updated++;
            } else {
                $count++;
            }
        }

        $this->dhruProducts = []; // Limpiar

        Notification::make()
            ->success()
            ->title('✅ Importación completada')
            ->body("Se importaron {$count} servicios nuevos y se actualizaron {$updated} existentes")
            ->send();
    }
}
