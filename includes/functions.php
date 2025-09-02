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
    $searchQuery = urlencode($clientData['nomeEmpresa'] . " " . $clientData['cidade']);
    // $url = "https://maps.googleapis.com/maps/api/place/textsearch/json?query={$searchQuery}&key={$apiKey}";
    // $response = file_get_contents($url);
    // $googleData = json_decode($response, true);

    // Retornar dados simulados por enquanto
    return [
        'google_rating' => 4.5,
        'website' => 'https://www.empresaexemplo.com.br',
        'main_phone' => '(11) 99999-8888'
    ];
}

/**
 * Placeholder para função de enriquecimento com um serviço de terceiros (Ex: Hunter.io).
 */
function enrichWithThirdParty($clientData, $apiKey) {
    // Lógica para chamar a API do serviço de enriquecimento...
    // $url = "https://api.hunter.io/v2/company-search?domain=" . urlencode(parse_url($clientData['website'], PHP_URL_HOST));
    // ...
    return [
        'social_profiles' => [
            'linkedin' => 'https://linkedin.com/company/empresa-exemplo',
            'facebook' => 'https://facebook.com/empresaexemplo'
        ],
        'key_personnel' => [
            ['name' => 'Maria Souza', 'role' => 'CEO'],
            ['name' => 'Carlos Pereira', 'role' => 'CTO']
        ]
    ];
}

/**
 * Enriquecimento de contatos via LinkedIn usando SerpAPI.
 * Retorna um array de contatos encontrados.
 */
function enrichWithLinkedIn($clientData, $sector, $serpApiKey) {
    if (empty($serpApiKey)) {
        error_log('Chave SerpAPI ausente para enriquecimento do LinkedIn.');
        return [];
    }

    $query = urlencode($clientData['nomeEmpresa'] . ' "' . $sector . '" site:linkedin.com/in');
    $url   = "https://serpapi.com/search.json?engine=google&q={$query}&api_key={$serpApiKey}";

    $response = @file_get_contents($url);
    if ($response === false) {
        error_log("Falha na requisição à SerpAPI para {$clientData['nomeEmpresa']} ({$sector}).");
        return [];
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($data['organic_results'])) {
        return [];
    }

    $contacts = [];
    foreach ($data['organic_results'] as $result) {
        $title = $result['title'] ?? '';
        $link  = $result['link'] ?? '';
        if ($title && $link) {
            $contacts[] = ['name' => $title, 'linkedin' => $link];
        }
    }

    return $contacts;
=======
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