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

Implementado.

## Entregables

- Autoload PSR-4 inicial.
- `Installer`, `ConfigurationInstaller`, `DatabaseInstaller`, `TabInstaller`.
- Primera extraccion de logica reusable a `src/Service/`.
- `mailsendvx.php` ya delega instalacion y parte de la logica.

## Pendientes

- Reemplazar el autoload manual provisional por Composer generado real.
- Registrar mas servicios en contenedor Symfony en lugar de instanciacion directa.
