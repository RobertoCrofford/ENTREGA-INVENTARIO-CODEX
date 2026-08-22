# Sistema de diseño UI/UX — Inventario Institucional

## 1. Propósito

Este documento define la identidad visual, estructura, comportamiento y reglas de experiencia de usuario del Sistema de Inventario Institucional. Debe utilizarse junto con el plan maestro y los flujos vigentes.

La interfaz transmite una aplicación institucional, operativa y confiable. Su apariencia combina:

- Base clara azul marino, blanco y gris frío.
- Acento amarillo institucional usado con moderación.
- Modo oscuro de fondo negro-púrpura con superficies ciruela y acento violeta.
- Contenedores compactos, bordes discretos y sombras suaves.
- Alta densidad informativa sin sacrificar legibilidad.
- Bootstrap 5 como base de controles e iconografía Bootstrap Icons.

No se debe convertir en una interfaz ornamental, con gradientes intensos, sombras grandes, tarjetas excesivamente redondeadas o animaciones protagonistas.

---

## 2. Principios de experiencia

### 2.1 Claridad operativa

Cada pantalla debe responder inmediatamente tres preguntas:

1. ¿Dónde estoy?
2. ¿Qué información estoy consultando?
3. ¿Qué acción puedo realizar ahora?

### 2.2 Jerarquía estable

La aplicación conserva siempre esta jerarquía:

1. Barra superior institucional.
2. Navegación lateral.
3. Encabezado de página.
4. Filtros o controles de contexto.
5. Contenido principal.
6. Detalle secundario en drawer lateral.

### 2.3 Densidad progresiva

Cuando falta espacio, se reduce la información secundaria antes de modificar la estructura principal. Nunca se permite que el texto salga de su bloque o se superponga.

### 2.4 Acciones seguras

- Las acciones destructivas o reversibles se distinguen visualmente.
- Los conflictos deben explicarse, no representarse solamente con color.
- Las correcciones y datos originales deben conservar trazabilidad.
- La aplicación no debe depender de hover para revelar información necesaria.

### 2.5 Accesibilidad

- Objetivos interactivos de al menos `44 × 44 px`.
- Foco visible amarillo de `3 px` con separación de `2 px`.
- Navegación completa mediante teclado.
- Enlace para saltar al contenido principal.
- Etiquetas visibles en formularios.
- Iconos acompañados por texto o nombre accesible.
- Respeto por `prefers-reduced-motion`.
- El color nunca es el único indicador de error, conflicto o selección.

---

## 3. Tecnología visual de referencia

- Tipografía: `Segoe UI`, con respaldo `Arial, sans-serif`.
- Componentes base: Bootstrap 5.3.
- Iconos: Bootstrap Icons.
- Tema: atributo `data-bs-theme="light|dark"` en el elemento `html`.
- Preferencia: guardar en `localStorage`; si no existe, respetar `prefers-color-scheme`.

El diseño puede implementarse con otra tecnología, pero debe conservar los tokens, proporciones, estados y prioridades descritos aquí.

---

## 4. Tokens de color

### 4.1 Tokens semánticos recomendados

```css
:root {
  --app-bg: #f3f6f9;
  --surface: #ffffff;
  --surface-raised: #ffffff;
  --surface-hover: #f1f6fa;
  --border-color: #dfe6ed;

  --text-primary: #172333;
  --text-secondary: #66788a;

  --brand-primary: #0b2d4d;
  --brand-secondary: #1565a8;
  --brand-accent: #f4c542;
  --focus-ring: #f4c542;

  --danger: #b42318;
  --warning: #8a6100;
  --success: #21834e;

  --history-grid: #e3e9ee;
  --history-event-bg: #dcecf8;
  --history-event-border: #0f568e;
}

[data-bs-theme="dark"] {
  --app-bg: #0d0812;
  --surface: #181020;
  --surface-raised: #21172b;
  --surface-hover: #2a1e35;
  --border-color: #392a43;

  --text-primary: #f8f3fa;
  --text-secondary: #bdafc3;

  --brand-primary: #c260ea;
  --brand-secondary: #c260ea;
  --brand-accent: #f4b942;
  --focus-ring: #f4b942;

  --danger: #ff6b7a;
  --warning: #f4b942;
  --success: #70d6a0;

  --history-grid: #392a43;
  --history-event-bg: #382148;
  --history-event-border: #c260ea;
}
```

### 4.2 Paleta clara extendida

