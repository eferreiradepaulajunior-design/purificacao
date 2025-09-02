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
}
?>