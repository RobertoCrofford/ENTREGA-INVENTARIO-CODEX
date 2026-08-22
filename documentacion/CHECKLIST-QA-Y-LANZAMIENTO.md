# Checklist de QA, piloto y lanzamiento

## Evidencia local verificada

- [x] Docker Compose inicia aplicación, proxy y MySQL; `/up` responde mediante el proxy.
- [x] Las migraciones se aplican contra MySQL 8.4 sin pendientes.
- [x] Roles fijos, autenticación local, bloqueo temporal y cambio obligatorio de contraseña están implementados.
- [x] Productos, existencias, movimientos, activos, componentes, bajas, bitácora e importaciones cuentan con esquema físico.
- [x] El backup local MySQL se genera y se verificó su compresión.
- [x] El proyecto formatea con Laravel Pint y la suite disponible pasa.

## Pruebas funcionales que deben ejecutarse en el piloto

- [ ] Crear una sede y una bodega; registrar una categoría, producto y entrada.
- [ ] Intentar dos salidas que superen el stock y confirmar que no se publica stock negativo.
- [ ] Ejecutar traslado entre bodegas distintas y comprobar ambos saldos e historial.
- [ ] Probar cuenta Invitado contra rutas de escritura.
- [ ] Desactivar una cuenta en sesión y verificar cierre de sesión.
- [ ] Registrar activo, moverlo a reparación y completarla con resultado.
- [ ] Solicitar y aprobar una baja por valor menor, igual y mayor a $200.000; comprobar PDF únicamente para el último caso.
- [ ] Probar autoaprobación de Superadministrador con y sin justificación.
- [ ] Importar un archivo de prueba y conservar el informe de errores.
- [ ] Restaurar un backup en una base vacía y comparar conteos críticos.

## Bloqueos previos a producción

- [ ] Catálogo institucional definitivo de sede, bodegas, salas, categorías y tipos.
- [ ] Logo, nombre institucional y formato de actas aprobado.
- [ ] DNS interno, certificado HTTPS y secretos de producción configurados.
- [ ] Correo remitente institucional y destinatarios de alertas configurados y probados.
- [ ] Segundo destino de respaldo cifrado, verificado y con responsable asignado.
- [ ] Piloto con usuarios autorizados, acta de aceptación y plan de reversión firmado.

No se debe declarar un lanzamiento productivo hasta cerrar los ítems anteriores: dependen de datos, infraestructura y aprobaciones institucionales que no están presentes en el repositorio.
