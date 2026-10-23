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

/**
 * Valida o arquivo PDF enviado por upload para materiais de estudo.
 *
 * Regras implementadas:
 * - Opcional se não enviado (UPLOAD_ERR_NO_FILE)
 * - Tamanho máximo: 10 MB (10.485.760 bytes)
 * - Extensão obrigatória: .pdf (case-insensitive)
 * - MIME type: application/pdf verificado via finfo
 * - Assinatura do arquivo: cabeçalho mágico %PDF-
 *
 * @param array|null $arquivo Vetor vindo de $_FILES['arquivo_pdf']
 * @return array ['tem_arquivo' => bool, 'valido' => bool, 'erro' => string|null, 'nome_original' => string, 'tamanho' => int]
 */
function validarUploadPdf(?array $arquivo): array
{
    if (!$arquivo || !isset($arquivo['error']) || $arquivo['error'] === UPLOAD_ERR_NO_FILE) {
        return [
            'tem_arquivo'   => false,
            'valido'        => true,
            'erro'          => null,
            'nome_original' => '',
            'tamanho'       => 0,
        ];
    }

    // Tratamento de erros de upload do próprio PHP
    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        $msgErro = match ($arquivo['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'O arquivo PDF enviado excede o limite máximo permitido de 10 MB.',
            UPLOAD_ERR_PARTIAL    => 'O upload do arquivo foi interrompido antes de ser concluído. Tente novamente.',
            UPLOAD_ERR_NO_TMP_DIR => 'Falha interna do servidor: diretório temporário não configurado.',
            UPLOAD_ERR_CANT_WRITE => 'Falha interna do servidor: não foi possível gravar o arquivo temporário no disco.',
            default               => 'Ocorreu um erro inesperado no envio do arquivo PDF (Código: ' . $arquivo['error'] . ').',
        };
        return [
            'tem_arquivo'   => true,
            'valido'        => false,
            'erro'          => $msgErro,
            'nome_original' => (string)($arquivo['name'] ?? ''),
            'tamanho'       => (int)($arquivo['size'] ?? 0),
        ];
    }

    // 1. Limite estrito de 10 MB (10 * 1024 * 1024 = 10.485.760 bytes)
    $limiteMaximoBytes = 10 * 1024 * 1024;
    $tamanho = (int)($arquivo['size'] ?? 0);

    if ($tamanho <= 0) {
        return [
            'tem_arquivo'   => true,
            'valido'        => false,
            'erro'          => 'O arquivo enviado está vazio (0 bytes).',
            'nome_original' => (string)($arquivo['name'] ?? ''),
            'tamanho'       => $tamanho,
        ];
    }

    if ($tamanho > $limiteMaximoBytes) {
        return [
            'tem_arquivo'   => true,
            'valido'        => false,
            'erro'          => 'O arquivo PDF excede o tamanho máximo de 10 MB (tamanho enviado: ' . formatarTamanhoBytes($tamanho) . ').',
            'nome_original' => (string)($arquivo['name'] ?? ''),
            'tamanho'       => $tamanho,
        ];
    }

    // 2. Validação da extensão (.pdf)
    $nomeOriginal = (string)($arquivo['name'] ?? '');
    $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
    if ($extensao !== 'pdf') {
        return [
            'tem_arquivo'   => true,
            'valido'        => false,
            'erro'          => 'Formato de arquivo inválido. Apenas documentos com extensão .pdf são aceitos.',
            'nome_original' => $nomeOriginal,
            'tamanho'       => $tamanho,
        ];
    }

    // 3. Validação real do MIME Type via finfo
    $tmpPath = (string)($arquivo['tmp_name'] ?? '');
    if (!is_uploaded_file($tmpPath)) {
        return [
            'tem_arquivo'   => true,
            'valido'        => false,
            'erro'          => 'Tentativa inválida de upload de arquivo.',
            'nome_original' => $nomeOriginal,
            'tamanho'       => $tamanho,
        ];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? finfo_file($finfo, $tmpPath) : false;
    if ($finfo) {
        finfo_close($finfo);
    }

    if ($mimeType !== 'application/pdf') {
        return [
            'tem_arquivo'   => true,
            'valido'        => false,
            'erro'          => 'O conteúdo do arquivo não foi identificado como um PDF válido (tipo detectado: ' . ($mimeType ?: 'desconhecido') . ').',
            'nome_original' => $nomeOriginal,
            'tamanho'       => $tamanho,
        ];
    }

    // 4. Validação da Assinatura do Arquivo (Magic Bytes %PDF-)
    $handle = @fopen($tmpPath, 'rb');
    if (!$handle) {
        return [
            'tem_arquivo'   => true,
            'valido'        => false,
            'erro'          => 'Não foi possível ler o arquivo enviado para verificação de integridade.',
            'nome_original' => $nomeOriginal,
            'tamanho'       => $tamanho,
        ];
    }

    $assinatura = fread($handle, 5);
    fclose($handle);

    if ($assinatura !== '%PDF-') {
        return [
            'tem_arquivo'   => true,
            'valido'        => false,
            'erro'          => 'O arquivo enviado não possui o cabeçalho oficial de um documento PDF (%PDF-).',
            'nome_original' => $nomeOriginal,
            'tamanho'       => $tamanho,
        ];
    }

    // Sanitiza o nome original para exibição e download seguro
    $nomeOriginalSanitizado = preg_replace('/[^\p{L}\p{N}\s._\-\(\)]/u', '_', $nomeOriginal);
    if (empty($nomeOriginalSanitizado)) {
        $nomeOriginalSanitizado = 'material.pdf';
    }

    return [
        'tem_arquivo'   => true,
        'valido'        => true,
        'erro'          => null,
        'nome_original' => $nomeOriginalSanitizado,
        'tamanho'       => $tamanho,
    ];
}

/**
 * Salva um arquivo PDF validado no diretório seguro de uploads com nome criptograficamente aleatório.
 *
 * @param array $arquivo Vetor vindo de $_FILES['arquivo_pdf']
 * @return string Nome físico único gerado (ex: pdf_xxx.pdf)
 */
function salvarUploadPdf(array $arquivo): string
{
    $dir = __DIR__ . '/../uploads/materiais';
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("Não foi possível criar o diretório de uploads no servidor.");
        }
    }

    $nomeFisico = 'pdf_' . bin2hex(random_bytes(16)) . '.pdf';
    $destino = $dir . DIRECTORY_SEPARATOR . $nomeFisico;

    if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
        throw new RuntimeException("Falha ao mover o arquivo enviado para o diretório de armazenamento.");
    }

    return $nomeFisico;
}

/**
 * Remove com segurança um arquivo PDF físico do disco (evita arquivos órfãos).
 *
 * @param string|null $nomeFisico Nome do arquivo físico
 */
function excluirArquivoPdfFisico(?string $nomeFisico): void
{
    if (empty($nomeFisico)) {
        return;
    }

    $nomeLimpo = basename($nomeFisico);
    $caminho = __DIR__ . '/../uploads/materiais' . DIRECTORY_SEPARATOR . $nomeLimpo;

    if (file_exists($caminho) && is_file($caminho)) {
        @unlink($caminho);
    }
}

/**
 * Formata um valor em bytes para exibição amigável (ex: 2.5 MB, 340 KB).
 *
 * @param int|null $bytes Tamanho em bytes
 * @return string Texto formatado
 */
function formatarTamanhoBytes(?int $bytes): string
{
    if ($bytes === null || $bytes <= 0) {
        return '0 B';
    }

    $unidades = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $tamanho = (float)$bytes;

    while ($tamanho >= 1024 && $i < count($unidades) - 1) {
        $tamanho /= 1024;
        $i++;
    }

    return number_format($tamanho, ($i === 0 ? 0 : 1), ',', '.') . ' ' . $unidades[$i];
}
