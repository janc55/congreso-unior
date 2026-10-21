<?php
if (!defined('UNIOR_INICIADO')) {
    http_response_code(403);
    exit;
}

$getEnvVal = function (string $key, mixed $default = null): mixed {
    $val = getenv($key);
    if ($val !== false && $val !== '') {
        return $val;
    }
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        return $_SERVER[$key];
    }
    return $default;
};

return array(
    'instalado' => true,
    'host'      => $getEnvVal('DB_HOST', '127.0.0.1'),
    'port'      => (int)$getEnvVal('DB_PORT', 3306),
    'database'  => $getEnvVal('DB_NAME', 'sql_unior_congreso'),
    'username'  => $getEnvVal('DB_USER', 'root'),
    'password'  => $getEnvVal('DB_PASSWORD', ''),
    'base_url'  => rtrim((string)$getEnvVal('APP_URL', 'http://localhost'), '/'),
    'timezone'  => $getEnvVal('APP_TIMEZONE', 'America/La_Paz'),
);
