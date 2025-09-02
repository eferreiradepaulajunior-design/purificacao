<?php
// ARQUIVO: api/outbound_webhook.php
// Endpoint para outros sistemas buscarem os dados.

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

// Verificação simples de token via query string
$providedToken = $_GET['token'] ?? '';
$expectedToken = getenv('API_TOKEN');
if (!$expectedToken || !hash_equals($expectedToken, $providedToken)) {
    http_response_code(401);
    echo json_encode(['error' => 'Token inválido.']);
    exit;
}

// Exemplo de busca por CNPJ
$cnpj = $_GET['cnpj'] ?? null;

if (!$cnpj) {
    http_response_code(400);
    echo json_encode(['error' => 'Parâmetro CNPJ é obrigatório.']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM clients WHERE cnpj = ? AND status = 'enriched'");
$stmt->execute([$cnpj]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    http_response_code(404);
    echo json_encode(['error' => 'Cliente não encontrado ou não enriquecido.']);
    exit;
}

// Decodificar o JSON para mesclar com os dados principais
$enrichedData = json_decode($client['enriched_data'], true);
unset($client['enriched_data']);

// Adicionar contatos
$contactsStmt = $pdo->prepare("SELECT contato, info, tipo FROM client_contacts WHERE client_id = ?");
$contactsStmt->execute([$client['id']]);
$contacts = $contactsStmt->fetchAll(PDO::FETCH_ASSOC);

$client['contatos'] = $contacts;
$client['dados_enriquecidos'] = $enrichedData;

echo json_encode($client);
?>
