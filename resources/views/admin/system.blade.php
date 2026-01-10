<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Administración del Sistema</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">
                    <i class="fas fa-tools text-blue-600"></i> Administración del Sistema
                </h1>
                <p class="text-gray-600">Panel de control para operaciones administrativas del sistema</p>
                <div class="mt-4 flex gap-4">
                    <a href="/admin" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
                        <i class="fas fa-arrow-left mr-2"></i> Volver al Panel
                    </a>
                </div>
            </div>

            <!-- Información del Sistema -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-info-circle text-blue-600"></i> Información del Sistema
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="border-l-4 border-blue-500 pl-4">
                        <p class="text-sm text-gray-600">Laravel Version</p>
                        <p class="text-lg font-semibold text-gray-800">{{ $laravel_version }}</p>
                    </div>
                    <div class="border-l-4 border-green-500 pl-4">
                        <p class="text-sm text-gray-600">PHP Version</p>
                        <p class="text-lg font-semibold text-gray-800">{{ $php_version }}</p>
                    </div>
                    <div class="border-l-4 border-purple-500 pl-4">
                        <p class="text-sm text-gray-600">Tamaño del Cache</p>
                        <p class="text-lg font-semibold text-gray-800">{{ $cache_size }}</p>
                    </div>
                    <div class="border-l-4 border-orange-500 pl-4">
                        <p class="text-sm text-gray-600">Versión de la App</p>
                        <p class="text-lg font-semibold text-gray-800">{{ $app_version }}</p>
                    </div>
                </div>
            </div>

            <!-- Limpiar Cache -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-broom text-yellow-600"></i> Limpiar Cache
                </h2>
                <p class="text-gray-600 mb-4">
                    Limpia todo el cache del sistema incluyendo: cache de aplicación, configuración, rutas, vistas y Filament.
                </p>
                <button id="clearCacheBtn" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-3 px-6 rounded-lg transition flex items-center">
                    <i class="fas fa-broom mr-2"></i> Limpiar Cache Completo
                </button>
                <div id="cacheResult" class="mt-4"></div>
            </div>

            <!-- Ejecutar Migraciones -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-database text-green-600"></i> Ejecutar Migraciones
                </h2>
                <p class="text-gray-600 mb-4">
                    Ejecuta las migraciones de base de datos pendientes. <strong>Requiere token de seguridad.</strong>
                </p>
                <div class="mb-4">
                    <label for="securityToken" class="block text-sm font-medium text-gray-700 mb-2">
                        Token de Seguridad
                    </label>
                    <input type="password" id="securityToken" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Ingrese el token de seguridad">
                    <p class="text-xs text-gray-500 mt-1">
                        El token se configura en el archivo .env (ADMIN_SECURITY_TOKEN)
                    </p>
                </div>
                <button id="runMigrationsBtn" class="bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-6 rounded-lg transition flex items-center">
                    <i class="fas fa-play mr-2"></i> Ejecutar Migraciones
                </button>
                <div id="migrationsResult" class="mt-4"></div>
            </div>

            <!-- Advertencia -->
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle text-red-600 mt-1 mr-3"></i>
                    <div>
                        <h3 class="text-red-800 font-bold mb-1">Advertencia</h3>
                        <p class="text-red-700 text-sm">
                            Estas operaciones pueden afectar el funcionamiento del sistema. Úsalas solo cuando sea necesario y asegúrate de tener backups actualizados.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Configurar CSRF token para todas las peticiones AJAX
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // Limpiar Cache
        document.getElementById('clearCacheBtn').addEventListener('click', async function() {
            const btn = this;
            const resultDiv = document.getElementById('cacheResult');

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Limpiando cache...';
            resultDiv.innerHTML = '';

            try {
                const response = await fetch('/system/clear-cache', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    }
                });

                const data = await response.json();

                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded">
                            <div class="flex items-start">
                                <i class="fas fa-check-circle text-green-600 mt-1 mr-3"></i>
                                <div>
                                    <h3 class="text-green-800 font-bold mb-1">${data.message}</h3>
                                    <ul class="text-sm text-green-700 list-disc list-inside">
                                        ${data.details.map(detail => `<li>${detail}</li>`).join('')}
                                    </ul>
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded">
                        <div class="flex items-start">
                            <i class="fas fa-times-circle text-red-600 mt-1 mr-3"></i>
                            <div>
                                <h3 class="text-red-800 font-bold mb-1">Error</h3>
                                <p class="text-sm text-red-700">${error.message}</p>
                            </div>
                        </div>
                    </div>
                `;
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-broom mr-2"></i> Limpiar Cache Completo';
            }
        });

        // Ejecutar Migraciones
        document.getElementById('runMigrationsBtn').addEventListener('click', async function() {
            const btn = this;
            const resultDiv = document.getElementById('migrationsResult');
            const securityToken = document.getElementById('securityToken').value;

            if (!securityToken) {
                resultDiv.innerHTML = `
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded">
                        <div class="flex items-start">
                            <i class="fas fa-times-circle text-red-600 mt-1 mr-3"></i>
                            <div>
                                <h3 class="text-red-800 font-bold mb-1">Error</h3>
                                <p class="text-sm text-red-700">Debe ingresar el token de seguridad</p>
                            </div>
                        </div>
                    </div>
                `;
                return;
            }

            if (!confirm('¿Está seguro que desea ejecutar las migraciones? Esta operación modificará la base de datos.')) {
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Ejecutando migraciones...';
            resultDiv.innerHTML = '';

            try {
                const response = await fetch('/system/run-migrations', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        security_token: securityToken
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // Mostrar con advertencia si hay warnings
                    const colorClass = data.warning ? 'yellow' : 'green';
                    const iconClass = data.warning ? 'exclamation-triangle' : 'check-circle';

                    resultDiv.innerHTML = `
                        <div class="bg-${colorClass}-50 border-l-4 border-${colorClass}-500 p-4 rounded">
                            <div class="flex items-start">
                                <i class="fas fa-${iconClass} text-${colorClass}-600 mt-1 mr-3"></i>
                                <div class="w-full">
                                    <h3 class="text-${colorClass}-800 font-bold mb-1">${data.message}</h3>
                                    <pre class="text-sm text-${colorClass}-700 mt-2 bg-${colorClass}-100 p-3 rounded overflow-x-auto">${data.output}</pre>
                                </div>
                            </div>
                        </div>
                    `;
                    document.getElementById('securityToken').value = '';
                } else {
                    // Mostrar mensaje de error con sugerencia si existe
                    let errorHtml = `
                        <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded">
                            <div class="flex items-start">
                                <i class="fas fa-info-circle text-yellow-600 mt-1 mr-3"></i>
                                <div class="w-full">
                                    <h3 class="text-yellow-800 font-bold mb-1">${data.message}</h3>
                    `;

                    if (data.output) {
                        errorHtml += `<pre class="text-sm text-yellow-700 mt-2 bg-yellow-100 p-3 rounded overflow-x-auto max-h-40">${data.output}</pre>`;
                    }

                    if (data.suggestion) {
                        errorHtml += `<p class="text-sm text-yellow-700 mt-2"><strong>Sugerencia:</strong> ${data.suggestion}</p>`;
                    }

                    errorHtml += `
                                </div>
                            </div>
                        </div>
                    `;

                    resultDiv.innerHTML = errorHtml;
                    document.getElementById('securityToken').value = '';
                }
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded">
                        <div class="flex items-start">
                            <i class="fas fa-times-circle text-red-600 mt-1 mr-3"></i>
                            <div>
                                <h3 class="text-red-800 font-bold mb-1">Error</h3>
                                <p class="text-sm text-red-700">${error.message}</p>
                            </div>
                        </div>
                    </div>
                `;
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-play mr-2"></i> Ejecutar Migraciones';
            }
        });
    </script>
</body>
</html>
