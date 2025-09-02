<?php
// ARQUIVO: login.php
// Página de Login e Cadastro.

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$login_error = '';
$register_message = '';
$register_error = '';

// Processar formulário de registro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $result = register_user($pdo, $username, $password);
    if ($result === true) {
        $register_message = "Usuário registrado com sucesso! Você já pode fazer o login.";
    } else {
        $register_error = $result;
    }
}

// Processar formulário de login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if (login_user($pdo, $username, $password)) {
        header('Location: dashboard.php');
        exit;
    } else {
        $login_error = 'Usuário ou senha inválidos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Cliente IA</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">
    <div class="w-full max-w-md">
        <div class="bg-white shadow-md rounded-lg px-8 pt-6 pb-8 mb-4">
            <h1 class="text-3xl font-bold text-center text-gray-800 mb-6">Cliente IA</h1>
            
            <!-- Formulário de Login -->
            <form method="POST" action="login.php" class="mb-4">
                <h2 class="text-xl text-center text-gray-700 mb-4">Login</h2>
                <?php if ($login_error): ?>
                    <p class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4"><?= htmlspecialchars($login_error) ?></p>
                <?php endif; ?>
                 <?php if ($register_message): ?>
                    <p class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4"><?= htmlspecialchars($register_message) ?></p>
                <?php endif; ?>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="login-username">Usuário</label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="login-username" name="username" type="text" required>
                </div>
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="login-password">Senha</label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline" id="login-password" name="password" type="password" required>
                </div>
                <div class="flex items-center justify-between">
                    <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" type="submit" name="login">
                        Entrar
                    </button>
                </div>
            </form>
            
            <hr class="my-6">

            <!-- Formulário de Cadastro -->
            <form method="POST" action="login.php">
                 <h2 class="text-xl text-center text-gray-700 mb-4">Não tem uma conta? Cadastre-se</h2>
                 <?php if ($register_error): ?>
                    <p class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4"><?= htmlspecialchars($register_error) ?></p>
                <?php endif; ?>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="register-username">Novo Usuário</label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="register-username" name="username" type="text" required>
                </div>
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="register-password">Nova Senha</label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline" id="register-password" name="password" type="password" required>
                </div>
                <div class="flex items-center justify-between">
                    <button class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" type="submit" name="register">
                        Registrar
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
