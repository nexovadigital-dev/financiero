<?php

namespace App\Console\Commands;

use App\Models\Product;
use Automattic\WooCommerce\Client;
use Illuminate\Console\Command;

class SyncWooCommerceProducts extends Command
{
    protected $signature = 'woo:sync-products';

    protected $description = 'Sincronizar productos y variantes desde WooCommerce (SOLO LECTURA)';

    public function handle()
    {
        $this->info('🔄 Iniciando sincronización de productos desde WooCommerce...');
        $this->warn('⚠️  MODO SOLO LECTURA: No se modificará ni eliminará nada en WooCommerce');

        // Soportar tanto WOOCOMMERCE_* como WOO_* variables
        $wooUrl = env('WOOCOMMERCE_URL') ?? env('WOO_URL');
        $wooKey = env('WOOCOMMERCE_CONSUMER_KEY') ?? env('WOO_KEY');
        $wooSecret = env('WOOCOMMERCE_CONSUMER_SECRET') ?? env('WOO_SECRET');

        // Verificar configuración
        if (!$wooUrl || !$wooKey || !$wooSecret) {
            $this->error('❌ Error: Faltan credenciales de WooCommerce en el archivo .env');
            $this->line('');
            $this->line('Agrega las siguientes variables a tu archivo .env:');
            $this->line('WOO_URL=https://tu-tienda.com');
            $this->line('WOO_KEY=ck_xxxxxxxxxxxxx');
            $this->line('WOO_SECRET=cs_xxxxxxxxxxxxx');
            return Command::FAILURE;
        }

        try {
            // Inicializar cliente WooCommerce
            $woocommerce = new Client(
                $wooUrl,
                $wooKey,
                $wooSecret,
                [
                    'version' => 'wc/v3',
                    'timeout' => 60,
                ]
            );

            $page = 1;
            $totalSynced = 0;
            $totalVariants = 0;

            do {
                // Obtener productos de WooCommerce (SOLO LECTURA)
                $this->info("📦 Obteniendo página {$page} de productos...");

                $response = $woocommerce->get('products', [
                    'per_page' => 100,
                    'page' => $page,
                    'status' => 'publish', // Solo productos publicados
                ]);

                if (empty($response)) {
                    break;
                }

                foreach ($response as $wooProduct) {
                    // Sincronizar producto simple o padre de variación
                    $this->syncProduct($wooProduct);
                    $totalSynced++;

                    // Si es un producto variable, sincronizar sus variantes
                    if ($wooProduct->type === 'variable') {
                        $variants = $woocommerce->get("products/{$wooProduct->id}/variations", ['per_page' => 100]);

                        foreach ($variants as $variant) {
                            $this->syncVariant($wooProduct, $variant);
                            $totalVariants++;
                        }
                    }
                }

                $page++;
            } while (count($response) === 100); // Continuar si hay más páginas

            $this->newLine();
            $this->info("✅ Sincronización completada:");
            $this->line("   • {$totalSynced} productos sincronizados");
            $this->line("   • {$totalVariants} variantes sincronizadas");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Error durante la sincronización: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function syncProduct($wooProduct)
    {
        // Determinar el tipo de producto
        $type = match($wooProduct->type) {
            'simple', 'variable' => 'digital_product',
            default => 'digital_product'
        };

        // Actualizar o crear producto (SOLO actualiza, nunca elimina)
        Product::updateOrCreate(
            ['woocommerce_product_id' => $wooProduct->id],
            [
                'name' => $wooProduct->name,
                'price' => floatval($wooProduct->price),
                'sku' => $wooProduct->sku,
                'type' => $type,
                'is_active' => $wooProduct->status === 'publish',
            ]
        );

        $this->line("   ✓ {$wooProduct->name} (SKU: {$wooProduct->sku})");
    }

    private function syncVariant($parentProduct, $variant)
    {
        // Construir nombre con atributos de variante
        $attributes = collect($variant->attributes ?? [])
            ->map(fn($attr) => $attr->option)
            ->join(', ');

        $variantName = "{$parentProduct->name} [{$attributes}]";

        // Actualizar o crear variante (SOLO actualiza, nunca elimina)
        Product::updateOrCreate(
            ['woocommerce_product_id' => $variant->id],
            [
                'name' => $variantName,
                'price' => floatval($variant->price),
                'sku' => $variant->sku,
                'type' => 'digital_product',
                'is_active' => $variant->status === 'publish',
            ]
        );

        $this->line("     ↳ {$variantName} (SKU: {$variant->sku})");
    }
}
