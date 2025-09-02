<?php
// ARQUIVO: workers/enrichment_worker.php
// Versão final: Agora processa tarefas da fila 'jobs'.

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// --- INÍCIO DO WORKER ---
echo "==================================================\n";
echo "Verificando fila de tarefas em: " . date('Y-m-d H:i:s') . "\n";
echo "==================================================\n";

// --- ETAPA 0: Verificar se há uma tarefa pendente na fila ---
$stmt = $pdo->prepare("SELECT * FROM jobs WHERE status = 'pending' AND job_type = 'sync_and_enrich' LIMIT 1");
$stmt->execute();
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    echo "Nenhuma tarefa de sincronização pendente na fila. Encerrando.\n";
    exit;
}

// Marca a tarefa como 'processing' para evitar que outro processo a pegue
$updateJobStmt = $pdo->prepare("UPDATE jobs SET status = 'processing' WHERE id = ?");
$updateJobStmt->execute([$job['id']]);

echo "Iniciando tarefa de sincronização ID: {$job['id']}...\n";


try {
    // --- FUNÇÕES AUXILIARES (movidas para dentro do try para só rodar se houver job) ---
    function update_setting($pdo, $key, $value) {
        $stmt = $pdo->prepare(
            "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = ?"
        );
        $stmt->execute([$key, $value, $value]);
    }

    function post_to_webhook($clientData, $webhookUrl) {
        if (empty($webhookUrl)) return;
        $options = ['http' => ['header'  => "Content-type: application/json\r\n", 'method'  => 'POST', 'content' => json_encode($clientData), 'ignore_errors' => true]];
        $context  = stream_context_create($options);
        file_get_contents($webhookUrl, false, $context);
        echo "Webhook para cliente ID {$clientData['id']} enviado. Resposta: {$http_response_header[0]}\n";
    }

    // --- ETAPA 1: Buscar novos clientes da API externa (com paginação) ---
    echo "\n--- ETAPA 1: Buscando novos clientes ---\n";
    $apiUrl = get_setting($pdo, 'external_api_url');
    $apiToken = get_setting($pdo, 'external_api_token');
    $lastPageFetched = (int)get_setting($pdo, 'last_fetched_page');
    $currentPage = $lastPageFetched > 0 ? $lastPageFetched : 1;
    $keepFetching = true;
    $totalClientsAdded = 0;

    do {
        echo "Buscando página: {$currentPage}...\n";
        $urlCompleta = "{$apiUrl}?pagina={$currentPage}";
        $options = ['http' => ['header' => "Authorization: {$apiToken}\r\n", 'method' => 'GET', 'ignore_errors' => true]];
        $context = stream_context_create($options);
        $response = file_get_contents($urlCompleta, false, $context);

        if ($response === FALSE || strpos($http_response_header[0], "200 OK") === false) {
            echo "Erro ao buscar dados da API externa na página {$currentPage}. Resposta: " . ($http_response_header[0] ?? 'N/A') . "\n";
            $keepFetching = false; continue;
        }
        $newClients = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($newClients)) {
            echo "Erro ao decodificar JSON da API externa na página {$currentPage}.\n";
            $keepFetching = false; continue;
        }
        if (empty($newClients)) {
            echo "Nenhum cliente novo encontrado na página {$currentPage}. Fim da busca.\n";
            update_setting($pdo, 'last_fetched_page', $currentPage - 1);
            $keepFetching = false; continue;
        }
        $clientsAddedInPage = 0;
        foreach ($newClients as $client) {
            if (empty($client['id']) || empty($client['cnpj'])) continue;
            $stmtCheck = $pdo->prepare("SELECT id FROM clients WHERE external_id = ? OR cnpj = ?");
            $stmtCheck->execute([$client['id'], $client['cnpj']]);
            if ($stmtCheck->fetch()) continue;
            $sql = "INSERT INTO clients (external_id, cnpj, nomeEmpresa, nomeFantasia, cep, logradouro, numero, complemento, uf, cidade, bairro, ramoAtividade, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
            $stmtInsert = $pdo->prepare($sql);
            $stmtInsert->execute([$client['id'], $client['cnpj'], $client['nomeEmpresa'] ?? null, $client['nomeFantasia'] ?? null, $client['cep'] ?? null, $client['logradouro'] ?? null, $client['numero'] ?? null, $client['complemento'] ?? null, $client['uf'] ?? null, $client['cidade'] ?? null, $client['bairro'] ?? null, $client['ramoAtividade'] ?? null]);
            $clientId = $pdo->lastInsertId();
            $clientsAddedInPage++;
            if (!empty($client['contatos']) && is_array($client['contatos'])) {
                foreach ($client['contatos'] as $contact) {
                    $sqlContact = "INSERT INTO client_contacts (client_id, external_contact_id, contato, info, tipo) VALUES (?, ?, ?, ?, ?)";
                    $stmtContact = $pdo->prepare($sqlContact);
                    $stmtContact->execute([$clientId, $contact['id'], $contact['contato'] ?? null, $contact['info'] ?? null, $contact['tipo'] ?? null]);
                }
            }
        }
        if ($clientsAddedInPage > 0) {
            echo "{$clientsAddedInPage} novos clientes adicionados da página {$currentPage}.\n";
            $totalClientsAdded += $clientsAddedInPage;
        } else {
            echo "Nenhum cliente inédito na página {$currentPage}.\n";
        }
        $currentPage++;
        sleep(1); 
    } while ($keepFetching);
    echo "Total de novos clientes adicionados nesta execução: {$totalClientsAdded}.\n";

    // --- ETAPA 2: Processar clientes pendentes para enriquecimento ---
    echo "\n--- ETAPA 2: Processando clientes pendentes ---\n";
    $googleApiKey      = get_setting($pdo, 'google_api_key');
    $enrichmentApiKey  = get_setting($pdo, 'enrichment_service_api_key');
    $serpApiKey        = get_setting($pdo, 'serp_api_key');
    $desiredSectors    = ['Setor Comercial', 'Compras'];
    $outboundWebhookUrl = get_setting($pdo, 'outbound_webhook_url');
    $stmtPending = $pdo->prepare("SELECT * FROM clients WHERE status = 'pending' LIMIT 100");
    $stmtPending->execute();
    $pendingClients = $stmtPending->fetchAll(PDO::FETCH_ASSOC);
    if (empty($pendingClients)) {
        echo "Nenhum cliente pendente para enriquecer.\n";
    } else {
        echo "Encontrados " . count($pendingClients) . " clientes para enriquecer.\n";
    }
    foreach ($pendingClients as $client) {
        $updateStmt = $pdo->prepare("UPDATE clients SET status = 'processing' WHERE id = ?");
        $updateStmt->execute([$client['id']]);
        echo "Enriquecendo cliente ID: {$client['id']} ({$client['nomeEmpresa']})...\n";
        $googleData = enrichWithGoogle($client, $googleApiKey);
        $client['website'] = $googleData['website'] ?? null;
        $thirdPartyData = enrichWithThirdParty($client, $enrichmentApiKey);
        $allEnrichedData = array_merge($googleData, $thirdPartyData);
        $finalStmt = $pdo->prepare("UPDATE clients SET enriched_data = ?, status = 'enriched' WHERE id = ?");
        $finalStmt->execute([json_encode($allEnrichedData), $client['id']]);
        echo "Cliente ID: {$client['id']} enriquecido com sucesso.\n";

        // Enriquecimento adicional via LinkedIn
        foreach ($desiredSectors as $sector) {
            $linkedinContacts = enrichWithLinkedIn($client, $sector, $serpApiKey);
            if (empty($linkedinContacts)) {
                echo "SerpAPI sem resultados ou indisponível para setor {$sector}.\n";
                continue;
            }
            foreach ($linkedinContacts as $contact) {
                $stmtLinked = $pdo->prepare("INSERT INTO client_contacts (client_id, contato, info, tipo, source) VALUES (?, ?, ?, ?, 'linkedin')");
                $stmtLinked->execute([$client['id'], $contact['name'], $contact['linkedin'], $sector]);
            }
        }
        if (!empty($outboundWebhookUrl)) {
            $stmtFullData = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
            $stmtFullData->execute([$client['id']]);
            $fullClientData = $stmtFullData->fetch(PDO::FETCH_ASSOC);
            $contactsStmt = $pdo->prepare("SELECT contato, info, tipo FROM client_contacts WHERE client_id = ?");
            $contactsStmt->execute([$client['id']]);
            $fullClientData['contatos'] = $contactsStmt->fetchAll(PDO::FETCH_ASSOC);
            $fullClientData['dados_enriquecidos'] = json_decode($fullClientData['enriched_data'], true);
            unset($fullClientData['enriched_data']);
            post_to_webhook($fullClientData, $outboundWebhookUrl);
        }
    }

    // Marca a tarefa como 'completed'
    $updateJobStmt = $pdo->prepare("UPDATE jobs SET status = 'completed', completed_at = CURRENT_TIMESTAMP WHERE id = ?");
    $updateJobStmt->execute([$job['id']]);
    echo "\nTarefa de sincronização ID: {$job['id']} concluída com sucesso.\n";

} catch (Exception $e) {
    // Em caso de erro, marca a tarefa como 'failed'
    $updateJobStmt = $pdo->prepare("UPDATE jobs SET status = 'failed' WHERE id = ?");
    $updateJobStmt->execute([$job['id']]);
    echo "\nERRO FATAL na tarefa ID: {$job['id']}. Mensagem: {$e->getMessage()}\n";
}

echo "Ciclo do worker finalizado em: " . date('Y-m-d H:i:s') . "\n";
echo "==================================================\n\n";
