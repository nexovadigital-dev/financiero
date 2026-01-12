# Sistema Financiero - NicaGSM

Sistema de gestión financiera y control de gastos desarrollado por **Nexova Digital Solutions** para uso exclusivo de **NicaGSM**.

## Descripción

Aplicación web personalizada para la gestión integral de operaciones financieras, inventario y control de gastos operativos de NicaGSM. El sistema permite un control detallado de ventas, compras, proveedores, clientes y reportes financieros con soporte multi-moneda.

## Características Principales

### Gestión de Ventas
- Registro de ventas con soporte multi-moneda (USDT, USD, NIO, USD-Nicaragua)
- Múltiples ítems por venta con costos y precios individualizados
- Cálculo automático de ganancias y costos
- Sistema de créditos e inversiones
- Conversión automática de monedas en reportes
- Ventas a crédito con seguimiento de pagos

### Gestión de Productos
- Catálogo de productos con información detallada
- Hasta 10 paquetes de precios por producto
- Precios base editables en múltiples monedas:
  - USDT (Tether)
  - USD (Dólar Estadounidense)
  - USD-Nic (Dólar para bancos de Nicaragua)
  - NIO (Córdoba Nicaragüense)
- Precios específicos por proveedor
- Control de costos por moneda

### Gestión de Proveedores
- Registro completo de proveedores
- Seguimiento de balance por proveedor
- Historial de pagos y compras
- Múltiples métodos de pago por proveedor
- Conversión automática de monedas en balances

### Sistema de Gastos
El sistema maneja dos tipos de gastos:

#### 1. Pagos a Proveedores
- Pagos relacionados con proveedores específicos
- Actualización automática de balance de proveedor
- Filtros por proveedor y método de pago

#### 2. Gastos Operativos
- Gastos generales de la empresa (hosting, programador, servicios externos)
- No requieren asociación con proveedor
- Registro con nombre personalizado y descripción
- Detección automática de moneda según método de pago
- Filtros por moneda y método de pago
- Seguimiento independiente en reportes financieros

### Gestión de Clientes
- Base de datos de clientes
- Historial de compras por cliente
- Seguimiento de créditos pendientes

### Métodos de Pago
- Configuración de múltiples métodos de pago
- Asociación con monedas específicas
- Activación/desactivación de métodos
- Uso en ventas, compras y gastos operativos

### Reportes Financieros
Dashboard con indicadores clave:
- **Total Ingresos**: Suma de todas las ventas
- **Egresos Proveedores**: Total de pagos a proveedores
- **Gastos Operativos**: Total de gastos operativos
- **Ganancia Neta**: Ingresos menos egresos totales

Características de reportes:
- Todos los indicadores en USD para consistencia
- Conversión automática de monedas
- Filtros por fecha, moneda y método de pago
- Exportación de datos
- Visualización detallada de costos

### Soporte Multi-Moneda
- **USDT (Tether)**: Criptomoneda estable
- **USD**: Dólar estadounidense estándar
- **USD-Nic**: Dólar para operaciones con bancos nicaragüenses
- **NIO**: Córdoba nicaragüense
- Conversión automática en reportes y balances
- Tasas de cambio configurables

### Panel de Administración
- Gestión de configuraciones globales
- Tasas de cambio de monedas
- Configuración de precios base
- Gestión de paquetes de precios
- Administración de usuarios

## Stack Tecnológico

- **Framework**: Laravel 11.x
- **Panel Admin**: Filament 3.x
- **Base de Datos**: MySQL/MariaDB
- **PHP**: 8.2+
- **Frontend**: Blade + Alpine.js + Tailwind CSS

## Requisitos del Sistema

- PHP >= 8.2
- Composer
- MySQL >= 8.0 o MariaDB >= 10.3
- Node.js >= 18.x (para assets)
- Extensiones PHP requeridas:
  - BCMath
  - Ctype
  - cURL
  - DOM
  - Fileinfo
  - JSON
  - Mbstring
  - OpenSSL
  - PCRE
  - PDO
  - Tokenizer
  - XML

## Instalación

1. Clonar el repositorio
```bash
git clone https://github.com/nexovadigital-dev/financiero.git
cd financiero
```

2. Instalar dependencias de PHP
```bash
composer install
```

3. Instalar dependencias de Node.js
```bash
npm install
```

4. Copiar el archivo de configuración
```bash
cp .env.example .env
```

5. Generar key de aplicación
```bash
php artisan key:generate
```

6. Configurar la base de datos en el archivo `.env`
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=financiero
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_password
```

7. Ejecutar migraciones
```bash
php artisan migrate --seed
```

8. Compilar assets
```bash
npm run build
```

9. Iniciar el servidor de desarrollo
```bash
php artisan serve
```

## Actualizaciones Recientes

### Enero 2026

#### Sistema de Gastos Operativos (v1.8)
- Nuevo módulo para gestionar gastos operativos separados de pagos a proveedores
- Gastos operativos no afectan el balance de proveedores
- Widget financiero actualizado con 4 estadísticas separadas
- Migración idempotente para actualización segura

#### Mejoras en Sistema de Precios (v1.7)
- Soporte para hasta 10 paquetes de precios por producto
- Precios base USD-Nicaragua agregados
- Corrección de prioridad de cálculo de costos para NIO
- Panel de administración mejorado para precios

#### Optimizaciones Multi-Moneda (v1.6)
- Corrección de conversión de costos para todas las monedas
- Reportes financieros siempre en USD
- Mejor manejo de bancos Nicaragua USD en ventas
- Visualización mejorada de precios NIO

#### Mejoras de Infraestructura (v1.5)
- Solución de problemas de sesiones con cPanel + Cloudflare
- Mejora en manejo de migraciones
- Corrección de errores de columnas duplicadas

## Soporte y Desarrollo

**Desarrollado por**: Nexova Digital Solutions
**Cliente**: NicaGSM
**Uso**: Exclusivo para NicaGSM

Para soporte o consultas sobre el sistema, contactar con el equipo de desarrollo de Nexova Digital Solutions.

## Licencia

Software propietario. Todos los derechos reservados por Nexova Digital Solutions.
Uso exclusivo autorizado para NicaGSM.

---

**Última actualización**: Enero 2026
**Versión**: 1.8.0
