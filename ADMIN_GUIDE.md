# Guía de Administración del Sistema

## Panel de Administración del Sistema

Este proyecto incluye un panel de administración especial para realizar operaciones de mantenimiento sin necesidad de acceso SSH.

### Acceso al Panel

1. Inicie sesión en el panel administrativo de Filament (`/admin`)
2. Haga clic en su avatar/nombre de usuario en la esquina superior derecha
3. Seleccione "Administración del Sistema" del menú desplegable
4. Será redirigido a `/system` donde verá el panel de administración

### Funcionalidades Disponibles

#### 1. Limpiar Cache Completo

Esta función limpia todo el cache del sistema de Laravel, incluyendo:

- Cache de aplicación (`cache:clear`)
- Cache de configuración (`config:clear`)
- Cache de rutas (`route:clear`)
- Cache de vistas (`view:clear`)
- Cache compilado de PHP
- Cache de servicios
- Optimización general (`optimize:clear`)
- Cache de Filament

**Uso:**
1. En el panel `/system`, localice la sección "Limpiar Cache"
2. Haga clic en el botón "Limpiar Cache Completo"
3. Espere a que se complete la operación
4. Verá un mensaje de éxito con los detalles de lo que se limpió

**Cuándo usar:**
- Después de cambios en archivos de configuración
- Después de actualizar archivos de vistas
- Cuando el sistema se comporta de manera inesperada
- Después de desplegar nuevos cambios

#### 2. Ejecutar Migraciones de Base de Datos

Esta función permite ejecutar migraciones de base de datos pendientes sin acceso SSH.

**Configuración inicial:**

1. Abra el archivo `.env` en la raíz del proyecto
2. Agregue o modifique la variable `ADMIN_SECURITY_TOKEN`:
   ```
   ADMIN_SECURITY_TOKEN=tu-token-secreto-aqui
   ```
3. Reemplace `tu-token-secreto-aqui` con un token seguro y único
4. Guarde el archivo

**Uso:**

1. En el panel `/system`, localice la sección "Ejecutar Migraciones"
2. Ingrese el token de seguridad que configuró en el archivo `.env`
3. Haga clic en "Ejecutar Migraciones"
4. Confirme la operación en el diálogo de confirmación
5. Espere a que se completen las migraciones
6. Verá la salida de las migraciones en pantalla

**Cuándo usar:**
- Después de desplegar nuevos cambios que incluyen migraciones
- Cuando se agregan nuevas tablas o campos a la base de datos
- Durante actualizaciones del sistema

**⚠️ Importante:**
- Esta operación modifica la estructura de la base de datos
- Asegúrese de tener backups actualizados antes de ejecutar
- El token de seguridad protege contra ejecuciones accidentales

### Acceso Directo por URL

También puede acceder directamente a estas funcionalidades mediante URLs:

- **Panel de administración:** `https://tu-dominio.com/system`
- **Limpiar cache (API):** `POST https://tu-dominio.com/system/clear-cache`
- **Ejecutar migraciones (API):** `POST https://tu-dominio.com/system/run-migrations`

**Nota:** Todas las rutas requieren autenticación. Debe estar logueado en el panel de administración.

### Solución del Problema de Paquetes de Precios

Se corrigió un bug donde solo se mostraban 4 paquetes de precios al editar productos.

**Problema anterior:**
- El sistema usaba el ID del paquete en lugar de un índice secuencial
- Si se eliminaban paquetes y se creaban nuevos, los IDs podían ser > 10
- Estos paquetes no se mostraban porque el sistema los filtraba

**Solución implementada:**
- Ahora se usa un índice secuencial (1-10) independiente del ID del paquete
- Muestra hasta 10 paquetes activos en orden, sin importar sus IDs
- Los paquetes se ordenan por `sort_order` y luego se asignan a los campos disponibles

**Resultado:**
- Todos los paquetes activos (hasta 10) se muestran al editar productos
- El orden se respeta según la configuración en "Paquetes de Precios"

### Seguridad

- Todas las operaciones requieren autenticación
- Las migraciones requieren un token de seguridad adicional
- Se registran logs de todas las operaciones administrativas
- Las rutas están protegidas con middleware de autenticación

### Solución de Problemas

**Error: "Acceso no autorizado"**
- Asegúrese de estar logueado en el panel de administración
- Cierre sesión y vuelva a iniciar sesión

**Error: "Token de seguridad inválido"**
- Verifique que el token en `.env` sea correcto
- No debe tener espacios antes o después del token
- Es case-sensitive (distingue mayúsculas y minúsculas)

**Error al limpiar cache**
- Verifique que el directorio `storage/framework/cache` tenga permisos de escritura
- En servidores compartidos, contacte a soporte si persiste

**Error al ejecutar migraciones**
- Verifique la conexión a la base de datos
- Asegúrese de que el usuario de BD tenga permisos para modificar la estructura
- Revise los logs en `storage/logs/laravel.log`

### Configuración de Hosting (cPanel)

Si no tiene acceso SSH pero necesita configurar el token de seguridad:

1. Acceda al administrador de archivos de cPanel
2. Navegue a la raíz del proyecto
3. Edite el archivo `.env`
4. Agregue la línea: `ADMIN_SECURITY_TOKEN=tu-token-aqui`
5. Guarde el archivo
6. Limpie el cache desde el panel (`/system`)

### Notas Adicionales

- **Backups:** Siempre mantenga backups actualizados antes de ejecutar migraciones
- **Logs:** Todas las operaciones se registran en `storage/logs/laravel.log`
- **Rendimiento:** Limpiar el cache puede causar una ligera lentitud temporal mientras se regenera
- **Mantenimiento:** Use estas funciones solo cuando sea necesario, no de forma rutinaria

---

**Desarrollado para:** NicaGSM Admin System
**Última actualización:** Enero 2026
