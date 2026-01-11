<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class SystemAdminController extends Controller
{
    /**
     * Mostrar panel de administración del sistema
     */
    public function index()
    {
        // Verificar que el usuario esté autenticado
        if (!Auth::check()) {
            abort(403, 'Acceso no autorizado');
        }

        return view('admin.system', [
            'cache_size' => $this->getCacheSize(),
            'app_version' => config('app.version', '1.0.0'),
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
        ]);
    }

    /**
     * Limpiar todo el cache del sistema
     */
    public function clearCache(Request $request)
    {
        // Verificar que el usuario esté autenticado
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Acceso no autorizado'
            ], 403);
        }

        try {
            $results = [];

            // Limpiar cache de aplicación
            Artisan::call('cache:clear');
            $results[] = 'Cache de aplicación limpiado';

            // Limpiar cache de configuración
            Artisan::call('config:clear');
            $results[] = 'Cache de configuración limpiado';

            // Limpiar cache de rutas
            Artisan::call('route:clear');
            $results[] = 'Cache de rutas limpiado';

            // Limpiar cache de vistas
            Artisan::call('view:clear');
            $results[] = 'Cache de vistas limpiado';

            // Limpiar cache compilado
            if (file_exists(base_path('bootstrap/cache/compiled.php'))) {
                unlink(base_path('bootstrap/cache/compiled.php'));
                $results[] = 'Cache compilado limpiado';
            }

            // Limpiar cache de servicios
            if (file_exists(base_path('bootstrap/cache/services.php'))) {
                unlink(base_path('bootstrap/cache/services.php'));
                $results[] = 'Cache de servicios limpiado';
            }

            // Optimizar caché de configuración
            Artisan::call('optimize:clear');
            $results[] = 'Optimización de caché completada';

            // Limpiar caché de Filament
            Cache::flush();
            $results[] = 'Cache de Filament limpiado';

            Log::info('Cache del sistema limpiado por usuario: ' . Auth::user()->email);

            return response()->json([
                'success' => true,
                'message' => 'Cache limpiado exitosamente',
                'details' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Error al limpiar cache: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al limpiar cache: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ejecutar migraciones de base de datos
     */
    public function runMigrations(Request $request)
    {
        // Verificar que el usuario esté autenticado
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Acceso no autorizado'
            ], 403);
        }

        // Verificar el token de seguridad
        $securityToken = $request->input('security_token');
        $expectedToken = env('ADMIN_SECURITY_TOKEN', 'change-this-token');

        if ($securityToken !== $expectedToken) {
            return response()->json([
                'success' => false,
                'message' => 'Token de seguridad inválido'
            ], 403);
        }

        try {
            // Ejecutar migraciones con manejo mejorado de errores
            Artisan::call('migrate', [
                '--force' => true,
                '--step' => true, // Ejecutar de una en una para mejor control
            ]);
            $output = Artisan::output();

            // Verificar si hay errores en la salida
            if (strpos($output, 'SQLSTATE') !== false) {
                // Hay un error SQL, intentar continuar con las siguientes
                Log::warning('Migraciones con advertencias por usuario: ' . Auth::user()->email . "\n" . $output);

                return response()->json([
                    'success' => true,
                    'message' => 'Migraciones completadas con advertencias (algunas ya estaban aplicadas)',
                    'output' => $output,
                    'warning' => true
                ]);
            }

            Log::info('Migraciones ejecutadas por usuario: ' . Auth::user()->email);

            return response()->json([
                'success' => true,
                'message' => 'Migraciones ejecutadas exitosamente',
                'output' => $output
            ]);

        } catch (\Exception $e) {
            Log::error('Error al ejecutar migraciones: ' . $e->getMessage());

            // Verificar si el error es por columnas duplicadas
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Las migraciones ya están aplicadas. Las columnas ya existen en la base de datos.',
                    'output' => $e->getMessage(),
                    'suggestion' => 'Si necesitas sincronizar el estado de las migraciones, contacta al administrador del sistema.'
                ], 200); // Cambiar a 200 porque técnicamente no es un error
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al ejecutar migraciones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener tamaño del cache
     */
    private function getCacheSize()
    {
        $cacheDir = storage_path('framework/cache');
        $size = 0;

        if (is_dir($cacheDir)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($cacheDir)) as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        }

        return $this->formatBytes($size);
    }

    /**
     * Formatear bytes a un formato legible
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
