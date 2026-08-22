# Plan maestro — Sistema de Inventario Institucional

## 1. Estado del documento

Este documento es la fuente de verdad preliminar del proyecto. Consolida las decisiones funcionales, técnicas, operativas y de experiencia de usuario confirmadas hasta el 21 de agosto de 2026.

La planificación funcional está cerrada y fue validada contra el DER, la matriz y los flujos. Los insumos de la sección 22 son configuraciones institucionales necesarias antes de producción, no bloqueos para el DER, la infraestructura ni la implementación local.

## 2. Objetivo

Construir una aplicación web institucional para:

- Gestionar existencias fungibles en múltiples bodegas.
- Registrar entradas, salidas, devoluciones, traslados, ajustes y bajas de stock.
- Gestionar activos individuales ubicados en salas, bodegas, talleres o asignados a funcionarios.
- Registrar PC, notebooks, monitores y otros activos institucionales.
- Mantener especificaciones y componentes de PC.
- Escanear identificadores mediante una pistola USB que funciona como teclado.
- Conservar trazabilidad de cambios y movimientos.
- Gestionar solicitudes y aprobaciones de baja.
- Generar actas PDF para bajas de activos de valor relevante.
- Entregar dashboard, consultas, reportes PDF y exportaciones Excel.
- Operar exclusivamente dentro de la red institucional.

## 3. Alcance operativo

- Una sede en el lanzamiento, con soporte estructural para varias sedes.
- Múltiples bodegas desde la primera versión.
- Más de 10.000 activos potenciales.
- Menos de 30 usuarios registrados y baja concurrencia simultánea.
- Cantidades de inventario exclusivamente enteras.
- Sin datos iniciales existentes; se requerirá carga progresiva e importación masiva.
- Sin exposición pública de la aplicación.

## 4. Arquitectura recomendada

### 4.1 Aplicación

- Laravel 13.
- PHP 8.4.
- Arquitectura monolítica MVC.
- Blade para vistas renderizadas por servidor.
- Bootstrap 5.3 y Bootstrap Icons.
- JavaScript acotado para escaneo, autocompletado, drawers, confirmaciones y mejoras de interfaz.
- MySQL 8.4 LTS con InnoDB.
- Vite para activos frontend.

### 4.2 Contenedores

- `proxy`: Nginx o Caddy.
- `app`: Laravel y PHP-FPM.
- `database`: MySQL con volumen persistente.
- `queue`: correos, PDF y exportaciones.
- `scheduler`: alertas y tareas programadas.
- `backup`: respaldos y verificación.

No se incorporará Redis inicialmente. Las colas, sesiones y caché pueden utilizar la base de datos para el volumen previsto.

### 4.3 Servidor

Recomendación inicial:

- Linux, preferentemente Ubuntu Server LTS o Debian.
- Docker Engine y Docker Compose.
- 4 núcleos de CPU.
- 8 GB de RAM.
- SSD de 150 a 250 GB.
- IP fija.
- UPS.
- DNS interno o nombre institucional estable.
- HTTPS obligatorio.

Windows Server no es necesario salvo exigencia institucional. Docker Desktop no debe considerarse plataforma productiva.

## 5. Autenticación

### 5.1 Primera versión

Las cuentas serán locales:

- Sin autorregistro público.
- Creación y activación por Superadministrador.
- Nombre y correo institucional obligatorios.
- Nombre de usuario único.
- Contraseña almacenada mediante hash seguro.
- Cambio obligatorio de contraseña inicial.
- Limitación progresiva de intentos fallidos.
- Registro de último acceso.
- Invalidación de sesiones al desactivar una cuenta.
- Cierre después de 30 minutos de inactividad.
- Advertencia aproximadamente dos minutos antes del cierre.

### 5.2 Futuro

Microsoft Entra ID mediante OpenID Connect se implementará posteriormente. La primera versión no dependerá de esta integración.

No se implementará un sistema híbrido complejo anticipadamente. La migración futura utilizará correo institucional y un identificador externo estable, conservando el usuario local y su historial.

## 6. Roles y permisos

### 6.1 Invitado

- Solo lectura.
- Consultar productos, stock, activos, salas, costos y reportes autorizados.
- Exportar reportes permitidos.
- No puede crear, modificar, aprobar ni publicar operaciones.

