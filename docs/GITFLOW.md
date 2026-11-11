# Flujo de trabajo Gitflow

Este repositorio reúne la web estable y el inventario móvil. La rama de integración se llama `desarrollo` (equivalente a `develop`).

## Ramas

- `main`: versión estable destinada a producción.
- `desarrollo`: integración y pruebas de la siguiente versión.
- `feature/<nombre>`: cambios nuevos; nacen de desarrollo y regresan a desarrollo mediante pull request.
- `release/<version>`: preparación de una entrega desde desarrollo. Al terminar las pruebas se integra a main, se crea una etiqueta de versión y se integran sus correcciones a desarrollo.
- `hotfix/<nombre>`: correcciones urgentes desde main; se integran a main y desarrollo y se etiquetan.

No publicar directamente cambios de desarrollo en main. Revisar los pull requests y ejecutar las pruebas antes de integrar una entrega.

## Trabajo diario sin extensiones

```powershell
git switch desarrollo
git pull --ff-only origin desarrollo
git switch -c feature/nombre-del-cambio
# Editar y verificar.
git add <archivos>
git commit -m "feat: describir el cambio"
git push -u origin feature/nombre-del-cambio
```

Crear el pull request con base `desarrollo`. Para preparar una entrega, crear `release/2.0.0` desde desarrollo, verificarla y abrir el pull request hacia main. Después de integrar, etiquetar el commit publicado y devolver a desarrollo cualquier corrección realizada en la rama release.

## Extensión git-flow opcional

Si git-flow ya está instalado, configurar los nombres sin crear nuevas ramas:

```powershell
git config gitflow.branch.master main
git config gitflow.branch.develop desarrollo
git config gitflow.prefix.feature feature/
git config gitflow.prefix.release release/
git config gitflow.prefix.hotfix hotfix/
git config gitflow.prefix.support support/
git config gitflow.prefix.versiontag v
```

Los comandos Git normales anteriores funcionan aunque la extensión no esté instalada.

## Verificación y despliegue

```powershell
docker compose config --quiet
npm ci
npm run build
docker compose run --rm test
```

Antes de una entrega: revisar migraciones, generar y verificar un respaldo, comprobar servicios y probar el flujo móvil con HTTPS en un teléfono real. Estas instrucciones no certifican que dichas pruebas ya se hayan ejecutado.

Portainer de producción debe seguir la rama main. Mantener el stack existente `inventario-duoc`, sus variables y sus volúmenes, en particular `inventario-duoc_mysql_data`. No ejecutar `down -v` ni eliminar volúmenes al actualizar. La rama desarrollo no modifica por sí sola el despliegue actual.

## Migración desde el repositorio 2.0

Se incorporó el contenido completo del commit `da4616e801e99f20b03cfcb2e5cf32402735b02c` de `RobertoCrofford/ENTREGA-INVENTARIO-CODEX2.0` en desarrollo, partiendo de main `8fbfcf727a1c501f7edd3c2795449dad49fc3c76`.

El commit de importación `6f97376629bcc2ecc498c33eee870316e140ee2c` tiene el mismo árbol Git que la versión 2.0: `b65c24802f8b2fc1018139ff0cf238f77dce21d7`. Por tanto, todos los archivos, rutas y permisos Git coinciden. Esta guía se agregó después como documentación del flujo.
