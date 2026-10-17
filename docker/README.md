# Entorno de desarrollo local (Docker)

El código del repo se monta directo en el contenedor: cualquier cambio en los
archivos PHP/JS/CSS se ve al recargar el navegador, sin reconstruir nada.

## Requisitos

- Docker y Docker Compose v2

## Levantar por primera vez

```bash
docker compose up -d --build
./docker/install.sh
```

| Servicio | URL | Credenciales |
|---|---|---|
| Portal de clientes | http://localhost:8081/ | — |
| Panel de agentes / admin | http://localhost:8081/scp/ | `ostadmin` / `Admin1234!` |
| Correos enviados (Mailpit) | http://localhost:8025/ | — |
| MariaDB (desde el host) | `localhost:3307` | `osticket` / `osticket`, base `osticket` (root: `root`) |

Todo correo que envía osTicket se captura en Mailpit; nunca sale a Internet.

## Usuario administrador

`install.sh` crea un único agente con permisos completos:

| Campo | Valor |
|---|---|
| Usuario | `ostadmin` |
| Contraseña | `Admin1234!` |
| Nombre | Admin Dev |
| Email | `admin@example.com` |
| Administrador | Sí (accede al *Admin Panel*) |
| Departamento | Support |
| Rol | All Access |

Datos de la instalación:

| Campo | Valor |
|---|---|
| Nombre del helpdesk | osTicket Dev |
| Email del helpdesk | `support@example.com` |
| Idioma | en_US |
| Zona horaria | UTC |
| Prefijo de tablas | `ost_` |

Estas credenciales son **solo para desarrollo local**; no usarlas en ningún
entorno expuesto. Para cambiarlas, definir las variables antes de instalar:

```bash
ADMIN_USER=miadmin ADMIN_PASS='OtraClave123' ADMIN_EMAIL=yo@empresa.com \
HELPDESK_EMAIL=soporte@empresa.com TIMEZONE=America/Bogota ./docker/reset.sh
```

Para clientes (usuarios finales) no se crea ninguna cuenta: se registran
desde el portal o se crean desde el panel en *Users*.

## Uso diario

```bash
docker compose up -d          # levantar
docker compose down           # apagar (los datos se conservan)
docker compose logs -f web    # ver errores de PHP/Apache
docker compose exec web bash  # shell dentro del contenedor
docker compose exec web php setup/test/run-tests.php   # tests
docker compose exec web php api/cron.php               # ejecutar el cron a mano
./docker/reset.sh             # borrar datos y reinstalar desde cero
```

## Configuración

Los puertos y Xdebug se cambian con un archivo `.env` en la raíz del repo:

```env
WEB_PORT=8081
DB_PORT=3307
MAIL_UI_PORT=8025
XDEBUG_MODE=debug   # off por defecto
```

Después de editar `.env`, ejecutar `docker compose up -d`.

### Depurar con Xdebug (VS Code)

1. Poner `XDEBUG_MODE=debug` en `.env` y ejecutar `docker compose up -d`.
2. Instalar la extensión *PHP Debug* y agregar en `.vscode/launch.json`:

```json
{
  "name": "osTicket (Docker)",
  "type": "php",
  "request": "launch",
  "port": 9003,
  "pathMappings": { "/var/www/html": "${workspaceFolder}" }
}
```

## Notas

- `include/ost-config.php` lo genera `install.sh` y está en `.gitignore`;
  no se sube al repo.
- El panel muestra el aviso *"Please take a minute to delete setup directory"*.
  En desarrollo es esperado y se ignora: `setup/` es parte del código fuente
  (instalador, tests, scripts) y no se debe borrar del repo. En producción sí
  se elimina, ya que `php manage.php deploy` la excluye.