### 6.2 Técnico

- Crear y modificar productos.
- Modificar costos netos, dejando historial.
- Registrar entradas, salidas, devoluciones y traslados.
- Registrar activos y componentes.
- Asignar y trasladar activos.
- Cambiar un activo a estado En reparación o No operativo.
- Solicitar bajas de activos o stock dañado.
- No puede aprobar bajas.
- No puede realizar ajustes directos de stock.

### 6.3 Director técnico

- Todas las capacidades del Técnico.
- Aprobar o rechazar bajas solicitadas por otras personas.
- Autorizar ajustes y bajas de stock.
- Revertir movimientos mediante operaciones compensatorias.
- Revisar diferencias e historial operativo.
- Recibir alertas de stock agotado.
- Consultar bitácora.

No debe aprobar una solicitud propia.

### 6.4 Superadministrador

- Gestionar usuarios, roles y configuración.
- Consultar bitácora completa.
- Configurar correo, respaldos e integraciones.
- Aprobar bajas.
- Puede aprobar excepcionalmente una solicitud propia.

La autoaprobación del Superadministrador requiere:

- Justificación obligatoria adicional.
- Confirmación reforzada.
- Evento de auditoría destacado.
- Notificación al Director técnico cuando exista uno activo.

Todos los roles pueden visualizar costos.

## 7. Conceptos del dominio

### 7.1 Producto fungible

Elemento controlado por cantidad, no por unidad física individual:

- Mouse.
- Teclados.
- Cables.
- Adaptadores.
- Insumos y periféricos equivalentes.

Un producto fungible puede no tener código de barras, pero debe tener:

- Código interno generado por el sistema.
- Número de parte común obligatorio.
- Nombre.
- Categoría.
- Costo neto sin IVA.

El número de parte identifica el modelo o referencia común, no una unidad individual. No debe tratarse como serial unitario.

### 7.2 Activo individual

Objeto físico controlado individualmente:

- PC.
- Notebook.
- Monitor.
- Proyector.
- Impresora.
- Equipamiento institucional equivalente.

Todo activo tiene activo fijo obligatorio y conserva historial individual.

### 7.3 Componente

Parte instalada en un PC:

- Procesador.
- RAM.
- Disco.
- GPU.
- Placa madre.
- Fuente.
- Tarjeta de red.

El activo fijo y número de serie del componente son opcionales. Un componente puede moverse entre PC y debe conservar historial de instalación y retiro.

## 8. Identificadores

### 8.1 Productos

- `codigo_interno`: obligatorio, único y generado por el sistema.
- `numero_parte`: obligatorio; no representa una unidad individual y no es único por sí solo.
- `codigo_barras`: opcional, numérico, entre 1 y 13 caracteres cuando exista.
- `nombre`: obligatorio.

Los códigos de barras se almacenan como texto para conservar ceros iniciales.

### 8.2 Activos

- `activo_fijo`: obligatorio y único.
- `codigo_institucional`: opcional, único y exactamente de 13 dígitos cuando exista.
- `numero_serie`: opcional cuando el fabricante no lo entregue o no sea legible.

La ausencia o deterioro de una etiqueta no impide registrar ni consultar un activo: el activo fijo es su identidad obligatoria y siempre existe búsqueda manual.

### 8.3 Escaneo

- La pistola actúa como teclado y normalmente envía `ENTER`.
- El campo de escaneo solo captura cuando está activo.
- Se eliminan espacios periféricos, pero no ceros iniciales.
- El servidor evita lecturas duplicadas y doble envío.
- Siempre existe entrada manual alternativa.
- Un código desconocido nunca crea un registro automáticamente.

## 9. Sedes y ubicaciones

La sede es obligatoria desde la primera versión.

Tipos de ubicación:

- Bodega.
- Sala.
- Oficina o unidad.
- Taller.
- Disposición o baja.

La sede inicial queda preseleccionada en la interfaz, evitando pasos innecesarios.

Asignaciones permitidas:

- PC: sala, bodega o taller.
- Notebook: sala, funcionario, bodega o taller.
- Monitor: sala, funcionario, bodega o taller.
- Componentes: disponibles en bodega/taller o instalados en un PC.

