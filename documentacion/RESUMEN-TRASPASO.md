# Resumen de traspaso para el nuevo agente

## Objetivo

Construir una intranet de inventario institucional ejecutada en contenedores Linux sobre un servidor local. Debe gestionar productos fungibles en múltiples bodegas, activos individuales, PC y componentes, asignaciones, reparaciones simples, bajas aprobadas, reportes, importaciones, correo, auditoría y respaldos.

## Escala

- Una sede inicialmente, preparada para varias.
- Múltiples bodegas.
- Más de 10.000 activos.
- Menos de 30 usuarios y baja concurrencia.
- Acceso solo desde la red institucional.

## Tecnología decidida

- Laravel 13, PHP 8.4, Blade y Bootstrap 5.3.
- MySQL 8.4 LTS con InnoDB.
- Docker Engine y Compose sobre Linux.
- Monolito MVC; no microservicios, Kubernetes, SPA separada ni Redis inicial.

## Reglas esenciales

- Stock entero, mínimo `0` y nunca negativo.
- Cuentas locales en la primera versión.
- Roles: Invitado, Técnico, Director técnico y Superadministrador.
- Todos pueden ver costos; Invitado es solo lectura.
- Productos fungibles: código interno y número de parte común obligatorios; código escaneable opcional.
- Activos: activo fijo obligatorio; código escaneable opcional de 13 dígitos.
- PC, notebooks y monitores son activos; mouse, teclados y cables son fungibles.
- Procesador obligatorio en PC.
- Componentes transferibles entre PC con historial.
- Técnico solicita bajas; Director o Superadministrador aprueban.
- Director no se autoaprueba. Superadministrador puede hacerlo excepcionalmente con justificación y auditoría reforzada.
- Baja de activo con costo neto `> 200000 CLP` genera acta PDF. Exactamente $200.000 no genera acta.
- Baja de stock aprobada genera comprobante PDF agrupado.
- Movimientos publicados son inmutables y se revierten con compensación.
- Sesión expira tras 30 minutos de inactividad.

## Insumos institucionales pendientes

- Catálogos reales de sedes, bodegas, salas y tipos.
- Logo y encabezado final.
- Buzón remitente.
- DNS y certificado HTTPS.
- Destino externo de respaldo.

Estos insumos no bloquean el desarrollo local y deben manejarse mediante configuración, nunca con valores inventados en producción.

