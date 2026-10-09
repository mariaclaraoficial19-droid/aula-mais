<?php
/**
 * Exclusão de Material de Estudo — Exclusivo para o Professor Autor — Sistema Aula+ (IFRO)
 * 
 * Implementa:
 * - RF10: Exclusão autorizada mediante confirmação
 * - RNF02: Validação no servidor
 * - RNF03: Prepared Statements via PDO
 * - RNF05: Validação obrigatória de Token CSRF
 * - RNF06: Verificação de autorização (apenas o professor autor)
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/database.php';

// Exige que o usuário seja professor
exigirPerfil('professor');

$usuario = getUsuarioLogado();
$basePath = getBasePath();

// Permite exclusivamente requisições POST para evitar exclusões acidentais via links GET
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    setFlashMessage('erro', 'Método não permitido para esta operação.');
    header('Location: ' . $basePath . '/dashboard/index.php');
    exit;
}

// 1. Validação de CSRF (RNF05)
if (!validarCsrf($_POST['csrf_token'] ?? null)) {
    setFlashMessage('erro', 'Falha na validação de segurança (token CSRF inválido). Ação cancelada.');
    header('Location: ' . $basePath . '/dashboard/index.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    setFlashMessage('erro', 'Identificador de material inválido.');
    header('Location: ' . $basePath . '/dashboard/index.php');
    exit;
}

try {
    $pdo = getDatabaseConnection();

    // 2. Verifica se o material existe e se o usuário logado é o autor (RNF06)
    $stmtCheck = $pdo->prepare("SELECT id, titulo, usuario_id FROM materiais WHERE id = :id LIMIT 1");
    $stmtCheck->execute([':id' => $id]);
    $material = $stmtCheck->fetch();

    if (!$material) {
        setFlashMessage('erro', 'Material não encontrado para exclusão.');
        header('Location: ' . $basePath . '/dashboard/index.php');
        exit;
    }

    if ((int)$material['usuario_id'] !== (int)$usuario['id']) {
        http_response_code(403);
        setFlashMessage('erro', 'Acesso negado: você não tem permissão para excluir materiais de outros professores.');
        header('Location: ' . $basePath . '/dashboard/index.php');
        exit;
    }

    // 3. Executa a exclusão com Prepared Statement seguro
    $stmtDelete = $pdo->prepare("DELETE FROM materiais WHERE id = :id AND usuario_id = :usuario_id");
    $stmtDelete->execute([
        ':id'         => $id,
        ':usuario_id' => $usuario['id']
    ]);

    setFlashMessage('sucesso', 'O material "' . $material['titulo'] . '" foi excluído com sucesso.');
} catch (Throwable $e) {
    error_log("Erro ao excluir material #$id: " . $e->getMessage());
    setFlashMessage('erro', 'Ocorreu um erro ao tentar excluir o material do banco de dados.');
}

header('Location: ' . $basePath . '/dashboard/index.php');
exit;
