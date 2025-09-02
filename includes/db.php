<?php
// ARQUIVO: includes/db.php
// Responsável pela conexão com o banco de dados.

require_once __DIR__ . '/env.php';

$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'database';
$user  = getenv('DB_USER') ?: 'user';
$pass  = getenv('DB_PASS') ?: 'password';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Em um ambiente de produção, não exiba detalhes sensíveis.
    error_log('Erro na conexão com o banco de dados: ' . $e->getMessage());
    die('Erro na conexão com o banco de dados.');
}
?>
