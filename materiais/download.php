<?php
/**
 * Rota Segura para Download e Visualização de PDFs de Estudo — Sistema Aula+ (IFRO)
 * 
 * Implementa:
 * - RNF02: Validação rigorosa de parâmetros
 * - RNF03: Prepared Statements via PDO
 * - RNF04: Proteção contra Directory Traversal e injeção de cabeçalhos
 * - RNF07: Tratamento seguro de exceções
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/database.php';

$basePath = getBasePath();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    setFlashMessage('erro', 'Identificador de material inválido.');
    header('Location: ' . $basePath . '/materiais/index.php');
    exit;
}

try {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare("
        SELECT id, titulo, arquivo_pdf, arquivo_pdf_nome_original, arquivo_pdf_tamanho 
        FROM materiais 
        WHERE id = :id 
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $material = $stmt->fetch();

    if (!$material || empty($material['arquivo_pdf'])) {
        http_response_code(404);
        setFlashMessage('erro', 'Este material não possui um anexo em PDF disponível para visualização.');
        header('Location: ' . $basePath . '/materiais/index.php');
        exit;
    }

    $uploadDir = realpath(__DIR__ . '/../uploads/materiais');
    if ($uploadDir === false) {
        http_response_code(500);
        setFlashMessage('erro', 'Diretório de armazenamento inacessível.');
        header('Location: ' . $basePath . '/materiais/visualizar.php?id=' . $id);
        exit;
    }

    // Proteção estrita contra Path Traversal usando basename
    $nomeFisico = basename((string)$material['arquivo_pdf']);
    $caminhoArquivo = $uploadDir . DIRECTORY_SEPARATOR . $nomeFisico;
    $realCaminho = realpath($caminhoArquivo);

    // Garante que o arquivo existe e está estritamente dentro da pasta de uploads
    if ($realCaminho === false || !is_file($realCaminho) || !str_starts_with($realCaminho, $uploadDir)) {
        http_response_code(404);
        setFlashMessage('erro', 'O arquivo físico do PDF não foi encontrado no servidor.');
        header('Location: ' . $basePath . '/materiais/visualizar.php?id=' . $id);
        exit;
    }

    // Determina a disposição (inline para abrir no leitor PDF do navegador ou attachment para download direto)
    $forcarDownload = isset($_GET['download']) && $_GET['download'] === '1';
    $disposition = $forcarDownload ? 'attachment' : 'inline';

    $nomeOriginal = (string)($material['arquivo_pdf_nome_original'] ?: 'documento.pdf');
    $nomeHeaderLimpo = str_replace(['"', "\r", "\n"], '', $nomeOriginal);

    // Limpa quaisquer buffers de saída anteriores para não corromper o binário do PDF
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    // Cabeçalhos HTTP de segurança e entrega de PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . $disposition . '; filename="' . $nomeHeaderLimpo . '"; filename*=UTF-8\'\'' . rawurlencode($nomeOriginal));
    header('Content-Length: ' . (string)filesize($realCaminho));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=3600');
    header('Pragma: private');

    readfile($realCaminho);
    exit;

} catch (Throwable $e) {
    error_log("Erro ao servir PDF do material #$id: " . $e->getMessage());
    http_response_code(500);
    setFlashMessage('erro', 'Ocorreu um erro ao processar a abertura do documento PDF.');
    header('Location: ' . $basePath . '/materiais/visualizar.php?id=' . $id);
    exit;
}
