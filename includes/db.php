<?php
// ARQUIVO: includes/db.php
// Responsável pela conexão com o banco de dados.

$host = 'localhost'; // ou o host do seu DB
$dbname = 'u974869224_purificacao';
$user = 'u974869224_purificacao';
$pass = '3GKG3ayM3E]p';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Em um ambiente de produção, você não deve exibir o erro diretamente.
    // Grave em um log de erros.
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}
?>