Todo activo activo debe tener ubicación o funcionario responsable. La ausencia desconocida se representa con estado No localizado, no mediante ubicación nula.

## 10. Existencias

La existencia se controla por combinación de producto y bodega.

Reglas:

- Cantidad entera.
- Stock mínimo fijo: `0`.
- Stock actual nunca menor que `0`.
- Se alerta cuando una existencia activa llega a `0`.
- Solo se crea una existencia producto–bodega cuando el producto se habilita o recibe stock en esa bodega.
- El total consolidado se calcula sumando bodegas.
- No se almacena una única cifra global como fuente de verdad.

## 11. Movimientos de inventario

### 11.1 Estados

- Borrador.
- Publicado.
- Revertido, mediante referencia a una operación compensatoria.

Solo Publicado cambia existencias.

### 11.2 Tipos

- Entrada.
- Salida.
- Devolución.
- Traslado.
- Ajuste.
- Baja de stock.
- Reversión.

### 11.3 Entrada

Aumenta existencias en una bodega. Requiere destino, productos, cantidades, costo neto de referencia, motivo y usuario.

### 11.4 Salida

Disminuye stock controlado sin aumentarlo en otra bodega. Requiere receptor y motivo.

Tipos de receptor recomendados:

- Funcionario: nombre, correo institucional y departamento opcional.
- Sala.
- Unidad o departamento.
- Consumo técnico interno, con referencia opcional al activo reparado.

### 11.5 Devolución

Entrada especial que puede referenciar la salida original.

- Si el producto está utilizable, vuelve a stock.
- Si está dañado, queda pendiente de solicitud de baja y no se incorpora al stock disponible.

### 11.6 Traslado

Mueve productos entre dos bodegas controladas.

- Origen y destino distintos.
- Descuento y aumento dentro de una sola transacción.
- Si falla una parte, no se aplica ninguna.

### 11.7 Ajuste

- Solo Director técnico o Superadministrador.
- Requiere motivo.
- Puede aumentar o reducir.
- Nunca puede dejar cantidad negativa.

### 11.8 Publicación y concurrencia

Toda publicación debe:

1. Iniciar transacción.
2. Bloquear las existencias afectadas.
3. Revalidar cantidades.
4. Insertar movimiento y detalle.
5. Actualizar existencias.
6. Registrar bitácora.
7. Confirmar o revertir todo.

Se debe utilizar una clave de idempotencia para impedir duplicados por doble clic, recarga o doble `ENTER` del lector.

## 12. Costos

- Todos los roles pueden visualizar costos.
- El costo es neto sin IVA.
- Técnicos, Directores técnicos y Superadministradores pueden modificarlo.
- Toda modificación conserva historial.
- Cada movimiento guarda una instantánea del costo utilizado.
- El costo actual no reescribe reportes históricos.

Se utilizará inicialmente un costo neto referencial, no costo promedio contable ni módulo de compras.

## 13. Estados de activos

Estados mínimos:

- Operativo.
- En reparación.
- No operativo.
- No localizado.
- Dado de baja.

Pendiente de baja es un estado de la solicitud, no del activo.

No se utilizará Traslado como estado porque el traslado se registra como evento. En tránsito se agregará solo si el proceso físico futuro lo requiere.

## 14. Reparación

No se construirá un módulo de mantenimiento avanzado.

Flujo:

1. Marcar activo En reparación.
2. Registrar motivo, responsable, ubicación y fecha.
3. Al finalizar, cambiar a Operativo o No operativo.
4. Registrar resultado y fecha.

Los productos consumidos en la reparación se registran como Salida por consumo técnico interno, opcionalmente referenciada al activo.

## 15. Especificaciones y componentes de PC

Para un PC, el procesador es la única especificación técnica obligatoria inicial.

Campos opcionales estructurados:

- RAM total.
- Tipo de RAM.
- Almacenamiento total.
- Tipo de almacenamiento.
- Sistema operativo.
- Arquitectura.
- Placa madre.
- GPU.
- Dirección MAC.
- Nombre de red.
- TPM.
- Observaciones.

Componentes:

- Un componente solo puede estar instalado en un PC a la vez.
- Estados: Disponible, Instalado, En reparación y Dado de baja.
- Instalación y retiro conservan PC, usuario, fecha, motivo y observación.
- Los componentes pueden moverse entre PC.

