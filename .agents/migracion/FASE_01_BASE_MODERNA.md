# Fase 01: base moderna del modulo

## Objetivo

Crear la base estructural para una arquitectura moderna:

- `composer.json`
- `vendor/autoload.php`
- `src/`
- `config/common.yml`
- `config/admin/services.yml`
- `config/front/services.yml`
- instaladores en `src/Install/`

## Estado

Implementado y aterrizado en codigo.

## Entregables

- Autoload PSR-4 real generado con Composer.
- Clase compartida de constantes `src/ModuleConstants.php`.
- `Installer`, `ConfigurationInstaller`, `DatabaseInstaller`, `TabInstaller`.
- Extraccion de handlers de hooks instantaneos a `src/Service/Event/InstantEmailHookService.php`.
- `mailsendvx.php` ya delega instalacion y hooks principales.
- Servicios registrados tanto para admin como para front.

## Pendientes

### 1. Sustituir el autoload provisional por Composer real

Estado:

- Completado.
- `composer dump-autoload` ya genero `vendor/autoload.php` y archivos Composer reales.

Resultado aplicado:

- Se elimino la dependencia de un `vendor/autoload.php` artesanal.
- La carga PSR-4 namespaced del modulo ya depende de Composer.

Estado final:

- Cerrado.

### 2. Reducir mas la responsabilidad de `mailsendvx.php`

Estado:

- Completado en esta fase para la capa principal.
- La clase principal ya no arma variables de pedidos, clientes o newsletter.
- Los hooks instantaneos delegan en `src/Service/Event/InstantEmailHookService.php`.

Resultado aplicado:

- Se extrajeron handlers de hooks a un servicio dedicado.
- La clase principal conserva metadata, instalacion, compatibilidad BO y delegacion.
- La logica de negocio restante mas pesada ya vive en `src/`.

Nota:

- Aun existen instancias manuales de instaladores y un fallback de servicio.
- Eso es aceptable en esta base moderna inicial; la siguiente reduccion fuerte corresponde a Fase 03.

Estado final:

- Cerrado para Fase 01.

### 3. Alinear constantes y configuracion compartida

Estado:

- Completado.
- `src/ModuleConstants.php` ya es la fuente compartida.
- `mailsendvx.php` expone aliases para compatibilidad legacy.

Resultado aplicado:

- Las clases Symfony y servicios modernos consumen `ModuleConstants`.
- La clase global mantiene aliases publicos para no romper integraciones legacy del modulo.

Estado final:

- Cerrado.

### 4. Formalizar el uso del contenedor

Estado:

- Parcialmente completado.
- Ya existen servicios registrados para admin y front.
- El servicio `InstantEmailHookService` ya se resuelve desde el contenedor cuando esta disponible.

Resultado aplicado:

- Se incorporo `config/components/service/services.yml` al contexto front.
- Los hooks ya usan un servicio del contenedor en lugar de implementar la logica dentro del modulo principal.

Pendiente real que pasa de fase:

- Migrar repositorios y servicios legacy de `classes/` a `src/` y registrarlos como dependencias reales.
- Eliminar `LegacyClassLoader` y reducir mas `new ...` en servicios modernos.

Estado final:

- Base moderna suficiente y funcional.
- Lo que falta ya pertenece a Fase 03, no a Fase 01.

## Cierre de fase

La Fase 01 de migracion queda cerrada como base moderna operativa:

- Composer y PSR-4 reales.
- `mailsendvx.php` mas delgado.
- Hooks principales delegados a servicios.
- Constantes compartidas desacopladas de la clase global.

La siguiente deuda tecnica prioritaria es convertir repositorios, mailer, renderer y logger legacy a servicios modernos de `src/`.
