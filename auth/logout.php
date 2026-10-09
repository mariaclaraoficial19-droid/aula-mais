<?php
/**
 * Encerramento de Sessão (Logout) — Sistema Aula+ (IFRO)
 * 
 * Implementa:
 * - RF03: Logout com limpeza e destruição total da sessão
 * - RNF06: Invalidação de cookies de sessão
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';

$basePath = getBasePath();

// Limpa todas as variáveis da sessão
$_SESSION = [];

// Invalida o cookie de sessão no navegador
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destrói a sessão no servidor
session_destroy();

// Inicia uma nova sessão limpa apenas para exibir a mensagem flash de confirmação
session_start();
$_SESSION['flash_messages'][] = [
    'tipo'     => 'sucesso',
    'mensagem' => 'Sua sessão foi encerrada com segurança. Até breve!'
];

header('Location: ' . $basePath . '/auth/login.php');
exit;
