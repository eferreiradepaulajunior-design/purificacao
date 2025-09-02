<?php
// ARQUIVO: index.php
// Página inicial que explica o sistema e direciona para o login.
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bem-vindo à Plataforma de Enriquecimento de Clientes</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex flex-col items-center justify-center min-h-screen p-6">
        <div class="max-w-3xl w-full bg-white rounded-xl shadow-lg p-8 md:p-12 text-center">
            
            <h1 class="text-3xl md:text-4xl font-bold text-gray-800 mb-4">
                Plataforma de Enriquecimento de Clientes
            </h1>
            
            <p class="text-gray-600 text-base md:text-lg mb-8 leading-relaxed">
                Nossa solução automatiza a busca e o armazenamento de dados de clientes. O sistema enriquece essas informações com dados públicos da web, como perfis sociais e informações de contato, e as disponibiliza de forma organizada através de uma API segura para integração com seus outros sistemas.
            </p>
            
            <a href="login.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg text-lg transition duration-300 ease-in-out transform hover:scale-105 focus:outline-none focus:ring-4 focus:ring-blue-300">
                Acessar a Plataforma
            </a>
            
        </div>
        <footer class="mt-8 text-center text-gray-500 text-sm">
            <p>&copy; <?= date('Y') ?> CRV Purificação. Todos os direitos reservados.</p>
        </footer>
    </div>
</body>
</html>
