<?php

declare(strict_types=1);

use App\Models\User;
use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Dotenv\Dotenv;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

$root = dirname(__DIR__);
chdir($root);

/**
 * Ejecuta un proceso y detiene la instalación si falla.
 *
 * @param  list<string>  $command
 */
function runSetupProcess(array $command): void
{
    $process = new Process($command, dirname(__DIR__));
    $process->setTimeout(null);
    $process->mustRun(function (string $type, string $output): void {
        echo $output;
    });
}

/**
 * Completa únicamente valores vacíos del archivo de entorno.
 */
function fillSetupValue(string &$contents, string $key, string $value): void
{
    $escaped = str_replace(
        ['\\', '"', '$'],
        ['\\\\', '\\"', '\\$'],
        $value,
    );

    $line = $key.'="'.$escaped.'"';
    $pattern = '/^'.preg_quote($key, '/').'[ \t]*=.*$/m';

    if (preg_match($pattern, $contents) === 1) {
        $contents = preg_replace_callback(
            $pattern,
            static fn (): string => $line,
            $contents,
        ) ?? throw new RuntimeException('No se pudo actualizar '.$key);
    } else {
        $contents = rtrim($contents).PHP_EOL.$line.PHP_EOL;
    }
}

try {
    foreach (['pdo_sqlite', 'curl', 'mbstring', 'fileinfo', 'openssl'] as $extension) {
        if (! extension_loaded($extension)) {
            throw new RuntimeException(
                'Falta la extensión PHP '.$extension.'. Revisá php --ini.',
            );
        }
    }

    $npm = PHP_OS_FAMILY === 'Windows' ? 'npm.cmd' : 'npm';

    echo PHP_EOL.'Comprobando herramientas y Docker...'.PHP_EOL;

    runSetupProcess(['node', '--version']);
    runSetupProcess([$npm, '--version']);
    runSetupProcess(['docker', 'compose', 'version']);
    runSetupProcess(['docker', 'info', '--format', '{{.ServerVersion}}']);

    // Protege los archivos nuevos que contienen credenciales en Linux.
    umask(0077);

    if (! is_file($root.'/.env')) {
        if (! copy($root.'/.env.example', $root.'/.env')) {
            throw new RuntimeException('No se pudo crear .env.');
        }
    }

    $env = Dotenv::createArrayBacked($root)->load();

    if (($env['APP_ENV'] ?? '') !== 'local') {
        throw new RuntimeException('Este instalador solo permite APP_ENV=local.');
    }

    if (
        ($env['DB_CONNECTION'] ?? '') !== 'sqlite'
        || ! empty($env['DB_URL'])
        || ! empty($env['DB_DATABASE'])
    ) {
        throw new RuntimeException(
            'El instalador automático requiere SQLite con DB_DATABASE y DB_URL sin definir. '
            .'La configuración personalizada se conserva; requiere instalación manual.',
        );
    }

    if (
        ($env['AWS_ENDPOINT'] ?? '') !== 'http://127.0.0.1:9000'
        || ($env['FILESYSTEM_DISK'] ?? '') !== 's3'
    ) {
        throw new RuntimeException(
            'El instalador requiere el MinIO local en http://127.0.0.1:9000 y FILESYSTEM_DISK=s3.',
        );
    }

    $contents = file_get_contents($root.'/.env');

    if ($contents === false) {
        throw new RuntimeException('No se pudo leer .env.');
    }

    $minioUser = $env['MINIO_ROOT_USER'] ?? '';
    $minioPassword = $env['MINIO_ROOT_PASSWORD'] ?? '';

    if ($minioUser === '') {
        $minioUser = 'nelixia-admin';
        fillSetupValue($contents, 'MINIO_ROOT_USER', $minioUser);
    }

    if ($minioPassword === '') {
        $minioPassword = bin2hex(random_bytes(24));
        fillSetupValue($contents, 'MINIO_ROOT_PASSWORD', $minioPassword);
    }

    if (empty($env['AWS_ACCESS_KEY_ID'])) {
        fillSetupValue($contents, 'AWS_ACCESS_KEY_ID', $minioUser);
    }

    if (empty($env['AWS_SECRET_ACCESS_KEY'])) {
        fillSetupValue($contents, 'AWS_SECRET_ACCESS_KEY', $minioPassword);
    }

    if (empty($env['APP_KEY'])) {
        fillSetupValue(
            $contents,
            'APP_KEY',
            'base64:'.base64_encode(random_bytes(32)),
        );
    }

    if (file_put_contents($root.'/.env', $contents, LOCK_EX) === false) {
        throw new RuntimeException('No se pudo guardar .env.');
    }

    $env = Dotenv::createArrayBacked($root)->load();

    $temporaryDirectory = $root.'/storage/app/php-uploads';

    if (
        ! is_dir($temporaryDirectory)
        && ! mkdir($temporaryDirectory, 0700, true)
        && ! is_dir($temporaryDirectory)
    ) {
        throw new RuntimeException('No se pudo crear la carpeta temporal.');
    }

    if (! is_writable($temporaryDirectory)) {
        throw new RuntimeException('La carpeta temporal no permite escritura.');
    }

    $database = $root.'/database/database.sqlite';

    if (! is_file($database) && ! touch($database)) {
        throw new RuntimeException('No se pudo crear la base SQLite.');
    }

    echo PHP_EOL.'Iniciando MinIO y Mailpit...'.PHP_EOL;

    runSetupProcess(['docker', 'compose', 'config', '--quiet']);
    runSetupProcess(['docker', 'compose', 'up', '-d', 'minio', 'mailpit']);

    $client = new S3Client([
        'version' => 'latest',
        'region' => $env['AWS_DEFAULT_REGION'] ?? 'us-east-1',
        'endpoint' => $env['AWS_ENDPOINT'],
        'use_path_style_endpoint' => true,
        'credentials' => [
            'key' => $env['AWS_ACCESS_KEY_ID'],
            'secret' => $env['AWS_SECRET_ACCESS_KEY'],
        ],
        'retries' => 0,
        'http' => [
            'connect_timeout' => 2,
            'timeout' => 3,
        ],
    ]);

    $bucket = $env['AWS_BUCKET'] ?? '';

    if ($bucket === '') {
        throw new RuntimeException('Falta AWS_BUCKET en .env.');
    }

    echo 'Esperando a MinIO y comprobando el bucket...'.PHP_EOL;

    $bucketReady = false;

    for ($attempt = 1; $attempt <= 20; $attempt++) {
        try {
            try {
                $client->headBucket(['Bucket' => $bucket]);
            } catch (AwsException $exception) {
                if ($exception->getStatusCode() !== 404) {
                    throw $exception;
                }

                // Crea un bucket privado únicamente si no existe.
                $client->createBucket(['Bucket' => $bucket]);
                $client->headBucket(['Bucket' => $bucket]);
            }

            $bucketReady = true;
            break;
        } catch (AwsException $exception) {
            $status = $exception->getStatusCode();

            if ($status === 401 || $status === 403) {
                throw new RuntimeException(
                    'MinIO rechazó las credenciales S3. Revisá AWS_ACCESS_KEY_ID '
                    .'y AWS_SECRET_ACCESS_KEY en .env.',
                );
            }

            if ($status !== null && $status < 500) {
                throw new RuntimeException(
                    'No se pudo preparar el bucket. Código S3: '
                    .($exception->getAwsErrorCode() ?? (string) $status),
                );
            }

            echo 'Esperando MinIO: intento '.$attempt.'/20...'.PHP_EOL;
            sleep(1);
        }
    }

    if (! $bucketReady) {
        throw new RuntimeException(
            'MinIO no respondió a tiempo. Revisá docker compose logs minio.',
        );
    }

    runSetupProcess([PHP_BINARY, 'artisan', 'config:clear']);
    runSetupProcess([PHP_BINARY, 'artisan', 'migrate', '--force']);
    runSetupProcess([
        PHP_BINARY,
        'artisan',
        'db:seed',
        '--class=Database\\Seeders\\CatalogSeeder',
        '--force',
    ]);

    echo PHP_EOL.'Instalando y compilando el frontend...'.PHP_EOL;

    runSetupProcess([$npm, 'ci']);
    runSetupProcess([$npm, 'run', 'build']);

    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    if (! User::query()->where('is_admin', true)->exists()) {
        echo PHP_EOL.'Falta crear el primer administrador.'.PHP_EOL;
        echo 'Ejecutá después: php artisan app:create-admin'.PHP_EOL;
    } else {
        echo PHP_EOL.'Ya existe un administrador; se conserva.'.PHP_EOL;
    }

    echo PHP_EOL.'Instalación terminada.'.PHP_EOL;
    echo 'Iniciá la aplicación con: composer dev'.PHP_EOL;
    echo 'Aplicación: http://127.0.0.1:8000'.PHP_EOL;
    echo 'Buzón de pruebas: http://127.0.0.1:8025'.PHP_EOL;
    echo 'Consola MinIO: http://127.0.0.1:9001'.PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, PHP_EOL.'Instalación detenida: '.$exception->getMessage().PHP_EOL);
    exit(1);
}
