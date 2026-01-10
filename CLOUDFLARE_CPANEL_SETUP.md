# Guía de Configuración: cPanel + Cloudflare

## Problema Solucionado

Si experimentas que después de 10-20 segundos de inactividad el sistema se queda cargando y necesitas hacer F5, es un problema de **sesiones con Cloudflare**. Esta guía soluciona ese problema.

## Cambios Implementados en el Código

### 1. Configuración de Sesiones Extendida (.env)

Se agregaron/modificaron las siguientes configuraciones en el archivo `.env`:

```env
SESSION_DRIVER=database
SESSION_LIFETIME=480              # 8 horas (antes 120 minutos)
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null               # Importante: dejar en null para subdominios
SESSION_SECURE_COOKIE=false       # Cambiar a true si usas HTTPS
SESSION_HTTP_ONLY=true            # Protección XSS
SESSION_SAME_SITE=lax            # Protección CSRF pero permite navegación
```

### 2. Configuración de Livewire

Se creó el archivo `config/livewire.php` con configuraciones optimizadas para cPanel + Cloudflare.

### 3. Middleware para Cloudflare

Se creó `app/Http/Middleware/TrustCloudflare.php` que configura Laravel para confiar en las IPs de Cloudflare correctamente.

## Pasos para Aplicar en Producción (cPanel)

### Paso 1: Actualizar el archivo .env

1. Accede al **Administrador de Archivos** en cPanel
2. Navega a la raíz de tu proyecto
3. Edita el archivo `.env`
4. **Importante:** Actualiza estas líneas:

```env
# Cambia el SESSION_LIFETIME
SESSION_LIFETIME=480

# Agrega estas nuevas líneas si no existen
SESSION_SECURE_COOKIE=true        # TRUE porque usas HTTPS con Cloudflare
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

# Asegúrate de tener tu URL correcta
APP_URL=https://tu-dominio.com    # Reemplazar con tu dominio real
```

5. Guarda el archivo

### Paso 2: Limpiar Cache

1. Inicia sesión en tu panel de administración (`/admin`)
2. Ve al menú de usuario (esquina superior derecha)
3. Click en **"Administración del Sistema"**
4. Click en **"Limpiar Cache Completo"**

### Paso 3: Configurar Cloudflare

#### 3.1 Reglas de Página (Page Rules)

Ve a tu panel de Cloudflare > **Rules** > **Page Rules** y crea estas reglas:

**Regla 1: No cachear el panel de administración**
- URL: `*tu-dominio.com/admin*`
- Configuración:
  - Cache Level: **Bypass**
  - Disable Security
  - Disable Performance

**Regla 2: No cachear endpoints de Livewire**
- URL: `*tu-dominio.com/livewire*`
- Configuración:
  - Cache Level: **Bypass**

#### 3.2 Configuración SSL/TLS

Ve a **SSL/TLS** > **Overview**
- Modo: **Full (strict)** (recomendado) o **Full**

#### 3.3 Configuración de Speed

Ve a **Speed** > **Optimization**

**Desactivar:**
- ❌ Rocket Loader (interfiere con Livewire)
- ❌ Auto Minify JavaScript (puede romper Filament)

**Activar:**
- ✅ Auto Minify HTML
- ✅ Auto Minify CSS
- ✅ Brotli

#### 3.4 Configuración de Caching

Ve a **Caching** > **Configuration**

**Configuración recomendada:**
- Browser Cache TTL: **4 horas**
- Respect Existing Headers: **Activado**

#### 3.5 Reglas de Configuración (Configuration Rules) - IMPORTANTE

Ve a **Rules** > **Configuration Rules** y crea esta regla:

**Regla: Desactivar cache para rutas dinámicas**
- When incoming requests match: `URI Path` **starts with** `/admin`
- Then the settings are:
  - Cache Level: **Bypass**
  - Browser Cache TTL: **Respect Existing Headers**

### Paso 4: Configuración de cPanel (Opcional pero Recomendado)

#### 4.1 PHP Configuration

