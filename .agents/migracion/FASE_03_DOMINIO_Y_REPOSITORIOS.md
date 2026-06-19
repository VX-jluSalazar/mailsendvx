# Fase 03: dominio y repositorios modernos

## Objetivo

Reducir el acoplamiento con clases globales legacy:

- mover repositorios legacy de `classes/Repository` a `src/Repository`
- mover mailer, logger y renderer a `src/Service`
- registrar dependencias reales en el contenedor
- evitar `require_once` manuales

## Estado

Implementada en primera pasada funcional.

## Entregables

- repositorios movidos a `src/Repository`:
  - `MailSendVxTemplateRepository`
  - `MailSendVxLogRepository`
  - `MailSendVxEventRepository`
  - `MailSendVxQueueRepository`
- provider movido a `src/Provider`:
  - `MailSendVxMailProviderInterface`
  - `MailSendVxPrestaShopMailProvider`
- servicios movidos a `src/Service`:
  - `MailSendVxMailer`
  - `MailSendVxLogger`
  - `MailSendVxVariableRenderer`
- servicios Symfony actualizados para recibir dependencias reales desde `config/components/service/services.yml`
- eliminacion de `LegacyClassLoader`
- eliminacion de `require_once` manuales en `mailsendvx.php`
- eliminacion de nuevas instancias de clases globales `MailSendVx*` dentro de servicios modernos bajo `src/`

## Cambios aplicados

- `TemplateAdminService` ya no usa `new \MailSendVx...`; ahora recibe repositorio, mailer y renderer por inyeccion.
- `DashboardViewService` ya no consulta repositorios legacy directos; ahora usa repositorios modernos inyectados.
- `DashboardViewService` expone tambien capturas recientes de `ps_mailsendvx_event` para validacion operativa desde el admin.
- `InstantEmailHookService` ya no crea repositorios ni mailer manualmente durante el flujo normal; todo entra por contenedor.
- `OrderStateEventService` ya opera contra el repositorio namespaced moderno.
- La clase principal del modulo conserva solo un fallback de emergencia con clases bajo `src/`, sin carga manual desde `classes/`.

## Criterio de salida

El modulo Symfony y la clase principal ya no dependen de clases cargadas manualmente desde `classes/`.

## Como probar la fase

1. Limpiar cache de Symfony y del modulo para forzar recompilacion del contenedor. Si usas entorno local con cache persistente, vacia `var/cache/*` antes de validar.
2. Abrir el Back Office del modulo y entrar a `Configuracion`, `Templates` y `Dashboard`. La validacion esperada es que las tres pantallas carguen sin errores de clase no encontrada ni errores 500.
3. En `Templates`, crear o editar una plantilla, guardarla y volver a abrirla. La validacion esperada es que el CRUD siga funcionando con repositorio moderno.
4. Desde `Templates`, ejecutar `Enviar prueba` sobre una plantilla valida. La validacion esperada es que el envio siga funcionando y que se registre un log `sent` o `failed`, pero no un fallo por dependencias ausentes.
5. Abrir `Dashboard` y confirmar que los contadores y logs recientes siguen apareciendo. La validacion esperada es que `templates_count`, `scheduled_count`, `pending_count` y `recent_logs` sigan resolviendo datos.
6. En `Dashboard`, confirmar que existe una tabla de `Captured events` con datos de `ps_mailsendvx_event`. La validacion esperada es que cada captura muestre fecha, evento, estado, objeto asociado y payload.
7. Provocar un evento instantaneo real:
   - cambiar el estado de un pedido
   - registrar un cliente
   - registrar newsletter
   La validacion esperada es que se inserte un evento en `mailsendvx_event`, aparezca en la tabla admin de eventos capturados, se inserte un log en `mailsendvx_log` y que el correo se intente enviar cuando exista plantilla activa.
8. Revisar el codigo para confirmar el cierre tecnico:
   - `mailsendvx.php` ya no contiene `loadClasses()` ni `require_once` a `classes/`
   - `src/Service/` ya no referencia `LegacyClassLoader`
   - los servicios modernos ya no crean clases globales `MailSendVx*` desde `classes/`
9. Ejecutar validacion sintactica local. Una comprobacion minima suficiente para cerrar la fase es:
   - `php -l modules/mailsendvx/mailsendvx.php`
   - `find modules/mailsendvx/src -name '*.php' -print0 | xargs -0 -n1 php -l`

Si todas estas comprobaciones pasan, la fase puede darse por terminada en esta iteracion.