| Uso | Color |
|---|---:|
| Fondo general | `#f3f6f9` |
| Superficie/sidebar | `#ffffff` / `#f8fafc` |
| Azul marino institucional | `#0b2d4d` |
| Azul secundario/acciones | `#1565a8` |
| Amarillo institucional | `#f4c542` |
| Texto principal | `#172333` |
| Texto secundario | `#66788a` |
| Bordes | `#dfe6ed` |
| Hover de superficie | `#f1f6fa` |
| Selección lateral | `#e6eff7` |
| Fondo de evento | `#dcecf8` |
| Borde de evento | `#0f568e` |
| Día actual | `#fff9df` |
| Conflicto, fondo | `#fde6e3` |
| Conflicto, texto | `#68221b` |
| Corrección, fondo | `#e9f2fa` |
| Éxito | `#21834e` |
| Línea de hora actual | `#d73535` |

### 4.3 Paleta oscura extendida

| Uso | Color |
|---|---:|
| Fondo general | `#0d0812` |
| Superficie principal | `#181020` |
| Superficie elevada | `#21172b` |
| Hover | `#2a1e35` |
| Bordes | `#392a43` |
| Texto principal | `#f8f3fa` |
| Texto secundario | `#bdafc3` |
| Violeta de marca | `#c260ea` |
| Violeta activo oscuro | `#6e3488` |
| Texto violeta claro | `#e3b6f4` |
| Amarillo de acento | `#f4b942` |
| Evento | `#382148` |
| Evento hover | `#47285b` |
| Conflicto, fondo | `#49212c` |
| Error elevado | `#451f2c` |
| Advertencia elevada | `#352c1d` |
| Corrección elevada | `#2d2442` |

### 4.4 Reglas de aplicación

- El azul marino domina la identidad clara; el violeta domina la identidad oscura.
- El amarillo se reserva para foco, selección, pequeños acentos y advertencias.
- No usar amarillo como fondo de áreas grandes.
- Las superficies oscuras no son negro puro y mantienen una progresión visible entre fondo, tarjeta y hover.
- Los estados tienen texto, icono y tratamiento de superficie; no solo un color.

---

## 5. Tipografía

### 5.1 Familia

```css
font-family: "Segoe UI", Arial, sans-serif;
```

### 5.2 Escala

| Elemento | Tamaño | Peso | Observación |
|---|---:|---:|---|
| Título de página | `1.72rem` | `750` | Tracking `-0.025em` |
| Título móvil | `1.4rem` | `750` | Bajo `640 px` |
| Título de drawer | `1.25rem` | `700` | Compacto |
| Título de tarjeta | `1–1.1rem` | `700` | Según jerarquía |
| Texto de introducción | `0.9rem` | `400` | Color secundario |
| Navegación | `0.9rem` | `600` | Altura mínima 45 px |
| Controles | `0.78–0.84rem` | `400–700` | Según función |
| Etiquetas | `0.67–0.72rem` | `700–800` | Claras y compactas |
| Eyebrow | `0.67rem` | `800` | Mayúsculas, tracking `0.12em` |
| Metadato | `0.6–0.7rem` | `400–700` | Nunca reemplaza datos primarios |

Los encabezados usan frases breves. Las etiquetas de categoría pueden ir en mayúsculas; los textos normales no.

---

## 6. Espaciado, formas y elevación

### 6.1 Escala de espaciado

Usar una cuadrícula base de `4 px`:

```text
4, 8, 12, 16, 20, 24, 28, 32, 40 px
```

- Separación habitual entre controles: `12–16 px`.
- Padding de tarjeta: `15–20 px`.
- Separación entre secciones: `16–20 px`.
- Padding horizontal del contenido desktop: `28 px`.
- Padding horizontal móvil: `10–16 px`.

### 6.2 Radios

| Elemento | Radio |
|---|---:|
| Tarjetas principales | `10 px` |
| Botones y controles agrupados | `7–8 px` |
| Etiquetas y eventos de historial | `4 px` |
| Botones compactos auxiliares | `5 px` |
| Avatares e indicadores | `50%` |

No utilizar estilo pill salvo en contadores o etiquetas de estado pequeñas.

### 6.3 Bordes y sombras

```css
/* Tarjeta clara */
border: 1px solid var(--border-color);
box-shadow: 0 2px 7px rgba(25, 49, 72, 0.04);

/* Tarjeta oscura */
box-shadow: 0 3px 12px rgba(0, 0, 0, 0.18);

/* Overlay elevado */
box-shadow: 0 12px 28px rgba(17, 43, 65, 0.16);
```

Las sombras son auxiliares; la separación principal se obtiene mediante contraste de superficie y borde.

---

## 7. Estructura global

### 7.1 Barra superior

- Fija en la parte superior.
- Altura: `70 px`.
- Padding horizontal desktop: `24 px`.
- Fondo claro: gradiente sutil `#0b2d4d → #092642`.
- Fondo oscuro: gradiente `#181020 → #0d0812`.
- Logo a la izquierda, altura desktop `40 px`, móvil `32 px`.
- Acciones a la derecha, controles de `44 px` de alto.
- Estado del entorno representado por punto verde, texto y separador.
- En móvil se ocultan los metadatos secundarios y el selector de tema conserva solo el icono.

