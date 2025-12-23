<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Automattic\WooCommerce\Client;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Http;

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
                Actions\Action::make('syncWoo')
                    ->label('🏪 Tienda (WooCommerce)')
                    ->icon('heroicon-o-shopping-bag')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Sincronizar WooCommerce (SOLO LECTURA)')
                    ->modalDescription('⚠️ MODO SOLO LECTURA: Se descargarán productos y variantes desde WooCommerce. Esta operación NO modificará ni eliminará NADA en tu tienda WooCommerce. Solo actualiza la lista local.')
                    ->action(fn () => $this->syncWooCommerce()),

                // 2. SINCRONIZAR DHRU FUSION
                Actions\Action::make('syncDhru')
                    ->label('🖥️ Servidor (DHRU)')
                    ->icon('heroicon-o-server')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Conectar con DHRU Fusion')
                    ->modalDescription('Se importarán los servicios IMEI/Unlock y sus precios en Créditos.')
                    ->action(fn () => $this->syncDhruFusion()),
            ])
            ->label('Sincronizar')
            ->icon('heroicon-m-arrow-path')
            ->color('gray')
            ->button(),
        ];
    }

    // --- LÓGICA WOOCOMMERCE ---
    public function syncWooCommerce()
    {
        // Soportar tanto WOOCOMMERCE_* como WOO_* variables
        $wooUrl = env('WOOCOMMERCE_URL') ?? env('WOO_URL');
        $wooKey = env('WOOCOMMERCE_CONSUMER_KEY') ?? env('WOO_KEY');
        $wooSecret = env('WOOCOMMERCE_CONSUMER_SECRET') ?? env('WOO_SECRET');

        if (!$wooUrl || !$wooKey || !$wooSecret) {
            $this->notifyError('Faltan credenciales WooCommerce en .env (WOO_URL, WOO_KEY, WOO_SECRET)');
            return;
        }

        try {
            $woocommerce = new Client(
                $wooUrl,
                $wooKey,
                $wooSecret,
                ['version' => 'wc/v3', 'verify_ssl' => true, 'timeout' => 60]
            );

            // Traemos 100 productos (puedes aumentar si necesitas más paginación)
            $wooProducts = $woocommerce->get('products', ['per_page' => 100]);
            
            $processedWooIds = []; // Lista para rastrear qué IDs siguen vivos

            $count = 0;
            foreach ($wooProducts as $item) {
                // A. PRODUCTOS VARIABLES
                if ($item->type === 'variable') {
                    $variations = $woocommerce->get("products/{$item->id}/variations", ['per_page' => 50]);
                    foreach ($variations as $variation) {
                        $attributes = array_map(fn($attr) => $attr->option, $variation->attributes);
                        $variationName = $item->name . ' - ' . implode(', ', $attributes);
                        $isActive = ($item->status === 'publish' && ($variation->stock_status ?? 'instock') === 'instock');

                        Product::withTrashed()->updateOrCreate(
                            ['woocommerce_product_id' => $variation->id],
                            [
                                'name' => $variationName,
                                'price' => floatval($variation->price ?: 0),
                                'sku' => $variation->sku ?: '',
                                'type' => 'store', // Artículos de tienda
                                'is_active' => $isActive,
                                'deleted_at' => null, // Restaurar si estaba borrado
                            ]
                        );
                        $processedWooIds[] = $variation->id;
                        $count++;
                    }
                }
                // B. PRODUCTOS SIMPLES
                else {
                    $isActive = ($item->status === 'publish' && ($item->stock_status ?? 'instock') === 'instock');
                    Product::withTrashed()->updateOrCreate(
                        ['woocommerce_product_id' => $item->id],
                        [
                            'name' => $item->name,
                            'price' => floatval($item->price ?: 0),
                            'sku' => $item->sku ?: '',
                            'type' => 'store', // Artículos de tienda
                            'is_active' => $isActive,
                            'deleted_at' => null,
                        ]
                    );
                    $processedWooIds[] = $item->id;
                    $count++;
                }
            }

            // C. LIMPIEZA (Soft Delete)
            // Borramos los productos locales que tienen ID de Woo pero NO llegaron en esta sincronización
            $deleted = 0;
            if (count($processedWooIds) > 0) {
                $deleted = Product::whereNotNull('woocommerce_product_id')
                    ->whereNotIn('woocommerce_product_id', $processedWooIds)
                    ->delete();
            }

            $this->notifySuccess("✅ Sincronización completada: {$count} productos importados/actualizados" . ($deleted > 0 ? ", {$deleted} eliminados" : ""));

        } catch (\Exception $e) {
            $this->notifyError('Error de sincronización: ' . $e->getMessage());
        }
    }

    // --- LÓGICA DHRU FUSION ---
    public function syncDhruFusion()
    {
        $url = env('DHRU_URL');
        $user = env('DHRU_USER');
        $key = env('DHRU_KEY');

        if (!$url || !$user || !$key) {
            $this->notifyError('Faltan credenciales DHRU en .env');
            return;
        }

        try {
            // DHRU usa POST estándar
            $response = Http::asForm()->post($url . '/api/index.php', [
                'username' => $user,
                'apiaccesskey' => $key,
                'action' => 'imeiservicelist', // Acción estándar para listar servicios
                'requestformat' => 'JSON',
            ]);

            if ($response->failed()) {
                throw new \Exception('Error conectando al servidor DHRU');
            }

            $json = $response->json();

            // Validar respuesta exitosa
            if (!isset($json['SUCCESS'])) {
                // A veces Dhru devuelve error en otro formato
                throw new \Exception('Respuesta inválida o credenciales incorrectas.');
            }

            $services = $json['SUCCESS'][0]['LIST'] ?? $json['SUCCESS']; // Dhru varía estructura a veces

            $count = 0;
            // Procesar lista
            // Estructura usual: ['SERVICEID', 'SERVICENAME', 'CREDIT', 'TIME']
            foreach ($services as $group) {
                 // Dhru agrupa por carpetas, a veces la lista plana está dentro, 
                 // o a veces devuelve lista plana directa. Asumimos lista plana o iteramos grupos.
                 // Si la estructura es simple lista de objetos:
                 
                 // Ajuste para estructura típica de Dhru v6:
                 // Puede venir agrupado. Si $group tiene 'GROUPNAME', hay que iterar sus servicios.
                 if (isset($group['GROUPNAME']) && isset($group['SERVICES'])) {
                     foreach ($group['SERVICES'] as $service) {
                         $this->createDhruProduct($service);
                         $count++;
                     }
                 } elseif (isset($group['SERVICEID'])) {
                     // Lista plana
                     $this->createDhruProduct($group);
                     $count++;
                 }
            }

            $this->notifySuccess("Se importaron {$count} servicios de DHRU Fusion.");

        } catch (\Exception $e) {
            $this->notifyError('Error DHRU: ' . $e->getMessage());
        }
    }

    private function createDhruProduct($serviceData)
    {
        // Mapeo de campos DHRU
        $dhruId = $serviceData['SERVICEID'] ?? null;
        $name = $serviceData['SERVICENAME'] ?? 'Servicio Desconocido';
        $price = $serviceData['CREDIT'] ?? 0;

        if ($dhruId) {
            Product::updateOrCreate(
                ['sku' => 'DHRU-' . $dhruId], // Usamos SKU para identificar únicos de Dhru
                [
                    'name' => $name,
                    'price' => $price,
                    'type' => 'service', // Lo marcamos como Servicio Servidor
                    'is_active' => true,
                ]
            );
        }
    }

    // Helpers de Notificación
    private function notifySuccess($msg)
    {
        Notification::make()->title('Éxito')->body($msg)->success()->send();
    }

    private function notifyError($msg)
    {
        Notification::make()->title('Error')->body($msg)->danger()->send();
    }
}