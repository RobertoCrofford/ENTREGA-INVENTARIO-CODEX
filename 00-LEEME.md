# Paquete de traspaso — Sistema de Inventario Institucional

Este paquete permite continuar el proyecto desde otra cuenta de ChatGPT/Codex sin depender del historial de la conversación original.

## Orden de lectura

1. `documentacion/AGENTS.md`
2. `documentacion/PLAN-MAESTRO.md`
3. `documentacion/DER-Y-DICCIONARIO.md`
4. `documentacion/MATRIZ-PERMISOS-Y-ESTADOS.md`
5. `documentacion/FLUJOS-Y-CRITERIOS.md`
6. `documentacion/UI-UX-DESIGN-SYSTEM.md`
7. `documentacion/RESUMEN-TRASPASO.md`

Los archivos de `referencias-historicas/` explican el origen del proyecto, pero contienen decisiones superadas. No son fuente de verdad.

## Uso de los prompts

- Comenzar con `prompts/00-INICIAR-NUEVO-AGENTE.txt`.
- Ejecutar después los prompts en orden numérico.
- No ejecutar dos fases de implementación simultáneamente.
- No pasar a la fase siguiente con pruebas fallidas o criterios pendientes.
- Cada prompt supone que los documentos se encuentran en la raíz del repositorio o en una carpeta `documentacion/` accesible para el agente.
- Si la infraestructura institucional todavía no entrega correo, DNS, certificado o destino externo de respaldo, usar configuración local segura y dejar el punto documentado; no inventar credenciales.

## Estado al momento del traspaso

- Planificación funcional completada.
- DER conceptual y diccionario preliminar completados.
- Matriz de permisos, estados, flujos y criterios completados.
- No existe implementación de la aplicación.
- Microsoft Entra ID está fuera de la primera versión.
- La primera versión utiliza cuentas locales.