No deformar ni encerrar el logo en una placa blanca si el archivo ya posee fondo transparente o apropiado.

### 7.2 Navegación lateral

- Fija bajo el header.
- Ancho desktop: `248 px`.
- Padding: `22 px 14 px`.
- Cada opción mide al menos `45 px` de alto.
- Icono, etiqueta y contador opcional.
- Activo: fondo tenue y barra interior izquierda amarilla de `3 px`.
- Ayuda contextual anclada al final.

En pantallas menores a `992 px` se transforma en drawer lateral de hasta `300 px` o `82vw`, con backdrop.

### 7.3 Contenido principal

```css
margin-left: 248px;
padding: 100px 28px 42px;
min-height: 100vh;
```

En tablet/móvil desaparece el margen lateral y el padding pasa a `92px 16px 30px`; bajo `640 px`, el padding horizontal es `10 px`.

### 7.4 Encabezado de página

Contiene:

- Eyebrow contextual.
- Título descriptivo.
- Frase corta de ayuda.
- Resumen contextual alineado a la derecha cuando corresponda.

Los resúmenes secundarios se ocultan bajo `992 px` para priorizar el contenido principal.

---

## 8. Componentes

### 8.1 Tarjetas

Las variantes `filter`, `history`, `panel` y `state` comparten superficie, borde, radio y sombra. La diferencia debe provenir de su estructura interna, no de estilos visuales incompatibles.

### 8.2 Formularios

- Altura mínima: `44 px`.
- Etiqueta visible encima del control.
- Separación etiqueta/control: `6 px`.
- Borde claro: `#cbd6df`.
- En modo oscuro: superficie elevada y borde `#4b3857`.
- Foco oscuro: borde violeta y halo `rgba(194,96,234,.24)`.
- Los placeholders son secundarios; nunca sustituyen a la etiqueta.

### 8.3 Botones

- Primario claro: azul `#1565a8`; hover `#0e548f`.
- Primario oscuro: violeta `#8a42aa`; hover `#a44ec9`.
- Secundarios: outline neutro.
- Destructivos: outline rojo salvo confirmación de alto riesgo.
- Icono a la izquierda cuando mejora el reconocimiento.
- Evitar más de una acción primaria por región.

### 8.4 Controles segmentados

Los selectores de modo y vista usan dos opciones dentro de un borde compartido:

- Altura: `44 px`.
- Radio exterior: `7 px`.
- Separador de `1 px`.
- Estado activo con fondo de marca y texto blanco.
- Estado inactivo con superficie neutra y texto secundario.

### 8.5 Autocompletado

- Resultados alineados exactamente al ancho del input.
- Máximo `286 px` de alto, con scroll.
- Cada opción mide al menos `44 px`.
- Nombre a la izquierda y metadato a la derecha.
- Estado vacío explícito.
- Operable con teclado y roles `combobox`, `listbox` y `option`.

### 8.6 Listados

- Filas separadas por una línea sutil.
- Altura cómoda, sin tarjetas individuales para cada fila.
- Avatar circular de `38 px` cuando representa personas.
- Título, metadato y affordance de navegación.
- Hover solo refuerza que la fila es interactiva.

### 8.7 Estados vacíos y errores

- Icono circular de `56 px`.
- Mensaje conciso y accionable.
- Acción directa cuando existe recuperación.
- Altura mínima de contenido vacío: `330 px` dentro de paneles de consulta amplios.
- Advertencia y error usan icono, título y texto; nunca solo un banner cromático.

### 8.8 Drawer de detalle

- Aparece desde la derecha.
- Ancho máximo: `500 px`; en móvil ocupa el ancho completo.
- Altura: `100dvh`.
- Header y footer fijos; cuerpo interno desplazable.
- Backdrop claro: `rgba(8,28,46,.36)`.
- Backdrop oscuro: `rgba(4,2,7,.76)`.
- Entrada breve de `200 ms`.
- Foco inicial en cerrar, cierre con `Escape` y retención lógica del contexto.
- Los datos se presentan como etiqueta pequeña en mayúsculas y valor prominente.

### 8.9 Carga de archivos

- Tarjeta centrada con icono de `58 px`.
- Zona de archivo con borde discontinuo, no una caja genérica.
- Mostrar nombre y peso del archivo seleccionado.
- Botón primario de ancho completo.
- Nota de privacidad visible.
- El mapeo manual utiliza una superficie amarilla tenue.

---

## 9. Responsive

### 9.1 Desktop: más de 1100 px

