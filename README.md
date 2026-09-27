# Nelixia — Administración de usuarios

Módulo de administración de usuarios desarrollado con Laravel 13, el starter kit oficial de React, TypeScript, Inertia y shadcn/ui. Permite listar, crear, editar y eliminar registros con fotografía recortada manualmente. Las fotografías definitivas se guardan en MinIO mediante el filesystem S3 de Laravel.

Laravel se ejecuta en el equipo local. Docker ejecuta MinIO y Mailpit.

## Estado de verificación

- Funcionalidad CRUD y permisos verificados mediante pruebas automatizadas y comprobaciones manuales.
- Última revisión completa reportada antes de incorporar los scripts de instalación: 56 pruebas aprobadas, 3 omitidas y 272 aserciones. Las omitidas corresponden a autenticación de dos factores desactivada.
- `composer setup` y `composer dev` ejecutados correctamente sobre una instalación existente en Windows 11 con PHP 8.4.25 y Node.js 24.21.0. En un clon separado se verificaron MinIO, bucket, migraciones, catálogo y build; la creación del primer administrador funcionó al ejecutar el comando directamente.
- Pendiente: repetir la instalación desde cero en otro Windows con la imagen de MinIO compilada localmente y en Linux. Esa compatibilidad todavía requiere verificación práctica.

## Requisitos previos

| Herramienta         | Requisito                                                            |
| ------------------- | -------------------------------------------------------------------- |
| Git                 | Instalado y con acceso al repositorio                                |
| PHP                 | Se recomienda PHP 8.4.x; Pest 5 exige al menos PHP 8.4               |
| Composer            | Versión 2.x, disponible en la terminal                               |
| Node.js             | Se recomienda Node.js 24.x, con npm                                  |
| Docker              | Docker Desktop en Windows; Docker Engine o Desktop en Linux          |
| Docker Compose      | Plugin que permita ejecutar `docker compose`                         |
| Conexión a Internet | Necesaria para descargar dependencias, imágenes y recursos del build |

El proyecto declara PHP `^8.3` para la aplicación, pero la instalación incluye dependencias de desarrollo que requieren PHP 8.4. `composer install` verifica las restricciones exactas de `composer.lock`. No usar `--ignore-platform-reqs`.

PHP debe disponer de las extensiones requeridas por Laravel y las dependencias: Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, Session, Tokenizer, XML y PDO SQLite. Para las pruebas con imágenes, habilitar GD. Para descargar archivos comprimidos, disponer de ZIP o de una herramienta de descompresión compatible con Composer. El instalador comprueba expresamente PDO SQLite, cURL, Mbstring, Fileinfo y OpenSSL; Composer valida los requisitos declarados por los paquetes.

Comprobación inicial:

```sh
php -v
php -m
composer --version
node --version
docker compose version
docker info
```

En Windows, abrir Docker Desktop y esperar a que el motor esté listo. En Linux, el usuario debe poder ejecutar Docker y escribir en la carpeta del proyecto. No ejecutar Composer ni npm como root para evitar archivos con propietarios incorrectos.

Los puertos 8000, 5173, 9000, 9001, 1025 y 8025 deben estar disponibles. Usar contenedores Linux en Docker Desktop.

## Instalación rápida

Desde una terminal, clonar el repositorio:

```sh
git clone https://github.com/mddepazr/nelixia-users.git
cd nelixia-users
composer setup
php artisan app:create-admin
composer dev
```

El comando anterior clona `main`, que contiene el instalador y esta guía.

Si el repositorio es privado, el evaluador necesita acceso mediante su propia cuenta de GitHub. No incluir tokens de acceso en el comando ni en el README.

### Qué hace `composer setup`

