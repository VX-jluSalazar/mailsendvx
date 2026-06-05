# Fase 00: base tecnica del modulo

## Objetivo

Crear una base tecnica estable para que Mail Send VX pueda crecer como motor de emails transaccionales y automatizados en PrestaShop 8.x.

Esta fase no busca resolver todavia todos los casos comerciales, sino preparar el modulo para instalarse, configurarse, capturar eventos, guardar datos y delegar responsabilidades en servicios reutilizables.

## Alcance funcional

- Instalar y desinstalar el modulo desde Back Office.
- Crear la configuracion general del modulo.
- Registrar hooks de PrestaShop necesarios para fases futuras.
- Crear un menu raiz en el sidebar del Back Office, al mismo nivel que `Modules`.
- Crear un panel administrativo inicial.
- Crear tablas base para plantillas, eventos, flujos, cola y logs.
- Registrar eventos base para validar que los hooks funcionan.
- Preparar un proveedor inicial de envio usando `Mail::Send()`.

## Alcance tecnico

| Subfase | Objetivo | Complejidad | Estado esperado |
| --- | --- | --- | --- |
| 0.1 Definicion de arquitectura | Definir servicios, repositorios, provider, renderer, cola y eventos internos. | Media | Arquitectura documentada y alineada con PrestaShop 8.x. |
| 0.2 Estructura base del modulo | Organizar `classes`, `controllers`, `views`, `Provider`, `Repository` y `Service`. | Media | Codigo separado por responsabilidad. |
| 0.3 Instalacion y desinstalacion | Crear `install()`, `uninstall()`, configuracion y limpieza. | Media | Modulo instalable y removible sin errores. |
| 0.4 Registro de hooks | Registrar hooks para pedido, cliente y newsletter. | Media | Eventos base capturados. |
| 0.5 Creacion de tablas | Crear tablas `mailsendvx_template`, `mailsendvx_event`, `mailsendvx_flow`, `mailsendvx_queue` y `mailsendvx_log`. | Media | Tablas disponibles despues de instalar. |
| 0.6 Servicios internos | Crear logger, mailer, renderer, repositorios y provider inicial. | Media | Servicios reutilizables por fases posteriores. |
| 0.7 Menu administrativo | Crear `Mail Send VELOX` como menu raiz con icono de buzon y submenus `Configuracion` y `Dashboard`. | Media | Menu visible en el sidebar, fuera de `Modules`. |
| 0.8 Configuracion general | Guardar activo/inactivo, debug, provider y token de cron. | Baja-media | Configuracion persistente. |
| 0.9 Sistema de logs | Registrar eventos, errores y acciones relevantes. | Baja-media | Trazabilidad minima. |
| 0.10 Provider inicial | Usar `Mail::Send()` como primer strategy de envio. | Media | Base lista para Brevo, SMTP o API. |
| 0.11 Panel administrativo inicial | Mostrar contadores, cola y logs recientes. | Media | Pantalla operativa para monitoreo basico. |

## Componentes principales

- `mailsendvx.php`: entrada del modulo, instalacion, hooks, configuracion y render de panel.
- `classes/Repository/*`: acceso a tablas internas.
- `classes/Service/MailSendVxLogger.php`: registro de logs.
- `classes/Service/MailSendVxVariableRenderer.php`: reemplazo simple de variables `{variable}`.
- `classes/Service/MailSendVxMailer.php`: orquestacion de plantilla, variables, provider y log.
- `classes/Provider/*`: abstraccion del proveedor de envio.
- `controllers/admin/AdminMailsendvx.php`: entrada raiz del menu `Mail Send VELOX`, redirige al dashboard.
- `controllers/admin/AdminMailsendvxConfigure.php`: pantalla `Configuracion` del menu lateral.
- `controllers/admin/AdminMailsendvxDashboard.php`: dashboard inicial.

## Menu de Back Office

La Fase 0 debe crear un menu de primer nivel en el sidebar, al mismo nivel que `Modules`, no dentro de la seccion de modulos.

```txt
Mail Send VELOX
|-- Configuracion
|-- Dashboard
```

El menu raiz debe usar un icono de buzon de correo. En PrestaShop se puede registrar en `Tab->icon` usando el icono Material Icons `markunread_mailbox`.

## Patrones recomendados

- Repository para acceso a base de datos.
- Strategy para proveedor de envio.
- Facade para exponer operaciones simples desde hooks, controllers y cron.
- Factory Method para construir providers y handlers cuando existan varios.
- Observer para mapear hooks de PrestaShop a eventos internos.

## Dependencias

Esta fase no depende de fases anteriores. Es prerequisito obligatorio para Fase 1, Fase 2 y Fase 3.

## Como probar la funcionalidad

1. Instalar el modulo desde Back Office.
2. Verificar que el sidebar muestra `Mail Send VELOX` al mismo nivel que `Modules`.
3. Verificar que el icono del menu raiz se muestra como buzon de correo.
4. Abrir `Mail Send VELOX > Configuracion`.
5. Activar `Enable event capture` y guardar.
6. Verificar que el provider visible sea `prestashop_mail`.
7. Abrir `Mail Send VELOX > Dashboard` y confirmar que carga sin errores.
8. Cambiar el estado de un pedido existente.
9. Crear una cuenta de cliente de prueba.
10. Ejecutar una suscripcion a newsletter si el hook esta disponible en la tienda.
11. Revisar que existan filas nuevas en `PREFIX_mailsendvx_event`.
12. Revisar que existan logs nuevos en `PREFIX_mailsendvx_log`.

## Consultas utiles de validacion

```sql
SHOW TABLES LIKE '%mailsendvx%';

SELECT event_name, status, date_add
FROM PREFIX_mailsendvx_event
ORDER BY id_mailsendvx_event DESC
LIMIT 20;

SELECT event_name, recipient, status, message, date_add
FROM PREFIX_mailsendvx_log
ORDER BY id_mailsendvx_log DESC
LIMIT 20;
```

## Criterios de aceptacion

- El modulo se instala sin errores.
- Las tablas internas se crean correctamente.
- La configuracion se guarda y persiste.
- El menu `Mail Send VELOX` aparece como menu raiz del sidebar.
- El menu raiz muestra un icono de buzon de correo.
- Los submenus `Configuracion` y `Dashboard` cargan desde Back Office.
- Los hooks registrados capturan eventos cuando el modulo esta activo.
- Si el modulo esta desactivado, no se capturan eventos nuevos.
- La desinstalacion elimina configuracion, tab administrativo y tablas internas.

## Riesgos

- Borrar tablas durante `uninstall()` puede eliminar informacion historica si se usa en produccion.
- Algunos hooks pueden no dispararse si la tienda tiene modulos externos que reemplazan comportamientos nativos.
- `Mail::Send()` depende de la configuracion nativa de email de PrestaShop.