- Sidebar fija de `248 px`.
- Filtros en cuatro columnas.
- Metadatos completos en header.

### 9.2 Tablet: 641–1100 px

- Bajo `1100 px`, filtros en dos columnas e importaciones en una columna.
- Bajo `992 px`, sidebar convertida en drawer.
- Contenido sin margen lateral.
- Metadatos secundarios ocultos cuando compitan con los filtros y acciones esenciales.

### 9.3 Móvil: hasta 640 px

- Filtros en una columna.
- Header con padding reducido.
- Logo de `32 px` de alto.
- Selector de tema solo con icono.
- Grillas de dos columnas pasan a una.
- Drawer ocupa todo el ancho.

### 9.4 Evitar

- Reducir toda la aplicación mediante `transform: scale()`.
- Texto fuera de tarjetas, tablas o paneles.
- Controles menores a `44 px`.
- Sidebar fija consumiendo espacio en móvil.
- Reordenar acciones de forma impredecible entre breakpoints.

---

## 10. Iconografía

Usar Bootstrap Icons con trazo consistente. Mapeo recomendado:

| Concepto | Icono |
|---|---|
| Historial | `bi-clock-history` |
| Usuarios o responsables | `bi-people` / `bi-person` |
| Sala | `bi-door-open` |
| Conflictos | `bi-exclamation-diamond` |
| Importación | `bi-cloud-arrow-up` |
| Categorías o versiones | `bi-layers` |
| Corrección | `bi-pencil-fill` |
| Seguridad | `bi-shield-check` / `bi-lock-fill` |
| Búsqueda | `bi-search` |
| Tema claro | `bi-sun-fill` |
| Tema oscuro | `bi-moon-stars-fill` |

No mezclar familias de iconos con pesos visuales incompatibles.

---

## 11. Movimiento

- Transiciones de navegación y drawers: `200 ms`.
- Curva recomendada: `ease-out` para entrada y `ease` para cambios de color.
- No animar datos operativos al punto de dificultar su lectura.
- Con `prefers-reduced-motion: reduce`, reducir animaciones y transiciones a `0.01 ms`.

---

## 12. Voz y contenido

- Español claro, directo y profesional.
- Títulos orientados a la tarea: “Stock por bodega”, “Importaciones”.
- Ayudas de una frase, sin lenguaje promocional.
- Estados vacíos explican la causa y el siguiente paso.
- Errores indican qué falló y cómo recuperarse.
- Fechas con locale `es-CL`.
- Horas en formato de 24 horas.
- Usar raya `—` para valores ausentes y no inventar contenido.
- Las salas se muestran por su código, sin expandir nombres no solicitados.

---

## 13. Plantilla de composición de una pantalla

```text
AppShell
├─ Topbar
│  ├─ Menú móvil
│  ├─ Logo
│  ├─ Selector de tema
│  └─ Estado/contexto
├─ Sidebar / Drawer de navegación
│  ├─ Etiqueta de sección
│  ├─ Navegación
│  └─ Ayuda contextual
└─ Main
   ├─ Banner operativo opcional
   ├─ PageHeading
   ├─ FilterCard opcional
   ├─ Estado de carga/error/vacío
   └─ Contenido principal
      └─ Drawer de detalle opcional
```

---

## 14. Checklist de fidelidad

Antes de considerar replicada la interfaz, verificar:

- [ ] Existen temas claro y oscuro completos, sin componentes aislados con colores incorrectos.
- [ ] El header mide `70 px` y conserva la identidad institucional.
- [ ] La sidebar mide `248 px` en desktop y se convierte en drawer bajo `992 px`.
- [ ] Los controles interactivos tienen al menos `44 px`.
- [ ] El foco de teclado es visible en todas las acciones.
- [ ] Tarjetas, bordes, radios y sombras respetan los tokens.
- [ ] Los encabezados de página mantienen eyebrow, título y ayuda.
- [ ] Los formularios tienen etiquetas visibles.
- [ ] Carga, vacío, error, advertencia y éxito tienen diseños definidos.
- [ ] Las tablas y paneles operativos no permiten desbordes de texto.
- [ ] Los datos primarios conservan prioridad en pantallas pequeñas.
- [ ] El drawer es usable mediante teclado y en pantallas pequeñas.
- [ ] El modo oscuro usa superficies púrpura, no una simple inversión de colores.
- [ ] La interfaz respeta reducción de movimiento.
- [ ] Las salas se presentan por código.

---

## 15. Regla de mantenimiento

Todo componente nuevo debe construirse primero con tokens semánticos y patrones existentes. Solo se agrega un color, radio, sombra o breakpoint nuevo cuando ninguno de los definidos resuelve el caso. Si se agrega, debe documentarse aquí y funcionar en ambos temas.
