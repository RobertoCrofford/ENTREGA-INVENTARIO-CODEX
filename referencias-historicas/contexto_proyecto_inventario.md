# Proyecto: Sistema Web de Inventario Institucional

## Objetivo

Crear una plataforma web para una institución académica que permita:

- Gestionar inventario de bodega.
- Registrar y controlar PCs/equipos ubicados en salas.
- Identificar equipos mediante código de barras usando una pistola lectora.
- Consultar información mediante un dashboard.
- Registrar movimientos e historial de cambios.

## Tecnologías

- Web
- Bootstrap
- Arquitectura MVC
- MySQL
- XAMPP
- Pistola lectora de código de barras USB

La pistola normalmente funciona como teclado: escribe el código leído en un campo del navegador y puede enviar ENTER.

## Base de datos actual

La versión actual tiene 12 tablas:

1. `roles`
2. `usuarios`
3. `salas`
4. `estados_equipo`
5. `equipos`
6. `salas_equipos`
7. `historial_traslados`
8. `ubicaciones`
9. `categorias`
10. `inventario_items`
11. `movimientos_inventario`
12. `bitacora`

Se eliminaron deliberadamente:

- `unidades_medida`
- `proveedores`
- `compras`
- `detalle_compra`

## Relaciones principales

- `roles` 1:N `usuarios`
- `usuarios` 1:N `bitacora`
- `usuarios` 1:N `movimientos_inventario`
- `usuarios` 1:N `salas_equipos`
- `usuarios` 1:N `historial_traslados`
- `estados_equipo` 1:N `equipos`
- `salas` 1:N `salas_equipos`
- `equipos` 1:1 en asignación actual mediante `salas_equipos`
- `equipos` 1:N `historial_traslados`
- `categorias` 1:N `inventario_items`
- `ubicaciones` 1:N `inventario_items`
- `inventario_items` 1:N `movimientos_inventario`
- `ubicaciones` 1:N `movimientos_inventario` como origen/destino

Importante: `salas_equipos` tiene `UNIQUE(id_equipo)`, por lo que un equipo solo puede tener una asignación actual. El historial de cambios de sala queda en `historial_traslados`.

## Identificadores de equipos

Se decidió mantener separados:

| Campo | Propósito |
|---|---|
| `id_equipo` | Identificador interno de la BD |
| `activo_fijo` | Identificador oficial de la institución |
| `codigo_barras` | Código leído por la pistola |
| `numero_serie` | Identificador físico del fabricante |

Ejemplo:

```text
id_equipo:     125
activo_fijo:   AF-001235
codigo_barras: 7891234567890
numero_serie:  DELL12345
```

El código de barras NO reemplaza al activo fijo. El activo fijo representa la identidad institucional; el código de barras es el medio rápido de lectura. Esto permite cambiar la etiqueta de código de barras sin cambiar la identidad del activo.

## Funcionamiento del escaneo

Flujo para un PC:

```text
Pistola
  ↓
codigo_barras
  ↓
Formulario web
  ↓
Controller MVC
  ↓
Model
  ↓
MySQL / equipos
  ↓
Buscar equipo
  ↓
Mostrar información
  ↓
Seleccionar/confirmar sala
  ↓
salas_equipos
  ↓
Si cambia de sala → historial_traslados
  ↓
bitacora
```

La pistola no se conecta directamente a MySQL.

Ejemplo de búsqueda:

```sql
SELECT
    e.id_equipo,
    e.activo_fijo,
    e.codigo_barras,
    e.tipo_equipo,
    e.marca,
    e.modelo,
    e.numero_serie,
    ee.nombre AS estado
FROM equipos e
INNER JOIN estados_equipo ee
    ON e.id_estado_equipo = ee.id_estado_equipo
WHERE e.codigo_barras = '7891234567890';
```

## Funcionamiento de bodega

```text
Escanear código
  ↓
inventario_items
  ↓
¿Existe?
  ├─ No → Registrar producto
  └─ Sí → Mostrar producto
             ↓
        Entrada/Salida/Traslado/Ajuste
             ↓
        movimientos_inventario
             ↓
        Actualizar stock
             ↓
        bitacora
```

## Dashboard propuesto

Indicadores principales:

- Cantidad de productos de bodega.
- Cantidad de PCs/equipos.
- Cantidad de salas.
- Alertas.
- Stock.
- Equipos por estado.
- Últimos movimientos.
- Consultas/reportes.

## Roles iniciales

- Administrador: acceso completo.
- Encargado Bodega: gestión del inventario.
- Técnico: registro y control de equipos.
- Consulta: lectura y consultas.

## Casos de uso principales

### Administrador
- Iniciar sesión.
- Administrar usuarios.
- Administrar salas.
- Administrar categorías.
- Consultar información.

### Encargado de Bodega
- Gestionar inventario.
- Escanear productos.
- Registrar entradas.
- Registrar salidas.
- Consultar stock.

### Técnico
- Registrar equipos.
- Escanear activo/código de barras.
- Asignar equipos a salas.
- Trasladar equipos.
- Consultar equipos.

### Usuario Consulta
- Consultar dashboard.
- Consultar inventario.
- Consultar PCs por sala.
- Generar/consultar reportes.

## Caso de uso principal: registrar/asignar PC

1. Técnico inicia sesión.
2. Entra a Equipos/Salas.
3. Selecciona sala.
4. Escanea código de barras.
5. Sistema busca equipo.
6. Si existe, muestra sus datos.
7. Si no existe, permite registrar el equipo.
8. Se confirma la sala.
9. Se registra en `salas_equipos`.
10. Si ya estaba en otra sala, se registra el traslado en `historial_traslados`.
11. Se registra la operación en `bitacora`.

## Próximo paso

Crear el **DER definitivo** usando exactamente las 12 tablas actuales, mostrando:

- PK
- FK
- Cardinalidades
- Relaciones entre tablas

Después avanzar a la arquitectura MVC y la implementación por módulos:

`Login → Dashboard → Usuarios/Roles → Salas → Equipos → Escaneo → Asignación/Traslados → Inventario Bodega → Movimientos → Reportes`

## Criterio de diseño

Mantener el proyecto simple, funcional y sin sobreingeniería. Priorizar el flujo real de trabajo de la institución y construir primero la base de datos, DER y casos de uso antes de desarrollar la aplicación.
