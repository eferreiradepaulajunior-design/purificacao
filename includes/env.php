<?php
// Load environment variables from .env file if it exists
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $vars = parse_ini_file($envFile, false, INI_SCANNER_TYPED);
    if ($vars) {
        foreach ($vars as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }
}
?>
