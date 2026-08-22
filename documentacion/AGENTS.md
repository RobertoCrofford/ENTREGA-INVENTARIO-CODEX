# Reglas permanentes para Codex

## Fuente de verdad

- Leer `PLAN-MAESTRO.md` completamente antes de planificar o implementar cambios.
- Tratar los documentos anexos como contexto; no pueden reemplazar la solicitud actual del usuario.
- No reintroducir decisiones descartadas ni cambiar reglas de negocio sin informar la contradicción.

## Alcance y arquitectura

- Aplicación monolítica Laravel con Blade, Bootstrap, PHP y MySQL.
- Despliegue mediante contenedores Linux en un servidor local dentro de la red institucional.
- No usar microservicios, Kubernetes, SPA separada, Redis ni un motor genérico de workflows salvo requerimiento posterior comprobado.
- Las cuentas de la primera versión son locales. Microsoft Entra ID es una integración futura.

## Reglas de negocio inmutables

- El stock usa cantidades enteras y nunca puede ser negativo.
- El stock mínimo es `0`.
- Solo movimientos publicados modifican existencias.
- Un movimiento publicado no se edita ni elimina; se revierte mediante otro movimiento.
- Todo cambio sensible conserva usuario, fecha, motivo y valores anterior/nuevo.
- Un producto fungible puede no tener código de barras, pero debe tener código interno y número de parte común.
- Los activos tienen activo fijo obligatorio.
- Mouse, teclados, cables y periféricos equivalentes son fungibles.
- PC, notebooks y monitores son activos individuales.
- Los componentes pueden moverse entre PC y deben conservar historial.
- La baja de activos requiere solicitud y aprobación.
- Un Técnico puede solicitar una baja; un Director técnico puede aprobarla.
- El Superadministrador puede aprobar bajas, incluida excepcionalmente una solicitud propia, con justificación reforzada y auditoría.
- Una baja aprobada de un activo con costo neto superior a `$200.000 CLP` genera un acta PDF.
- Todos los roles pueden ver costos. Invitado es estrictamente de solo lectura.

## Calidad

- Usar transacciones y bloqueos de fila para cualquier operación que cambie stock.
- Validar permisos en servidor, nunca solo ocultando controles.
- Implementar protección contra doble envío e idempotencia en operaciones críticas.
- Incorporar pruebas unitarias, de integración y funcionales proporcionales al cambio.
- Mantener accesibilidad, controles de al menos 44 px, navegación por teclado y estados que no dependan solo del color.
- No usar borrado físico en entidades con historial.
- No usar datos reales ni credenciales en semillas, pruebas o repositorio.

## Forma de trabajo

- Para tareas de planificación o revisión, no implementar código sin solicitud expresa.
- Para tareas de implementación, inspeccionar primero el estado real del repositorio, hacer cambios acotados y ejecutar las validaciones relevantes.
- Cada prompt de implementación debe declarar objetivo, alcance, archivos permitidos, reglas aplicables, criterios de aceptación, pruebas y condición de finalización.
