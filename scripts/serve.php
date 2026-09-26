<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$temporaryDirectory = $root.'/storage/app/php-uploads';
$publicDirectory = $root.'/public';
$router = $root.'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';

if (
    ! is_dir($temporaryDirectory)
    && ! mkdir($temporaryDirectory, 0700, true)
    && ! is_dir($temporaryDirectory)
) {
    fwrite(STDERR, 'No se pudo crear la carpeta temporal.'.PHP_EOL);
    exit(1);
}

if (! is_writable($temporaryDirectory)) {
    fwrite(STDERR, 'La carpeta temporal no permite escritura.'.PHP_EOL);
    exit(1);
}

if (! is_file($router)) {
    fwrite(STDERR, 'No se encontró el router de Laravel. Ejecutá composer install.'.PHP_EOL);
    exit(1);
}

// Estas opciones afectan solamente al servidor local de este proyecto.
$command = [
    PHP_BINARY,
    '-d',
    'upload_tmp_dir='.$temporaryDirectory,
    '-d',
    'upload_max_filesize=12M',
    '-d',
    'post_max_size=16M',
    '-S',
    '127.0.0.1:8000',
    '-t',
    $publicDirectory,
    $router,
];

echo 'Nelixia: http://127.0.0.1:8000'.PHP_EOL;

$process = proc_open(
    $command,
    [STDIN, STDOUT, STDERR],
    $pipes,
    $publicDirectory,
);

if (! is_resource($process)) {
    fwrite(STDERR, 'No se pudo iniciar el servidor local.'.PHP_EOL);
    exit(1);
}

exit(proc_close($process));
