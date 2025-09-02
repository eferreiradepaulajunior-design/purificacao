<?php
// ARQUIVO: webhooks_docs.php
// Página de documentação com UI/UX moderna.

require_once __DIR__ . '/includes/auth.php';
check_login();

$baseUrl = "http" . (isset($_SERVER['HTTPS']) ? "s" : "") . "://" . $_SERVER['HTTP_HOST'];
$path = rtrim(dirname($_SERVER['PHP_SELF']), '/');
$webhookUrl = $baseUrl . $path . '/api/outbound_webhook.php';
?>
<!DOCTYPE html>
<html lang="pt-BR" class="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentação do Webhook - Cliente IA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 font-sans transition-colors duration-300">

<div class="flex min-h-screen">
    <!-- Sidebar (Desktop) -->
    <aside class="w-64 bg-white dark:bg-gray-800 shadow-md hidden lg:flex flex-col">
        <div class="p-6"><h1 class="text-2xl font-bold text-blue-600 dark:text-blue-400">Cliente IA</h1></div>
        <nav class="flex-1 px-4 space-y-2">
            <a href="dashboard.php" class="flex items-center px-4 py-2 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg">
                <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                Dashboard
            </a>
            <a href="settings.php" class="flex items-center px-4 py-2 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg">
                <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                Configurações
            </a>
            <a href="webhooks_docs.php" class="flex items-center px-4 py-2 text-gray-700 dark:text-gray-200 bg-gray-200 dark:bg-gray-700 rounded-lg">
                <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Webhooks
            </a>
        </nav>
        <div class="p-4 mt-auto">
            <a href="logout.php" class="flex items-center px-4 py-2 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg">
                <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                Sair
            </a>
        </div>
    </aside>
        <!-- Main Content -->
        <main class="flex-1 p-8 overflow-y-auto">
            <h2 class="text-3xl font-semibold text-gray-800 mb-6">Documentação do Webhook de Saída</h2>

            <div class="bg-white p-8 rounded-lg shadow-md prose max-w-none">
                <p>
                    Utilize o endpoint abaixo para consultar os dados de um cliente já enriquecido em seu sistema.
                    A consulta é feita através do CNPJ do cliente.
                </p>

                <h3 class="mt-6">Endpoint</h3>
                <pre class="bg-gray-200 p-4 rounded-md text-sm"><code>GET <?= htmlspecialchars($webhookUrl) ?></code></pre>

                <h3 class="mt-6">Parâmetros da Query</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="p-2 text-left">Parâmetro</th>
                                <th class="p-2 text-left">Tipo</th>
                                <th class="p-2 text-left">Obrigatório</th>
                                <th class="p-2 text-left">Descrição</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-b">
                                <td class="p-2"><code>cnpj</code></td>
                                <td class="p-2">string</td>
                                <td class="p-2 text-center">Sim</td>
                                <td class="p-2">O CNPJ do cliente que deseja consultar. (Apenas números)</td>
                            </tr>
                            <tr class="border-b">
                                <td class="p-2"><code>apiKey</code></td>
                                <td class="p-2">string</td>
                                <td class="p-2 text-center">Sim</td>
                                <td class="p-2">Sua chave de API para autenticação. <strong>(Implementação futura)</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <h3 class="mt-6">Exemplo de Requisição (cURL)</h3>
                <pre class="bg-gray-200 p-4 rounded-md text-sm"><code>curl -X GET "<?= htmlspecialchars($webhookUrl) ?>?cnpj=12345678000199"</code></pre>

                <h3 class="mt-6">Exemplo de Resposta de Sucesso (200 OK)</h3>
                <pre class="bg-gray-200 p-4 rounded-md text-sm"><code>{
    "id": 1,
    "external_id": 45,
    "cnpj": "12345678000199",
    "nomeEmpresa": "CARGRAPHICS GRÁFICA E EDITORA LTDA.",
    "nomeFantasia": "CARGRAPHICS",
    "cep": "01001-000",
    "logradouro": "Rua das Artes",
    "numero": "123",
    "complemento": "Sala 5",
    "uf": "SP",
    "cidade": "São Paulo",
    "bairro": "Centro",
    "ramoAtividade": "1813-0/01 - Impressão de material gráfico",
    "status": "enriched",
    "created_at": "2025-08-02 20:45:00",
    "updated_at": "2025-08-02 20:46:10",
    "contatos": [
        {
            "contato": "João Silva",
            "info": "joao@email.com",
            "tipo": "E-mail"
        }
    ],
    "dados_enriquecidos": {
        "google_rating": 4.5,
        "website": "https://www.empresaexemplo.com.br",
        "main_phone": "(11) 99999-8888",
        "social_profiles": {
            "linkedin": "https://linkedin.com/company/empresa-exemplo"
        }
    }
}</code></pre>

                <h3 class="mt-6">Resposta de Erro (404 Not Found)</h3>
                <pre class="bg-gray-200 p-4 rounded-md text-sm"><code>{
    "error": "Cliente não encontrado ou não enriquecido."
}</code></pre>
            </div>
        </main>
    </div>
    <!-- Bottom Navigation (Mobile) -->
    <nav class="lg:hidden fixed bottom-0 left-0 right-0 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 flex justify-around">
        <a href="dashboard.php" class="flex-1 flex flex-col items-center justify-center p-2 text-gray-500 dark:text-gray-400">
            <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
            <span class="text-xs">Dashboard</span>
        </a>
        <a href="settings.php" class="flex-1 flex flex-col items-center justify-center p-2 text-gray-500 dark:text-gray-400">
            <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
            <span class="text-xs">Ajustes</span>
        </a>
        <a href="webhooks_docs.php" class="flex-1 flex flex-col items-center justify-center p-2 text-blue-600 dark:text-blue-400">
            <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span class="text-xs">Webhooks</span>
        </a>
        <a href="logout.php" class="flex-1 flex flex-col items-center justify-center p-2 text-gray-500 dark:text-gray-400">
            <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
            <span class="text-xs">Sair</span>
        </a>
    </nav>
</div>

<script src="assets/js/dashboard.js"></script>
</body>
</html>