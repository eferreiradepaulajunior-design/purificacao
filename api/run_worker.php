<?php
// ARQUIVO: api/run_worker.php
// Versão final: Agenda uma tarefa na fila de jobs.

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

check_login();

try {
    // Verifica se já existe uma tarefa pendente para não criar duplicatas
    $stmt = $pdo->prepare("SELECT id FROM jobs WHERE status = 'pending' AND job_type = 'sync_and_enrich'");
    $stmt->execute();

    if ($stmt->fetch()) {
        // Se já existe, apenas informa o usuário.
        echo json_encode([
            'success' => true,
            'message' => 'Uma sincronização já está na fila para ser executada. Os resultados aparecerão em breve.'
        ]);
    } else {
        // Se não existe, cria uma nova tarefa na tabela 'jobs'
        $stmt = $pdo->prepare("INSERT INTO jobs (job_type, status) VALUES ('sync_and_enrich', 'pending')");
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Sincronização agendada com sucesso! O sistema irá processar em segundo plano nos próximos minutos.'
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao agendar a tarefa: ' . $e->getMessage()
    ]);
}
