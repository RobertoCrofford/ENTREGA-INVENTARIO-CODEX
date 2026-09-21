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
| `backup` | Genera un dump local verificado diario en `storage/backups`. |

Los valores de `.env.example` son exclusivos para desarrollo. Producción requiere secretos propios, HTTPS configurado en el proxy, correo institucional y un destino externo cifrado y verificado para respaldos.

## Restauración verificada (entorno vacío)

La restauración se realiza únicamente con servicios detenidos y sobre una base de datos de destino creada para ese fin. No ejecute este procedimiento sobre la base productiva sin una ventana de mantenimiento y una copia adicional verificada.

```powershell
# Verificar la integridad del archivo antes de restaurar.
docker compose exec backup gzip -t /backups/mysql-AAAAMMDDTHHMMSSZ.sql.gz

# Restaurar hacia la base configurada en .env.
Get-Content .\storage\backups\mysql-AAAAMMDDTHHMMSSZ.sql.gz -Encoding Byte |
  docker compose exec -T database sh -lc 'gzip -dc | mysql -u root -p"$MYSQL_ROOT_PASSWORD"'
```

Después se debe iniciar la aplicación, ejecutar `php artisan migrate --force`, comprobar `/up` y registrar el resultado de la prueba de restauración. Para producción, configure además un segundo destino externo cifrado; la copia local no es suficiente.
