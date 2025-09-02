<?php
// ARQUIVO: api/get_clients.php
// Endpoint que o JavaScript do dashboard vai chamar para buscar os dados.

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Proteger o endpoint, apenas usuários logados podem acessar
check_login();

try {
    // 1. Buscar estatísticas
    $stats = [
        'total' => $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn(),
        'enriched' => $pdo->query("SELECT COUNT(*) FROM clients WHERE status = 'enriched'")->fetchColumn(),
        'pending' => $pdo->query("SELECT COUNT(*) FROM clients WHERE status = 'pending'")->fetchColumn(),
        'error' => $pdo->query("SELECT COUNT(*) FROM clients WHERE status = 'error'")->fetchColumn(),
    ];

    // 2. Buscar os últimos 50 clientes atualizados
    $stmt = $pdo->prepare("SELECT id, cnpj, nomeEmpresa, nomeFantasia, cidade, uf, status FROM clients ORDER BY updated_at DESC LIMIT 50");
    $stmt->execute();
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Montar a resposta final
    $response = [
        'stats' => $stats,
        'clients' => $clients
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro no servidor ao buscar os dados.']);
}
?>