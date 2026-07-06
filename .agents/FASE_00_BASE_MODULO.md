# Fase 00: base tecnica del modulo

## Objetivo

Crear una base tecnica estable para que Mail Send VX pueda crecer como motor de emails transaccionales y automatizados en PrestaShop 8.x.

Esta fase no busca resolver todavia todos los casos comerciales, sino preparar el modulo para instalarse, configurarse, capturar eventos, guardar datos y delegar responsabilidades en servicios reutilizables.

## Alcance funcional

- Instalar y desinstalar el modulo desde Back Office.
- Crear la configuracion general del modulo.
- Registrar hooks de PrestaShop necesarios para fases futuras.
- Crear un menu desplegable en el sidebar del Back Office, dentro de la seccion `Configure`, con el mismo comportamiento visual de `Shop Parameters`.
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
| 0.7 Menu administrativo | Crear `Mail Send VELOX` como menu desplegable con icono de buzon y submenus `Configuracion`, `Templates` y `Dashboard`. | Media | Menu visible dentro de `Configure`, con flecha desplegable como `Shop Parameters`. |
| 0.8 Configuracion general | Guardar activo/inactivo, debug, provider y token de cron. | Baja-media | Configuracion persistente. |
| 0.9 Sistema de logs | Registrar eventos, errores y acciones relevantes. | Baja-media | Trazabilidad minima. |
| 0.10 Provider inicial | Usar `Mail::Send()` como primer strategy de envio. | Media | Base lista para Brevo, SMTP o API. |
| 0.11 Panel administrativo inicial | Mostrar contadores, cola y logs recientes. | Media | Pantalla operativa para monitoreo basico. |

## Componentes principales

- `mailsendvx.php`: entrada del modulo, instalacion, hooks, configuracion y render de panel.
- `src/Repository/*`: acceso a tablas internas.
- `src/Service/Support/MailSendVxLogger.php`: registro de logs.
- `src/Service/Template/MailSendVxVariableRenderer.php`: reemplazo simple de variables `{variable}`.
- `src/Service/Mail/MailSendVxMailer.php`: orquestacion de plantilla, variables, provider y log.
- `src/Provider/*`: abstraccion del proveedor de envio.
- `controllers/admin/AdminMailsendvx.php`: entrada padre del menu `Mail Send VELOX`, redirige al dashboard si se accede por URL directa.
- `controllers/admin/AdminMailsendvxConfigure.php`: pantalla `Configuracion` del menu lateral.
- `controllers/admin/AdminMailsendvxDashboard.php`: dashboard inicial.

## Menu de Back Office

La Fase 0 debe crear un menu desplegable en el sidebar, dentro de la seccion nativa `Configure`, con el mismo comportamiento visual de `Shop Parameters`.

```txt
Configure
|-- Shop Parameters
|-- Advanced Parameters
|-- Mail Send VELOX
    |-- Configuracion
    |-- Templates
    |-- Dashboard
```

El menu padre debe usar un icono de buzon de correo. En PrestaShop se puede registrar en `Tab->icon` usando el icono Material Icons `markunread_mailbox`.

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
2. Verificar que el sidebar muestra `Mail Send VELOX` dentro de la seccion `Configure`.
3. Verificar que `Mail Send VELOX` tiene flecha desplegable y se comporta como `Shop Parameters`.
4. Verificar que el icono del menu se muestra como buzon de correo.
5. Abrir `Mail Send VELOX > Configuracion`.
6. Activar `Enable event capture` y guardar.
7. Verificar que el provider visible sea `prestashop_mail`.
8. Abrir `Mail Send VELOX > Dashboard` y confirmar que carga sin errores.
9. Cambiar el estado de un pedido existente.
10. Crear una cuenta de cliente de prueba.
11. Ejecutar una suscripcion a newsletter si el hook esta disponible en la tienda.
12. Revisar que existan filas nuevas en `PREFIX_mailsendvx_event`.
13. Revisar que existan logs nuevos en `PREFIX_mailsendvx_log`.

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
- El menu `Mail Send VELOX` aparece dentro de la seccion `Configure`.
- El menu muestra un icono de buzon de correo.
- El menu tiene flecha desplegable y muestra sus submenus sin recargar la pagina como una pantalla independiente.
- Los submenus `Configuracion`, `Templates` y `Dashboard` cargan desde Back Office.
- Los hooks registrados capturan eventos cuando el modulo esta activo.
- Si el modulo esta desactivado, no se capturan eventos nuevos.
- La desinstalacion elimina configuracion, tab administrativo y tablas internas.

## Riesgos

- Borrar tablas durante `uninstall()` puede eliminar informacion historica si se usa en produccion.
- Algunos hooks pueden no dispararse si la tienda tiene modulos externos que reemplazan comportamientos nativos.
- `Mail::Send()` depende de la configuracion nativa de email de PrestaShop.
