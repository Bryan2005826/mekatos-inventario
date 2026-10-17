<?php
/**
 * Copia este archivo como config.php y completa los datos.
 * En InfinityFree los datos de MySQL están en: Panel > MySQL Databases.
 */
return [
    'db' => [
        'host'    => 'sqlXXX.infinityfree.com',   // "MySQL Hostname"
        'port'    => 3306,
        'name'    => 'if0_00000000_asistencia',   // "MySQL DB Name"
        'user'    => 'if0_00000000',              // "MySQL Username"
        'pass'    => 'CAMBIAR',                   // contraseña de la cuenta de hosting
    ],

    // URL pública sin barra final. Déjalo en null para detectarla automáticamente.
    'base_url' => null,

    // Zona horaria del establecimiento
    'timezone' => 'America/Bogota',

    // Clave secreta larga y aleatoria (mínimo 32 caracteres).
    // Genera una en https://www.random.org/strings/ o con: php -r "echo bin2hex(random_bytes(32));"
    'app_key' => 'CAMBIAR_POR_UNA_CLAVE_LARGA_Y_ALEATORIA',

    // Carpeta privada para fotos de evidencia y documentos. Debe quedar FUERA de htdocs.
    // null = carpeta "storage" junto a "app".
    'storage_path' => null,

    // true solo mientras haces pruebas; muestra errores en pantalla.
    'debug' => false,

    // Si el hosting está detrás de Cloudflare u otro proxy, nombre del encabezado con la IP real.
    // Ejemplo: 'HTTP_CF_CONNECTING_IP'. Déjalo en null si no aplica.
    'ip_header' => null,
];
