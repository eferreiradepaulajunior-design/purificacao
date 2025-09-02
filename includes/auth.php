<?php
// ARQUIVO: includes/auth.php
// Funções para registro, login e gerenciamento de sessão.

// Inicia a sessão em todas as páginas que incluírem este arquivo
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Registra um novo usuário no banco de dados.
 * @param PDO $pdo Conexão com o banco de dados.
 * @param string $username Nome do usuário.
 * @param string $password Senha.
 * @return bool|string Retorna true em caso de sucesso, ou uma string de erro em caso de falha.
 */
function register_user($pdo, $username, $password) {
    if (empty($username) || empty($password)) {
        return "Usuário e senha não podem estar vazios.";
    }

    // Verificar se o usuário já existe
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        return "Este nome de usuário já está em uso.";
    }

    // Criptografar a senha
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Inserir no banco de dados
    $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    if ($stmt->execute([$username, $hashed_password])) {
        return true;
    } else {
        return "Erro ao registrar o usuário. Tente novamente.";
    }
}

/**
 * Tenta logar um usuário.
 * @param PDO $pdo Conexão com o banco de dados.
 * @param string $username Nome do usuário.
 * @param string $password Senha.
 * @return bool True se o login for bem-sucedido, false caso contrário.
 */
function login_user($pdo, $username, $password) {
    $stmt = $pdo->prepare("SELECT id, password FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Senha correta, iniciar sessão
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $username;
        return true;
    }

    return false;
}

/**
 * Verifica se o usuário está logado.
 * Se não estiver, redireciona para a página de login.
 */
function check_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}
?>