Los monitores son activos independientes y no se ligan obligatoriamente a un PC.

## 16. Bajas

### 16.1 Baja de activo

Estados de solicitud:

- Borrador.
- Pendiente.
- Aprobada.
- Rechazada.
- Cancelada.

Reglas:

- Técnico, Director técnico o Superadministrador pueden solicitar.
- Director técnico o Superadministrador pueden aprobar.
- Un Director no aprueba su propia solicitud.
- El Superadministrador sí puede aprobar excepcionalmente una solicitud propia con justificación reforzada.
- Un activo solo puede tener una solicitud activa.
- La aprobación cambia el activo a Dado de baja dentro de una transacción.
- El activo no se elimina.

### 16.2 Regla de PDF por valor

Se genera acta PDF solo cuando:

```text
costo_neto_aprobado > 200000 CLP
```

La comparación es estricta y utiliza una instantánea del costo neto sin IVA al momento de aprobar. Un activo con costo exactamente igual a $200.000 no genera acta PDF.

El alta manual exige costo. Si un activo histórico importado carece de costo, la aprobación debe exigir que se complete antes de continuar.

El acta contiene:

- Logo y nombre institucional.
- Sede.
- Identificador interno de solicitud.
- Fecha de solicitud y aprobación.
- Datos completos del activo.
- Costo neto usado para evaluar el umbral.
- Ubicación anterior.
- Motivo y diagnóstico.
- Solicitante y aprobador.
- Componentes relevantes.
- Observaciones.
- Espacios para firmas y fechas.
- Identificador de verificación.

No se implementará firma digital. El acta se descarga e imprime.

Las bajas inferiores a $200.000 conservan solicitud, aprobación, historial y bitácora, pero no generan acta PDF.

### 16.3 Baja de stock fungible

Los productos dañados pasan por solicitud de baja.

- El Técnico solicita.
- El Director técnico o Superadministrador aprueba.
- El Superadministrador puede autoaprobar excepcionalmente con las mismas exigencias reforzadas de una baja de activo.
- Una solicitud puede agrupar varios productos y cantidades.
- Al aprobar se publica un movimiento Baja de stock.
- Nunca puede producir stock negativo.
- Toda baja de stock aprobada genera un comprobante PDF agrupado con los productos, cantidades, motivo, solicitante y aprobador.

## 17. Dashboard y alertas

### Invitado

- Stock y activos.
- Costos.
- Reportes autorizados.

### Técnico

- Operaciones recientes.
- Activos en reparación.
- Solicitudes propias.
- Productos agotados.

### Director técnico

- Solicitudes pendientes.
- Productos agotados.
- Ajustes y bajas.
- Equipos no operativos o no localizados.
- Actividad reciente.

### Superadministrador

- Usuarios.
- Errores técnicos.
- Estado de respaldos.
- Fallos de correo.
- Auditoría destacada.

Alertas de stock:

- El estado normal cambia a agotado al llegar a `0`.
- Se notifica una vez al Director técnico.
- No se repite mientras continúe en `0`.
- Se registra recuperación cuando vuelve a ser mayor que `0`.

## 18. Reportes y exportaciones

Todos los reportes incluirán:

- Logo.
- Nombre institucional.
- Sede.
- Título.
- Fecha y hora.
- Usuario generador.
- Filtros aplicados.
- Paginación.
- Identificador interno.

El formato institucional final está por definir. No existe un formato obligatorio de numeración para las actas.

Reportes mínimos:

- Stock por bodega.
- Stock consolidado.
- Productos agotados.
- Valorización neta.
- Movimientos por periodo y tipo.
- Entregas por receptor.
- Bajas de stock.
- Activos por sede, ubicación, tipo y estado.
- Activos no localizados.
- Ficha técnica de PC.
- Componentes actuales e historial.
- Traslados de activos.
- Equipos en reparación.
- Solicitudes de baja.
- Activos dados de baja.
- Operaciones por usuario.
- Importaciones y errores.
- Bitácora.

Formatos:

- PDF institucional.
- Excel.
- CSV técnico cuando sea útil para importación o interoperabilidad.

Las exportaciones grandes se procesan en cola.

## 19. Notificaciones por correo

Buzón recomendado:

