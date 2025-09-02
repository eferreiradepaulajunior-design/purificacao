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
