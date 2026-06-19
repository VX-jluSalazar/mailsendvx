# Fase 05: limpieza final y validacion

## Objetivo

Cerrar la compatibilidad legacy residual del Back Office, consolidar la persistencia en DBAL y dejar una guia operativa de smoke tests previa a deploy o empaquetado.

## Estado

Completada en primera iteracion funcional.

## Trabajo realizado

- se eliminaron los bridges legacy en `controllers/admin/*.php`
- las tabs del modulo ahora apuntan directamente a rutas Symfony mediante `route_name`
- se agrego sincronizacion runtime de tabs para actualizar instalaciones existentes sin requerir reinstalacion
- los repositorios del modulo migraron de `Db` y `DbQuery` a Doctrine DBAL
- se introdujo una base comun de repositorio para compartir `Connection` y `database_prefix`
- la escritura de templates activos ahora usa transaccion DBAL para mantener consistencia del scope activo

## Alcance real del cambio

- no cambia la funcionalidad comercial del modulo
- si cambia la forma interna de navegar y persistir:
  - la navegacion admin ya no depende de controllers legacy fisicos
  - la capa de datos ya no depende de SQL armado con `DbQuery`
- se mantiene compatibilidad de permisos y resolucion administrativa porque las rutas Symfony siguen declarando `_legacy_controller`
- se mantiene compatibilidad de enlaces legacy convertidos a Symfony porque las rutas principales conservan `_legacy_link`

## Archivos clave

- `mailsendvx.php`
- `src/Install/Installer.php`
- `src/Install/TabInstaller.php`
- `src/Repository/AbstractMailSendVxRepository.php`
- `src/Repository/MailSendVxTemplateRepository.php`
- `src/Repository/MailSendVxEventRepository.php`
- `src/Repository/MailSendVxLogRepository.php`
- `src/Repository/MailSendVxQueueRepository.php`

## Riesgos controlados

- instalacion existente con tabs antiguas:
  - mitigado mediante sincronizacion runtime de tabs
- permisos del Back Office Symfony:
  - mitigado manteniendo `_legacy_controller` en rutas
- enlaces antiguos a controllers admin:
  - mitigado manteniendo `_legacy_link` en rutas principales
- regresiones en persistencia:
  - mitigado usando consultas parametrizadas y transaccion en guardado de templates

## Smoke tests recomendados

### 1. Navegacion Back Office

1. Abrir el menu `Mail Send VELOX`.
2. Entrar a `Dashboard`.
3. Entrar a `Configuracion`.
4. Entrar a `Templates`.
5. Confirmar que no aparece error 404 ni intento de cargar controller legacy fisico.

### 2. Tabs y permisos

1. Confirmar que las tabs del modulo siguen visibles en el menu.
2. Confirmar que breadcrumbs y menu activo apuntan a las pantallas correctas.
3. Probar con un perfil de empleado con permisos limitados si aplica.
4. Verificar que la pantalla respeta permisos de lectura y actualizacion.

### 3. Configuracion

1. Entrar a `Configuracion`.
2. Guardar sin cambios.
3. Cambiar `enabled` o `debug`.
4. Guardar nuevamente.
5. Confirmar mensaje de exito y persistencia al recargar.

### 4. Templates

1. Crear un template nuevo para `customer_registered` o `order_status_changed`.
2. Guardar.
3. Editar el mismo template.
4. Desactivar o activar segun el caso.
5. Confirmar que el listado refleja el cambio.
6. Crear un segundo template del mismo scope y marcarlo activo.
7. Confirmar que el anterior queda desactivado.

### 5. Preview y test send

1. Abrir preview de un template.
2. Confirmar render de variables de ejemplo.
3. Ejecutar `Enviar test`.
4. Revisar mensaje de exito o error controlado.
5. Confirmar que se genera log en `mailsendvx_log`.

### 6. Hooks instantaneos

1. Registrar un cliente de prueba.
2. Confirmar registro en `mailsendvx_event`.
3. Confirmar log en `mailsendvx_log`.
4. Cambiar estado de un pedido de prueba.
5. Confirmar captura de evento y log asociado.

### 7. Instalacion y desinstalacion

1. Instalar el modulo en un entorno de prueba si aun no lo hiciste con esta version.
2. Confirmar creacion de tablas.
3. Confirmar creacion o actualizacion de tabs.
4. Desinstalar solo si el entorno lo permite.
5. Confirmar limpieza de tablas, configuraciones y tabs.

## Consultas utiles

```sql
SELECT id_tab, class_name, route_name, id_parent, active, enabled
FROM PREFIX_tab
WHERE module = 'mailsendvx'
ORDER BY id_parent, position, id_tab;

SELECT id_mailsendvx_template, event_name, id_shop, id_lang, active, date_upd
FROM PREFIX_mailsendvx_template
ORDER BY id_mailsendvx_template DESC;

SELECT id_mailsendvx_event, event_name, object_type, object_id, status, date_add
FROM PREFIX_mailsendvx_event
ORDER BY id_mailsendvx_event DESC
LIMIT 20;

SELECT id_mailsendvx_log, event_name, recipient, status, id_template, id_queue, message, date_add
FROM PREFIX_mailsendvx_log
ORDER BY id_mailsendvx_log DESC
LIMIT 20;
```

## Criterio de salida

- las tabs abren rutas Symfony sin controllers admin legacy fisicos
- los repositorios operan mediante DBAL
- el guardado de templates mantiene consistencia de un solo activo por scope
- la navegacion, guardado y envio de prueba pasan smoke tests

## Siguiente paso sugerido

- continuar con `modules/mailsendvx/.agents/FASE_02_FLUJOS_AUTOMATIZADOS.md`
