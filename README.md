# Inventario Institucional

Aplicación Laravel 13 para inventario institucional. Esta fase contiene únicamente la fundación técnica; los módulos de negocio se implementan en las fases posteriores descritas en `prompts/`.

## Requisitos locales

- Docker Desktop con backend Linux/WSL 2 iniciado.
- Puerto `8080` disponible, o un valor alternativo en `APP_PORT` dentro de `.env`.

No se requiere PHP, Composer ni Node instalados en Windows: se ejecutan dentro de contenedores. Los recursos web se compilan automáticamente al levantar el proyecto; `public/build` no se sube a Git.

## Inicio local

1. Copiar `.env.example` a `.env` si no existe y reemplazar las contraseñas de desarrollo si corresponde.
2. Generar una clave si el archivo fue creado manualmente:

   ```powershell
   docker compose run --rm app php artisan key:generate
   ```

3. Construir e iniciar:

   ```powershell
   docker compose up --build -d
   ```

4. Comprobar el estado y la aplicación:

   ```powershell
   docker compose ps
   Invoke-WebRequest http://localhost:8080/up
   ```

La aplicación queda expuesta solo mediante el proxy en `http://localhost:8080`. MySQL pertenece a la red interna de Docker y no publica puertos al host.

## Primera cuenta en un servidor nuevo

Al iniciar una base de datos vacía, el proyecto prepara automáticamente los roles, las bodegas y los catálogos necesarios. Luego abre la dirección del sistema y, en la pantalla de acceso, selecciona **Configurar primera cuenta**. Completa los datos solicitados y se creará el primer **superadministrador** desde el navegador.

La opción desaparece en forma automática apenas se guarda la primera cuenta. Desde ese momento, los demás usuarios se crean en **Usuarios** con una cuenta superadministradora; no se necesita usar consola ni ChatGPT.

Antes de publicar el servidor, define en su archivo `.env` un `ADMIN_RECOVERY_CODE` largo, único y guardado en un lugar seguro. Si alguna vez quedaran usuarios pero ningún superadministrador, el acceso mostrará **Recuperar administración**; al ingresar ese código se podrá crear un nuevo superadministrador desde el navegador. El código nunca se muestra en la aplicación.

## Operación habitual

```powershell
# Registros de servicios
docker compose logs -f proxy app queue scheduler

# Pruebas aisladas (SQLite en memoria; nunca usa la base local)
docker compose --profile tools run --rm test

# Formato
docker compose run --rm app ./vendor/bin/pint --test

# Reconstruir recursos frontend
docker compose --profile tools run --rm frontend

# Detener servicios sin borrar datos
docker compose down
```

Para reiniciar desde cero en desarrollo se deben detener los servicios y eliminar explícitamente los volúmenes del proyecto. No ejecutar esa operación sobre datos que se deban conservar.

## Servicios

| Servicio | Función |
|---|---|
| `proxy` | Nginx; único puerto expuesto al host. |
| `app` | PHP-FPM con Laravel y PHP 8.4. |
| `database` | MySQL 8.4 con volumen persistente. |
| `migrate` | Ejecuta las migraciones técnicas una vez por inicio. |
| `queue` | Procesa colas con driver de base de datos. |
| `scheduler` | Ejecuta el planificador Laravel. |
| `backup` | Genera un dump verificado diario en un volumen persistente de Docker. |

Los valores de `.env.example` son exclusivos para desarrollo. Producción requiere secretos propios, HTTPS configurado en el proxy, correo institucional y un destino externo cifrado y verificado para respaldos.

## Publicación segura en servidor

1. Copia `.env.production.example` como `.env` en el servidor. Nunca copies el `.env` local ni subas ese archivo a Git.
2. Genera `APP_KEY` con `docker compose run --rm app php artisan key:generate`, reemplaza todas las claves `CAMBIAR...` por secretos únicos y guarda `ADMIN_RECOVERY_CODE` fuera del servidor.
3. Publica el sitio detrás de un proxy HTTPS (por ejemplo, Nginx, Caddy o el servicio del proveedor). El proxy debe redirigir HTTP a HTTPS y entregar el tráfico a este proyecto por la red privada; no expongas el puerto `8080` directamente a Internet.
4. Configura el SMTP institucional y realiza una prueba de recuperación de contraseña antes de abrir el sistema a usuarios.
5. En cada actualización, ejecuta `docker compose up --build -d`, luego `docker compose exec app php artisan optimize:clear` y revisa `https://tu-dominio/up`. El contenedor reconoce los cambios de código sin conservar rutas o vistas antiguas.

Los respaldos diarios se crean de forma temporal y se publican solo después de verificarse. Se conservan en un volumen persistente de Docker, fuera del repositorio. Para producción, cifra la copia y envíala a un segundo destino con acceso restringido; conserva y prueba periódicamente una restauración.

### Despliegue desde Portainer

El archivo `docker-compose.yml` está preparado para recibir secretos desde **Environment variables** de la Stack en Portainer; no requiere ni busca un archivo `.env` en el servidor ni monta carpetas del host sobre la aplicación. Antes de pulsar **Deploy the stack**, agrega las variables indicadas en `.env.production.example`. Las obligatorias son `APP_KEY`, `ADMIN_RECOVERY_CODE`, `APP_URL`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `MYSQL_ROOT_PASSWORD`, `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD` y `MAIL_FROM_ADDRESS`.

Usa valores nuevos y privados para los secretos. Para `APP_KEY`, genera una clave de Laravel en un entorno seguro con `php artisan key:generate --show` y copia solo su resultado a Portainer. No subas `.env` ni contraseñas a GitHub.

Los errores del servidor se envían a los registros del contenedor `app` (`LOG_CHANNEL=stderr`), por lo que se pueden revisar desde **Containers → app → Logs** en Portainer sin abrir una consola.

## Restauración verificada (entorno vacío)

La restauración se realiza únicamente con servicios detenidos y sobre una base de datos de destino creada para ese fin. No ejecute este procedimiento sobre la base productiva sin una ventana de mantenimiento y una copia adicional verificada.

```powershell
# Listar y verificar el respaldo dentro del volumen Docker.
docker compose exec backup ls -1 /backups
docker compose exec backup gzip -t /backups/mysql-AAAAMMDDTHHMMSSZ.sql.gz

# Restaurar hacia la base configurada en .env.
docker compose exec -T backup sh -c 'gzip -dc /backups/mysql-AAAAMMDDTHHMMSSZ.sql.gz' |
  docker compose exec -T database sh -lc 'mysql -u root -p"$MYSQL_ROOT_PASSWORD"'
```

Después se debe iniciar la aplicación, ejecutar `php artisan migrate --force`, comprobar `/up` y registrar el resultado de la prueba de restauración. Para producción, configure además un segundo destino externo cifrado; la copia local no es suficiente.
