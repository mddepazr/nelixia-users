<?php

declare(strict_types=1);

// El servidor integrado de PHP en Windows puede interpretar mal las rutas
// Unicode al recibir el directorio public como argumento de -t.
if (PHP_OS_FAMILY === 'Windows' && preg_match('/[^\x00-\x7F]/', dirname(__DIR__)) === 1) {
    fwrite(
        STDERR,
        'La ruta del proyecto contiene caracteres no ASCII. En Windows, PHP puede no iniciar '
        .'el servidor desde esa ruta. Mové el proyecto a una carpeta sin tildes, por ejemplo '
        .'C:\\Dev\\nelixia-users, y volvé a ejecutar el comando.'.PHP_EOL,
    );
    exit(1);
}