1. Instala las dependencias PHP de `composer.lock`, incluidas las de desarrollo.
2. Comprueba Node.js, npm, Docker Compose, el motor Docker y algunas extensiones PHP.
3. Copia `.env.example` a `.env` únicamente si no existe.
4. Completa las claves vacías de la aplicación y MinIO. Conserva valores existentes.
5. Crea la carpeta temporal de cargas y el archivo SQLite si faltan.
6. Compila MinIO Community desde su código fuente oficial, con la versión fijada en `docker/minio/Dockerfile`, y lo inicia junto con Mailpit mediante Docker Compose. El primer build puede tardar varios minutos; Docker reutiliza sus capas en instalaciones posteriores.
7. Espera a MinIO, comprueba el bucket y lo crea si no existe.
8. Limpia la caché de configuración, ejecuta migraciones pendientes y el catálogo inicial.
9. Ejecuta `npm ci` y compila el frontend.
10. Indica cómo crear el primer administrador si todavía no existe uno.

La contraseña del administrador debe tener al menos 12 caracteres, mayúsculas, minúsculas y números. No existe una contraseña administrativa predeterminada.

En una instalación nueva, ejecutá `php artisan app:create-admin` desde la terminal después de `composer setup`. El comando solicita nombre, correo y contraseña. Si ya existe un administrador, no hace falta volver a ejecutarlo. La solicitud interactiva se ejecuta por separado porque al invocarla dentro de `composer setup` se abortó en la prueba con PowerShell.

El instalador está limitado a `APP_ENV=local`, `DB_CONNECTION=sqlite`, `FILESYSTEM_DISK=s3` y `AWS_ENDPOINT=http://127.0.0.1:9000`. `DB_DATABASE` y `DB_URL` deben estar ausentes o vacíos. Las configuraciones personalizadas se conservan, pero requieren preparación manual.

Volver a ejecutar `composer setup` reinstala las dependencias del frontend, compila y ejecuta las migraciones y el seeder. No utiliza `migrate:fresh`, no elimina volúmenes ni regenera una `APP_KEY` que ya tenga valor. Si falla un paso, se detiene; los pasos ya completados permanecen aplicados. Corregir la causa y repetir el comando.

MinIO ya no se descarga desde Quay. El build de Docker usa la imagen oficial `golang:1.24.6-alpine3.22`, obtiene el código fuente de MinIO en la versión `RELEASE.2025-09-07T16-13-09Z` y guarda el ejecutable en una imagen local. Requiere conexión a Docker Hub, Alpine y el repositorio de módulos Go durante la primera compilación. `composer dev` utiliza la imagen ya compilada por `composer setup`.

## Ejecución diaria

Con Docker iniciado y desde la carpeta del proyecto:

```sh
composer dev
```

Este comando inicia los contenedores y mantiene activos el servidor PHP, la cola y Vite. **Es normal que no devuelva el prompt:** la terminal debe permanecer abierta mientras se usa la aplicación.

| Servicio                        | Dirección o puerto                                   |
| ------------------------------- | ---------------------------------------------------- |
| Aplicación                      | http://127.0.0.1:8000                                |
| Mailpit, buzón local de pruebas | http://127.0.0.1:8025                                |
| Consola MinIO                   | http://127.0.0.1:9001                                |
| API S3 de MinIO                 | http://127.0.0.1:9000                                |
| SMTP de Mailpit                 | 127.0.0.1:1025                                       |
| Vite, recursos de desarrollo    | Puerto 5173; no es la URL principal de la aplicación |

Para detener PHP, Vite y la cola, presionar `Ctrl+C`. Los contenedores permanecen activos. Para detenerlos también:

```sh
docker compose stop
```

Los volúmenes conservan las fotografías y los mensajes de Mailpit. No ejecutar `docker compose down -v` si se desea conservarlos.

## Configuración y datos locales

`.env` contiene la configuración particular de cada equipo y no se versiona. `.env.example` es la plantilla compartida.

