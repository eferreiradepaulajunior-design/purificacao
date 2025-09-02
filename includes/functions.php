<?php

// ARQUIVO: includes/functions.php
// Funções reutilizáveis, incluindo as de enriquecimento.

/**
 * Busca uma configuração do banco de dados.
 */
function get_setting($pdo, $key) {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    return $stmt->fetchColumn();
}

/**
 * Placeholder para função de enriquecimento com Google.
 * A implementação real dependerá da API do Google (Places, Search).
 */
function enrichWithGoogle($clientData, $apiKey) {
    // Lógica para chamar a API do Google aqui...
    // Exemplo: buscar pelo CNPJ ou Nome da Empresa + Endereço
    $searchQuery = urlencode(($clientData['nomeEmpresa'] ?? '') . " " . ($clientData['cidade'] ?? ''));
    // $url = "https://maps.googleapis.com/maps/api/place/textsearch/json?query={$searchQuery}&key={$apiKey}";
    // $response = file_get_contents($url);
    // $googleData = json_decode($response, true);

    // Retornar dados simulados por enquanto
    return [
        'google_rating' => 4.5,
        'website'       => 'https://www.empresaexemplo.com.br',
        'main_phone'    => '(11) 99999-8888'
    ];
}

/**
 * Dispara uma busca no serviço Python e aguarda o resultado.
 */
function callSearchService($baseUrl, $startEndpoint, $statusEndpoint, array $payload) {
    if (empty($baseUrl)) { return null; }

    $ch = curl_init(rtrim($baseUrl, '/') . $startEndpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload)
    ]);
    $startResponse = curl_exec($ch);
    curl_close($ch);
    $startData = json_decode($startResponse, true);
    if (empty($startData['task_id'])) { return null; }

    $taskId = $startData['task_id'];
    for ($i = 0; $i < 10; $i++) {
        sleep(3);
        $statusJson = @file_get_contents(rtrim($baseUrl, '/') . "$statusEndpoint/$taskId");
        $statusData = json_decode($statusJson, true);
        if (!is_array($statusData)) { continue; }
        if ($statusData['status'] === 'concluido') {
            return $statusData['result'] ?? null;
        }
        if ($statusData['status'] === 'falhou') {
            return null;
        }
    }
    return null;
}

function searchLinkedinProfiles($baseUrl, $profession, $searchTerm, $numResults = 20) {
    return callSearchService(
        $baseUrl,
        '/iniciar-busca-perfis',
        '/status/perfis',
        [
            'profession'  => $profession,
            'search_term' => $searchTerm,
            'num_results' => $numResults
        ]
    ) ?: [];
}

function searchCompanyData($baseUrl, $companyName) {
    return callSearchService(
        $baseUrl,
        '/iniciar-busca-empresa',
        '/status/empresa',
        ['company_name' => $companyName]
    );
}

/**
 * Função de enriquecimento usando o serviço Python de busca.
 */
function enrichWithSearchService($clientData, $serviceUrl) {
    if (empty($serviceUrl)) { return []; }

    $companyInfo = null;
    if (!empty($clientData['nomeEmpresa'])) {
        $companyInfo = searchCompanyData($serviceUrl, $clientData['nomeEmpresa']);
    }

    // Exemplo: buscar perfis de compras da empresa
    $profiles = [];
    if (!empty($clientData['nomeEmpresa'])) {
        $profiles = searchLinkedinProfiles($serviceUrl, 'Compras', $clientData['nomeEmpresa']);
    }

    return [
        'company_info' => $companyInfo,
        'profiles'     => $profiles
    ];
}


?>

/**
 * Executa uma busca de contatos no LinkedIn por meio de um serviço externo.
 *
 * A função inicia uma tarefa de busca e aguarda a conclusão consultando o
 * endpoint de status. Em caso de sucesso, retorna uma lista de contatos com
 * título e link; caso contrário, lança uma exceção.
 *
 * @param string $profession  Profissão ou cargo que será utilizado no filtro.
 * @param string $searchTerm  Termo de busca adicional (ex.: empresa ou cidade).
 * @param int    $numResults  Quantidade máxima de resultados desejados.
 *
 * @return array Lista de contatos no formato ['title' => string, 'link' => string]
 *
 * @throws Exception Quando houver falha na comunicação ou timeout.
 */
function enrichWithLinkedIn($profession, $searchTerm, $numResults = 20) {
    $baseUrl = rtrim(getenv('LINKEDIN_API_URL') ?: '', '/');
    if (empty($baseUrl)) {
        throw new Exception('LinkedIn API URL não configurada.');
    }

    $token = getenv('API_TOKEN');

    $payload = json_encode([
        'profession' => $profession,
        'searchTerm' => $searchTerm,
        'numResults' => $numResults,
    ]);

    $ch = curl_init($baseUrl . '/iniciar-busca');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_filter([
        'Content-Type: application/json',
        $token ? 'Authorization: Bearer ' . $token : null,
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception('Erro ao iniciar busca no LinkedIn: ' . $error);
    }

    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($statusCode < 200 || $statusCode >= 300) {
        throw new Exception('Falha ao iniciar busca no LinkedIn.');
    }

    $data = json_decode($response, true);
    if (!isset($data['taskId'])) {
        throw new Exception('Resposta inválida ao iniciar busca no LinkedIn.');
    }

    $taskId = $data['taskId'];
    $maxAttempts = 10;
    $attempt = 0;

    do {
        $attempt++;
        $ch = curl_init($baseUrl . '/status/' . urlencode($taskId));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_filter([
            'Accept: application/json',
            $token ? 'Authorization: Bearer ' . $token : null,
        ]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            if ($attempt >= $maxAttempts) {
                throw new Exception('Erro ao verificar status no LinkedIn: ' . $error);
            }
            sleep(2);
            continue;
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($statusCode < 200 || $statusCode >= 300) {
            if ($attempt >= $maxAttempts) {
                throw new Exception('Falha ao verificar status no LinkedIn.');
            }
            sleep(2);
            continue;
        }

        $data = json_decode($response, true);
        if (isset($data['status']) && $data['status'] === 'completed') {
            $contacts = [];
            if (!empty($data['results']) && is_array($data['results'])) {
                foreach ($data['results'] as $contact) {
                    if (isset($contact['title'], $contact['link'])) {
                        $contacts[] = [
                            'title' => $contact['title'],
                            'link'  => $contact['link'],
                        ];
                    }
                }
            }
            return $contacts;
        }

        if (isset($data['status']) && $data['status'] === 'error') {
            throw new Exception('Busca no LinkedIn retornou erro.');
        }

        sleep(2);
    } while ($attempt < $maxAttempts);

    throw new Exception('Timeout ao aguardar resultados do LinkedIn.');
}
?>

