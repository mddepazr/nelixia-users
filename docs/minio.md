# Almacenamiento con MinIO

Las fotografías de los usuarios se almacenarán en un bucket privado de
MinIO mediante el adaptador S3 de Laravel. Laravel se ejecuta localmente
y MinIO dentro de Docker.

## Configuración local

1. Copiar `.env.example` a `.env` si todavía no existe.
2. Definir `MINIO_ROOT_USER` y generar `MINIO_ROOT_PASSWORD` con:
   `php -r "echo bin2hex(random_bytes(24)), PHP_EOL;"`
3. Para este entorno local, colocar esos mismos valores en
   `AWS_ACCESS_KEY_ID` y `AWS_SECRET_ACCESS_KEY`.
4. Verificar estas variables:

    - `FILESYSTEM_DISK=s3`
    - `AWS_DEFAULT_REGION=us-east-1`
    - `AWS_BUCKET=nelixia-users`
    - `AWS_ENDPOINT=http://127.0.0.1:9000`
    - `AWS_URL=http://127.0.0.1:9000/nelixia-users`
    - `AWS_USE_PATH_STYLE_ENDPOINT=true`

5. Iniciar MinIO con `docker compose up -d minio`.
6. Abrir http://127.0.0.1:9001 e ingresar con las credenciales configuradas.
7. Crear el bucket `nelixia-users` y mantener su acceso privado.
8. Ejecutar `php artisan config:clear`.

Las dependencias PHP se instalan con `composer install`.

## Puertos y persistencia

- Puerto 9000: API compatible con S3.
- Puerto 9001: consola web.
- Ambos puertos están vinculados a la computadora local.
- El volumen `minio_data` conserva los archivos al recrear el contenedor.
- `docker compose down` detiene y elimina los contenedores.
- No usar `docker compose down -v` si se desean conservar los archivos:
  esa opción también elimina el volumen.

## Verificación realizada

Se comprobó desde Laravel la escritura, lectura y eliminación de un
archivo temporal, y se confirmó que ya no existía después de eliminarlo.

## Alcance

Esta configuración está destinada a desarrollo y evaluación local.
Utiliza credenciales administrativas para la conexión inicial.
Un despliegue productivo requiere credenciales limitadas al bucket,
HTTPS y una revisión del soporte y seguridad de la versión de MinIO.

Las credenciales reales se guardan en `.env` y no se incluyen en Git.