```text
inventario@institucion.cl
```

La dirección final está por definir.

Eventos iniciales:

- Nueva solicitud de baja.
- Baja aprobada o rechazada.
- Producto agotado.
- Importación completada con errores.
- Respaldo fallido.
- Error crítico persistente.

Los correos se envían en cola. Un fallo de correo no revierte una operación de inventario ya confirmada.

## 20. Auditoría y archivo

La bitácora registra:

- Usuario.
- Acción.
- Tipo e identificador de entidad.
- Valores anteriores y nuevos.
- Motivo.
- IP.
- Agente del navegador.
- Correlación de operación.
- Resultado.
- Fecha en UTC.

No registra contraseñas, tokens ni secretos.

Política preliminar:

- Últimos 12 meses visibles por defecto.
- Registros anteriores archivados lógicamente y consultables por Director técnico y Superadministrador.
- Exportación anual cifrada cuando exista destino externo.
- Sin eliminación automática hasta contar con respaldo externo verificado y política aprobada.

No se usará otra base de datos ni particionamiento inicialmente.

## 21. Respaldos y recuperación

La aplicación debe quedar preparada para un segundo destino aunque todavía no exista.

Requisitos:

- Respaldo diario de MySQL.
- Respaldo antes de actualizaciones.
- Directorio local configurable.
- Destino externo configurable para NAS, SMB/NFS, segundo servidor o almacenamiento S3 compatible.
- Cifrado.
- Verificación automática.
- Registro de resultados.
- Notificación de fallos.
- Procedimiento documentado de restauración.
- Prueba periódica de restauración.

Una copia en el mismo disco no se considera respaldo suficiente para producción.

Política preliminar sugerida:

- 7 diarias.
- 4 semanales.
- 12 mensuales.
- RPO inicial: 24 horas.
- RTO inicial: 4 horas.

## 22. Insumos externos pendientes

Estas definiciones no bloquean el DER ni la implementación inicial, pero deben completarse antes de producción:

1. Catálogo inicial exacto de categorías, bodegas, salas y tipos de activo.
2. Dirección remitente y mecanismo de correo institucional.
3. Logo definitivo y datos de encabezado institucional.
4. Nombre DNS interno y estrategia de certificado HTTPS.
5. Destino externo inicial para respaldos antes de producción.

Decisiones cerradas por simplicidad:

- El número de parte no es único por sí solo; la identidad interna depende de `codigo_interno`.
- El funcionario receptor exige nombre y correo; departamento es opcional.
- Una asignación a funcionario puede tener fecha esperada de devolución opcional.
- Una salida fungible no exige firma del receptor en la primera versión.
- Toda baja de stock aprobada genera un único comprobante PDF agrupado.
- La autoaprobación excepcional del Superadministrador se permite tanto para activos como para stock, siempre con auditoría reforzada.
- El código institucional escaneable de un activo es opcional, único y de 13 dígitos cuando existe; el activo fijo es obligatorio.

## 23. Casos límite obligatorios

### Stock

- Dos usuarios intentan retirar las últimas unidades simultáneamente.
- Doble clic o doble lectura.
- Salida mayor al disponible.
- Traslado hacia la misma bodega.
- Reversión que ya no puede aplicarse por cambios posteriores.
- Producto inactivo con stock.
- Bodega inactiva con existencias.
- Devolución superior a la salida original.
- Baja de producto ya reservado en otro borrador.

### Escaneo

- Código vacío, demasiado largo o con caracteres no numéricos.
- Espacios y caracteres de control.
- Ceros iniciales.
- Código desconocido.
- Código duplicado.
- Lector sin `ENTER`.
- Lectura mientras el usuario escribe en otro campo.

### Activos

- Activo fijo duplicado.
- Activo dado de baja que vuelve a escanearse.
- Traslado concurrente.
- Componente instalado en dos PC.
- PC sin procesador informado.
- Activo no localizado encontrado en otra sede o sala.
- Solicitud de baja duplicada.
- Costo ausente al aprobar.
- Costo exactamente igual a $200.000, que no debe generar acta PDF.
- Autoaprobación excepcional del Superadministrador.

### Usuarios