1. En cPanel, ve a **Select PHP Version** o **MultiPHP Manager**
2. Asegúrate de usar **PHP 8.2** o superior
3. Ve a **PHP Options** y ajusta:
   - `max_execution_time`: **300**
   - `max_input_time`: **300**
   - `memory_limit`: **256M** (o más si es posible)
   - `post_max_size`: **64M**
   - `upload_max_filesize`: **64M**

#### 4.2 Limpiar Sessions Antiguas (Solo si es necesario)

Si sigues teniendo problemas, puedes limpiar las sesiones antiguas:

```sql
-- Conecta a tu base de datos desde phpMyAdmin
DELETE FROM sessions WHERE last_activity < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 1 DAY));
```

## Configuración Adicional en .env (Producción)

Asegúrate de tener estas configuraciones correctas en producción:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.com

# Base de datos
DB_CONNECTION=mysql               # No sqlite en producción
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=tu_base_datos
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_contraseña

# Sesiones
SESSION_DRIVER=database           # Usar database en cPanel
SESSION_LIFETIME=480
SESSION_SECURE_COOKIE=true        # TRUE porque usas HTTPS
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

# Cache
CACHE_STORE=database              # O 'file' si tienes problemas

# Queue
QUEUE_CONNECTION=database         # Funciona sin cron job adicional
```

## Verificar que Todo Funciona

1. **Limpia el cache de Cloudflare:**
   - Panel Cloudflare > Caching > **Purge Everything**

2. **Limpia el cache de tu aplicación:**
   - `/system` > "Limpiar Cache Completo"

3. **Prueba la sesión:**
   - Inicia sesión en `/admin`
   - Deja la pestaña abierta sin tocar nada por 30 segundos
   - Haz click en cualquier enlace del menú
   - **Debería funcionar sin necesidad de F5**

4. **Verifica en el navegador:**
   - Abre las **Herramientas de Desarrollador** (F12)
   - Ve a la pestaña **Network**
   - Filtra por `livewire`
   - Deberías ver requests exitosos (200) cuando interactúas con el sistema

## Problemas Comunes y Soluciones

### Problema: Aún se queda cargando después de seguir todos los pasos

**Solución 1:** Verifica que Cloudflare no esté cacheando las rutas de administración
- En Cloudflare, ve a **Caching** > **Purge Everything**
- Revisa las Page Rules y asegúrate que `/admin*` esté en bypass

**Solución 2:** Verifica el .env
```bash
# En cPanel, usa el File Manager para verificar que .env tenga:
SESSION_LIFETIME=480
SESSION_SECURE_COOKIE=true
APP_URL=https://tu-dominio-correcto.com
```

**Solución 3:** Revisa los logs
- cPanel > **Errors** (en la sección Metrics)
- Busca errores relacionados con sesiones o CSRF

### Problema: Error CSRF token mismatch

**Solución:**
1. Limpia el cache de Cloudflare
2. Limpia el cache de la aplicación
3. Verifica que `SESSION_DOMAIN=null` en el .env
4. Asegúrate que Cloudflare no tenga activado "Email Obfuscation" o "Rocket Loader"

### Problema: Las imágenes o CSS no cargan

**Solución:**
- En el .env, verifica que `APP_URL` sea correcto y use HTTPS
- Limpia el cache

## Notas Importantes

- **Cloudflare Rocket Loader:** SIEMPRE desactivado para aplicaciones Livewire/Filament
- **SESSION_LIFETIME:** 480 minutos = 8 horas. Ajusta según tus necesidades
- **Browser Cache TTL:** 4 horas es un buen balance entre rendimiento y actualización
- **Database Sessions:** Más confiables en hosting compartido que file sessions

## Soporte Adicional

Si después de seguir todos estos pasos aún tienes problemas:

1. Revisa el archivo `storage/logs/laravel.log` para ver errores específicos
2. Contacta al soporte de tu hosting para:
   - Verificar que no haya firewall bloqueando requests AJAX
   - Verificar que las sesiones de PHP estén configuradas correctamente
   - Verificar que no haya mod_security bloqueando requests

---

**Última actualización:** Enero 2026
**Desarrollado para:** NicaGSM Admin System
