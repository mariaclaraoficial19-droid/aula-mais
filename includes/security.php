<?php
/**
 * Módulo de Segurança e Utilitários Globais — Sistema Aula+ (IFRO)
 * 
 * Implementa os Requisitos Não Funcionais:
 * - RNF01: Hashing e verificação de senhas
 * - RNF02: Validação e sanitização no servidor
 * - RNF04: Prevenção contra Cross-Site Scripting (XSS)
 * - RNF05: Proteção contra Cross-Site Request Forgery (CSRF)
 * - RNF06: Gerenciamento seguro de sessão
 */

declare(strict_types=1);

/**
 * Inicializa a sessão com configurações seguras de cookies caso ainda não esteja ativa.
 */
function iniciarSessaoSegura(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        // Configurações de cookies para segurança máxima (RNF06)
        $cookieParams = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => 0, // Expira ao fechar o navegador
            'path'     => $cookieParams['path'] ?? '/',
            'domain'   => $cookieParams['domain'] ?? '',
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true, // Impede acesso aos cookies via JavaScript
            'samesite' => 'Lax' // Proteção contra ataques CSRF vindos de sites externos
        ]);
        session_start();
    }
}

// Inicia automaticamente a sessão segura
iniciarSessaoSegura();

/**
 * Escapa strings para saída segura em HTML, prevenindo ataques XSS (RNF04).
 *
 * @param mixed $valor Texto ou dado a ser renderizado.
 * @return string Texto tratado e seguro para HTML.
 */
function e(mixed $valor): string
{
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Retorna ou gera o token CSRF atual da sessão (RNF05).
 */
function getCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Renderiza um campo oculto HTML contendo o token CSRF para formulários POST.
 */
function campoCsrf(): string
{
    $token = getCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

/**
 * Valida o token CSRF recebido em requisições POST.
 *
 * @param string|null $tokenRecebido Token vindo de $_POST['csrf_token'].
 * @return bool True se o token for válido e corresponder à sessão atual.
 */
function validarCsrf(?string $tokenRecebido): bool
{
    if (empty($tokenRecebido) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $tokenRecebido);
}

/**
 * Define uma mensagem temporária (Flash Message) para exibição na próxima página.
 *
 * @param string $tipo 'sucesso', 'erro', ou 'aviso'.
 * @param string $mensagem Texto a ser apresentado ao usuário.
 */
function setFlashMessage(string $tipo, string $mensagem): void
{
    $_SESSION['flash_messages'][] = [
        'tipo'     => $tipo,
        'mensagem' => $mensagem
    ];
}

/**
 * Recupera e limpa todas as mensagens flash acumuladas.
 *
 * @return array Lista de mensagens.
 */
function getFlashMessages(): array
{
    $mensagens = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $mensagens;
}

/**
 * Verifica se há um usuário autenticado na sessão.
 */
function estaAutenticado(): bool
{
    return !empty($_SESSION['usuario_id']);
}

/**
 * Retorna os dados do usuário logado ou null se for visitante.
 */
function getUsuarioLogado(): ?array
{
    if (!estaAutenticado()) {
        return null;
    }
    return [
        'id'     => (int)$_SESSION['usuario_id'],
        'nome'   => (string)($_SESSION['usuario_nome'] ?? ''),
        'email'  => (string)($_SESSION['usuario_email'] ?? ''),
        'perfil' => (string)($_SESSION['usuario_perfil'] ?? 'aluno'),
    ];
}

/**
 * Exige autenticação para prosseguir; redireciona para login se for visitante.
 *
 * @param string $urlRedirecionamento URL base caso precise voltar.
 */
function exigirAutenticacao(?string $urlRedirecionamento = null): void
{
    if (!estaAutenticado()) {
        $destino = $urlRedirecionamento ?: (getBasePath() . '/auth/login.php');
        setFlashMessage('aviso', 'Você precisa fazer login para acessar esta página.');
        header('Location: ' . $destino);
        exit;
    }
}

/**
 * Exige perfil específico de usuário (ex: 'professor'). Bloqueia com 403 se não autorizado.
 *
 * @param string $perfilEsperado Perfil exigido para a ação.
 */
function exigirPerfil(string $perfilEsperado): void
{
    exigirAutenticacao();
    $usuario = getUsuarioLogado();

    if (!$usuario || $usuario['perfil'] !== $perfilEsperado) {
        http_response_code(403);
        setFlashMessage('erro', 'Acesso negado: esta ação é exclusiva para professores.');
        header('Location: ' . getBasePath() . '/dashboard/index.php');
        exit;
    }
}

/**
 * Resolve dinamicamente o caminho base da aplicação considerando o diretório de execução.
 * Suporta XAMPP em subpastas, servidores com virtual hosts e o servidor embutido do PHP.
 */
function getBasePath(): string
{
    static $basePath = null;
    if ($basePath !== null) {
        return $basePath;
    }

    $docRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: '') : '';
    $appRoot = str_replace('\\', '/', realpath(dirname(__DIR__)) ?: '');

    if ($docRoot !== '' && str_starts_with($appRoot, $docRoot)) {
        $relative = trim(substr($appRoot, strlen($docRoot)), '/');
        $basePath = ($relative !== '') ? '/' . $relative : '';
        return $basePath;
    }

    // Fallback para quando o projeto roda via Symlink/Junction no Apache
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($scriptDir !== '/' && $scriptDir !== '.' && $scriptDir !== '') {
        $cleanDir = preg_replace('#/(auth|materias|materiais|dashboard|config|includes)$#', '', $scriptDir);
        $basePath = rtrim($cleanDir, '/');
        return $basePath;
    }

    $basePath = '';
    return $basePath;
}