- Usuario desactivado durante una sesión.
- Cambio de rol durante una sesión.
- Intento de desactivar al último Superadministrador.
- Sesión expirada durante un borrador.
- Invitado invocando directamente una ruta de escritura.

### Operación

- Base de datos no disponible.
- Fallo de correo.
- Fallo de generación de PDF.
- Respaldo fallido.
- Migración interrumpida.
- Disco lleno.
- Pérdida de red antes o después de publicar.

## 24. Experiencia de usuario

Se reutiliza el sistema visual institucional de referencia:

- Topbar de 70 px.
- Sidebar de 248 px en escritorio y drawer bajo 992 px.
- Temas claro y oscuro.
- Controles de al menos 44 px.
- Foco visible.
- Navegación completa por teclado.
- Etiquetas visibles.
- Color nunca como único indicador.
- Tablas con paginación en servidor.
- Drawers para detalles breves y páginas completas para operaciones complejas.
- Estados de carga, vacío, error, advertencia y éxito.
- Fechas `es-CL`, hora de 24 horas y visualización en `America/Santiago`.
- Persistencia en UTC.

Pantallas mínimas:

- Inicio de sesión.
- Dashboard.
- Escaneo global.
- Productos y detalle.
- Entradas, salidas, devoluciones, traslados, ajustes y bajas.
- Movimientos e historial.
- Activos y detalle.
- Ficha técnica de PC.
- Componentes.
- Asignación y traslado.
- Reparación.
- Solicitudes de baja.
- Sedes, bodegas, salas y ubicaciones.
- Usuarios.
- Reportes.
- Importaciones.
- Bitácora.
- Configuración.

## 25. Importación inicial

La carga manual no es suficiente para más de 10.000 activos.

El importador debe incluir:

- Plantilla Excel/CSV versionada.
- Vista previa.
- Validación sin guardar.
- Errores por fila y campo.
- Detección de duplicados.
- Confirmación.
- Importación transaccional por lotes.
- Informe final.
- Archivo descargable con rechazados.
- Identificador y bitácora de importación.

Los datos de demostración deben estar separados y nunca cargarse automáticamente en producción.

## 26. Pruebas obligatorias

### Unitarias

- Normalización de códigos.
- Permisos.
- Estados y transiciones.
- Reglas de movimientos.
- Umbral PDF de $200.000.
- Bajas y autoaprobación excepcional.

### Integración

- Transacciones MySQL.
- Bloqueos concurrentes.
- Unicidad.
- Publicación y reversión.
- Auditoría.
- Colas, correos y PDF.
- Importaciones.

### Funcionales

- Acceso y sesión.
- Cada rol.
- Escaneo.
- Entrada, salida, devolución, traslado, ajuste y baja.
- Activos y componentes.
- Solicitudes y aprobaciones.
- Reportes y exportaciones.

### Operación

- Construcción de contenedores.
- Migraciones desde cero.
- Respaldo.
- Restauración en ambiente vacío.
- Actualización sin exposición de secretos.

## 27. Fases de implementación

1. Cerrar decisiones restantes.
2. Diseñar DER y diccionario de datos.
3. Definir matriz de permisos y transiciones.
4. Diseñar wireframes y flujos de escaneo.
5. Crear infraestructura Docker y proyecto Laravel.
6. Implementar autenticación local, roles y shell visual.
7. Implementar sedes, ubicaciones, bodegas y salas.
8. Implementar productos, existencias y movimientos.
9. Implementar activos, asignaciones, reparación y componentes.
10. Implementar bajas y actas PDF.
11. Implementar dashboard, reportes y Excel.
12. Implementar importaciones, correo y auditoría.
13. Endurecer seguridad, concurrencia, respaldos y restauración.
14. Ejecutar piloto y corregir.

## 28. Exclusiones deliberadas

- Compras, proveedores y facturas.
- Depreciación contable.
- ERP.
- Aplicación móvil nativa.
- PWA offline.
- Firma electrónica.
- Impresión de etiquetas.
- Microsoft Entra ID en la primera versión.
- Sincronización Microsoft 365.
- Motor dinámico de permisos.
- Múltiples niveles de aprobación.
- Módulo avanzado de mantenimiento.
- Microservicios.
- Kubernetes.
- Redis, Elasticsearch o infraestructura distribuida.
- Inteligencia artificial dentro del producto.
