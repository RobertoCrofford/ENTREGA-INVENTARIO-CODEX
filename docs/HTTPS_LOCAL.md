# HTTPS local detrás de Caddy

## Configuración de la aplicación

Después de desplegar la versión que contiene `config/trustedproxy.php`, configurar en el stack existente `inventario-duoc`:

```dotenv
APP_PORT=5030
APP_URL=https://10.202.0.113:5443
TRUSTED_PROXIES=172.29.0.2
SESSION_SECURE_COOKIE=true
```

La IP `172.29.0.2` es la dirección interna de Caddy observada en este despliegue. Confirmarla con `hostname -i` dentro de Caddy antes de aplicar los valores. La aplicación usa la dirección del proxy inmediato que ve Nginx. Si Docker o la red cambia esa dirección, revisar el campo remote_addr en los accesos de Nginx y actualizar TRUSTED_PROXIES. No utilizar `*` ni confiar en toda la red de alumnos. Se admiten varias IP/CIDR explícitas separadas por comas.

Mantener APP_KEY, credenciales, nombre del stack, puertos existentes y volúmenes, especialmente `inventario-duoc_mysql_data`. Generar un respaldo antes de actualizar. No ejecutar `down -v`.

Recrear los contenedores de aplicación para cargar código y variables. Si existe una configuración cacheada, ejecutar dentro de app:

```sh
php artisan config:clear
```

Solo se confía en X-Forwarded-Proto desde los proxies configurados. X-Forwarded-Host, X-Forwarded-Port y X-Forwarded-For no controlan host, puerto ni IP de cliente. No se fuerza HTTPS globalmente en las solicitudes HTTP directas. Las cookies Secure requieren que los usuarios usen HTTPS para autenticarse.

## Configuración de Caddy

El stack separado `inventario-https` conserva los volúmenes `caddy_data` y `caddy_config`. Su Caddyfile debe incluir:

```caddyfile
{
    admin off
    auto_https disable_redirects
    default_sni 10.202.0.113
}

https://10.202.0.113:5443 {
    tls internal
    reverse_proxy http://10.202.0.113:5030
}
```

El navegador no envía un nombre SNI al abrir una IP. En Docker, default_sni permite seleccionar el certificado de la IP del servidor en vez de la IP interna del contenedor. Se reprodujo el fallo TLS sin SNI y la negociación correcta al indicar la IP explícitamente; después de configurar default_sni, la negociación normal por IP funcionó con TLS 1.3.

Instalar el certificado raíz público `/data/caddy/pki/authorities/local/root.crt` de ese servidor como autoridad de confianza en cada dispositivo autorizado. No distribuir claves privadas ni omitir advertencias del navegador. Conservar el volumen de Caddy para mantener la misma autoridad raíz.

## Validación antes de dar por terminado

1. Acceder a `https://10.202.0.113:5443/mobile` sin advertencias de certificado.
2. Sin sesión, debe redirigir a `https://10.202.0.113:5443/login`, conservando esquema y puerto.
3. Comprobar que estilos, scripts, formularios y navegación permanecen en HTTPS.
4. Iniciar sesión con un usuario existente y confirmar la sesión después de recargar.
5. Permitir la cámara en un dispositivo real, consultar una etiqueta y verificar el inventario físico.
6. Comprobar que una segunda lectura del mismo equipo dentro de la misma revisión no se registra dos veces.

Pruebas automáticas: `docker compose run --rm --no-deps test`. El ajuste añadió seis pruebas de proxy. La ejecución local completa del 17-11-2026 terminó con 54 pruebas aprobadas y 261 aserciones, usando SQLite en memoria. Esto no sustituye la comprobación final del servidor y los dispositivos reales.