| Configuración                                | Uso                                                                                            |
| -------------------------------------------- | ---------------------------------------------------------------------------------------------- |
| `APP_KEY`                                    | Clave de la aplicación; se genera solo si está vacía                                           |
| `MINIO_ROOT_USER`, `MINIO_ROOT_PASSWORD`     | Acceso administrativo a MinIO                                                                  |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` | Credenciales del filesystem S3; en una instalación nueva se completan con las locales de MinIO |
| `AWS_BUCKET`                                 | Bucket; valor de ejemplo `nelixia-users`                                                       |
| `AWS_ENDPOINT`                               | Endpoint local de MinIO                                                                        |
| `AWS_USE_PATH_STYLE_ENDPOINT`                | Debe mantenerse en `true` para esta configuración                                              |
| `MAIL_HOST`, `MAIL_PORT`                     | Mailpit local: `127.0.0.1`, `1025`                                                             |
| `APP_URL`                                    | URL local usada también para enlaces de recuperación                                           |

Para entrar a la consola MinIO, consultar localmente las variables `MINIO_ROOT_USER` y `MINIO_ROOT_PASSWORD` del `.env`. No compartir ese archivo.

La base de datos se guarda en `database/database.sqlite`. Las fotografías definitivas se guardan en el volumen Docker `minio_data`; Mailpit utiliza `mailpit_data`. Docker Compose añade normalmente un prefijo basado en el nombre del proyecto.

Un clon nuevo crea una instalación independiente: no copia usuarios, fotografías ni correos del equipo original. Los catálogos iniciales se cargan mediante `CatalogSeeder`.

### Fotografías y carpeta temporal

La interfaz permite seleccionar JPG, PNG o WebP, recortar a proporción 1:1 y confirmar el resultado. El formulario trabaja con una imagen final de 512 × 512 y un máximo de 2 MB. El límite de selección de la imagen original en la interfaz es 10 MB.

`scripts/serve.php` crea `storage/app/php-uploads` y configura el proceso PHP con `upload_tmp_dir` apuntando a esa carpeta, `upload_max_filesize=12M` y `post_max_size=16M`. Estos límites de transporte no cambian las validaciones de la aplicación.

No es necesario editar `php.ini` para esa carpeta al iniciar con `composer dev`. Si se usa Herd, Apache o `php artisan serve` directamente, se estará usando otro mecanismo de arranque y habrá que revisar su configuración PHP por separado. Las cargas temporales no sustituyen el almacenamiento definitivo en MinIO.

## Recorrido de evaluación

1. Abrir la aplicación e iniciar sesión con el administrador creado durante la instalación.
2. Entrar a Usuarios y crear un registro, seleccionando empresa y departamento.
3. Cargar una fotografía, mover y ajustar el recorte, confirmarlo y guardar.
4. Verificar que la fotografía y los datos aparecen en el listado.
5. Probar búsqueda, filtros y limpieza de filtros; crear suficientes registros para comprobar la paginación.
6. Editar primero sin cambiar la fotografía y después reemplazarla.
7. Probar un correo duplicado y verificar el mensaje de validación.
8. Eliminar un registro y comprobar el aviso de resultado.
9. Probar recuperación de contraseña y abrir el mensaje en Mailpit.

Las cuentas de acceso al sistema y los registros del directorio son entidades distintas. Crear una persona en el directorio no crea automáticamente una cuenta para iniciar sesión.

## Recuperación de contraseña

Desde el login, seleccionar «¿Olvidaste tu contraseña?» y utilizar el correo de una cuenta de acceso existente. Abrir http://127.0.0.1:8025 y seguir el enlace recibido.

Mailpit captura el correo localmente: no lo entrega a Gmail, Outlook ni a otros buzones externos. No requiere credenciales de un proveedor de correo.

El login limita los intentos por combinación de correo e IP. La interfaz muestra una cuenta regresiva; el servidor conserva la decisión de permitir o rechazar el intento. La autenticación de dos factores está desactivada.

## Pruebas y comprobaciones

Desde otra terminal en la raíz del proyecto:

```sh
composer ci:check
```

Incluye formato y lint del frontend, TypeScript, Pint, PHPStan y pruebas automatizadas. Las pruebas Feature utilizan SQLite en memoria y `RefreshDatabase`; las pruebas de fotografías usan almacenamiento simulado. Complementar con el recorrido manual contra MinIO real.

Comprobación de los scripts de instalación:

```sh
php -l scripts/setup.php
php -l scripts/serve.php
composer validate
```

Compilar para comprobar el build:

```sh
npm run build
```

En PowerShell, usar `npm.cmd run build` si la política de ejecución impide usar `npm`. Los scripts de Composer y el instalador gestionan sus propias llamadas.

## Limpieza de fotografías pendientes

Cuando se elimina un usuario, el sistema registra el trabajo pendiente de limpieza de su fotografía. Si MinIO falla, se puede reintentar:

```sh
php artisan app:cleanup-user-photos
```

Que se informe «Fotografías eliminadas: 0» puede significar que no había trabajo pendiente. Este comando no está programado automáticamente. El comportamiento de limpieza al reemplazar una foto debe evaluarse por separado del flujo de eliminación del usuario.

## Organización del código

- `app/Http/Controllers/DirectoryUserController.php`: coordina las operaciones HTTP del directorio.
- `app/Http/Requests/`: validación y autorización de solicitudes.
- `app/Actions/DirectoryUsers/`: creación, actualización y eliminación de registros.
- `app/Services/`: almacenamiento y limpieza de fotografías.
- `app/Models/` y `database/migrations/`: entidades, relaciones y esquema relacional.
- `database/seeders/CatalogSeeder.php`: empresas y departamentos iniciales.
- `resources/js/pages/directory-users/`: listado, creación y edición.
- `resources/js/components/`: formulario compartido, recorte y confirmación de eliminación.
- `tests/Feature/`: pruebas de permisos, validación, autenticación y operaciones del directorio.
- `scripts/`: instalación y arranque local.

Las empresas tienen departamentos y cada registro del directorio pertenece a un departamento. El esquema conserva fechas de creación y actualización. La lógica de operaciones está separada de la validación y de los detalles de almacenamiento.

## Solución de problemas

| Problema                                             | Acción                                                                                                |
| ---------------------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| Docker no responde                                   | Iniciar Docker y comprobar `docker info`                                                              |
| Puerto ocupado                                       | Detener la instancia anterior del servidor o el servicio que usa ese puerto                           |
| Composer indica una extensión o versión incompatible | Revisar `php -v`, `php --ini` y `php -m`; habilitar la extensión en el PHP de la terminal             |
| MinIO rechaza credenciales                           | Revisar la coherencia de las credenciales de MinIO y S3 en `.env`; no borrar volúmenes como solución  |
| Configuración antigua                                | Ejecutar `php artisan config:clear` y reiniciar `composer dev`                                        |
| Error al recibir una fotografía                      | Arrancar con `composer dev` y comprobar permisos de `storage/app/php-uploads`                         |
| No aparece el correo                                 | Revisar que exista la cuenta de acceso, los valores SMTP y `docker compose ps`                        |
| No se carga el frontend                              | Mantener Vite activo con `composer dev`; revisar su salida en la terminal                             |
| Advertencia de `fontaine`                            | En las ejecuciones verificadas no bloquea el build; se refiere a una optimización opcional de fuentes |
| Instalación detenida a mitad                         | Corregir el error indicado y repetir `composer setup`                                                 |

Si `app:create-admin` muestra `no such table: users`, `composer setup` no llegó a las migraciones. Repetí la instalación y esperá el mensaje «Instalación terminada» antes de crear el administrador.

Para consultar los servicios:

```sh
docker compose ps
docker compose logs --tail=100 minio mailpit
```

Los registros de Laravel se encuentran en `storage/logs`. Pueden contener datos de la aplicación: revisar antes de compartirlos.

## Alcance

Esta configuración está destinada a la evaluación y desarrollo local. No incluye despliegue público ni endurecimiento de un entorno de producción. El logo actual puede ser provisional hasta recibir el recurso oficial.

Referencias: [Laravel](https://laravel.com/docs/13.x), [requisitos de Pest](https://pestphp.com/docs/installation), [servidor local PHP](https://www.php.net/commandline.webserver).
