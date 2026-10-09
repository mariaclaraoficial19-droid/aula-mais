<?php
/**
 * Controlador de Geração de Resumo por Inteligência Artificial — Sistema Aula+ (IFRO)
 * 
 * Implementa:
 * - RF11: Geração de resumo com IA baseado no material de estudo
 * - RF12: Tratamento de indisponibilidade e falhas da API sem interromper o sistema
 * - RNF05: Proteção por token CSRF
 * - RNF06: Verificação de autenticação e autorização no servidor
 * - RNF08: Desacoplamento e resiliência da IA
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/gemini_service.php';

// 1. Exige autenticação de usuário (Requisito 4)
exigirAutenticacao();

$usuario = getUsuarioLogado();
$basePath = getBasePath();

// Permite exclusivamente requisições POST para evitar acionamentos acidentais via links GET
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    setFlashMessage('erro', 'Método inválido para solicitação de resumo.');
    header('Location: ' . $basePath . '/materiais/index.php');
    exit;
}

// 2. Validação do Token CSRF (RNF05)
if (!validarCsrf($_POST['csrf_token'] ?? null)) {
    setFlashMessage('erro', 'Falha na validação de segurança (token CSRF inválido ou expirado). Tente novamente.');
    header('Location: ' . $basePath . '/materiais/index.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    setFlashMessage('erro', 'Material não identificado.');
    header('Location: ' . $basePath . '/materiais/index.php');
    exit;
}

try {
    $pdo = getDatabaseConnection();

    // 3. Busca o material com Prepared Statement seguro (RNF03)
    $stmt = $pdo->prepare("SELECT id, titulo, assunto, descricao, conteudo, resumo_ia, usuario_id FROM materiais WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $material = $stmt->fetch();

    if (!$material) {
        setFlashMessage('erro', 'Material não encontrado no banco de dados.');
        header('Location: ' . $basePath . '/materiais/index.php');
        exit;
    }

    $ehAutor = ((int)$material['usuario_id'] === (int)$usuario['id']);
    $jaPossuiResumo = !empty($material['resumo_ia']);

    // 4. Verificação de Permissão no Servidor (Requisito 7)
    // Se o resumo já existe, apenas o professor autor pode solicitar a regeneração.
    // Se ainda não existir resumo, qualquer usuário autenticado (aluno ou professor) pode solicitar a geração inicial.
    if ($jaPossuiResumo && !$ehAutor) {
        setFlashMessage('aviso', 'Este material já possui um resumo gerado. Apenas o professor autor pode solicitar a regeneração do conteúdo.');
        header('Location: ' . $basePath . '/materiais/visualizar.php?id=' . $id);
        exit;
    }

    // 5. Aciona o Serviço de IA do Google Gemini (Sem envio de dados pessoais - Requisito 8)
    $resultado = gerarResumoGemini(
        (string)$material['titulo'],
        (string)$material['assunto'],
        (string)$material['descricao'],
        $material['conteudo'] !== null ? (string)$material['conteudo'] : null
    );

    // 6. Tratamento de Sucesso ou Falha Resiliente (RF11, RF12 e Requisito 6)
    if ($resultado['sucesso'] && !empty($resultado['resumo'])) {
        // Salva o resumo no banco de dados somente após uma resposta válida (Requisito 7)
        $stmtUpdate = $pdo->prepare("UPDATE materiais SET resumo_ia = :resumo_ia WHERE id = :id");
        $stmtUpdate->execute([
            ':resumo_ia' => $resultado['resumo'],
            ':id'        => $id
        ]);

        setFlashMessage('sucesso', 'Resumo educacional gerado com sucesso pela Inteligência Artificial!');
    } else {
        // Tratamento gracioso: não quebra a página nem corrompe o material existente
        setFlashMessage('aviso', $resultado['erro'] ?? 'O serviço de IA está temporariamente indisponível. O material continua acessível normalmente.');
    }

} catch (Throwable $e) {
    error_log('Erro ao processar solicitação de resumo por IA: ' . $e->getMessage());
    setFlashMessage('aviso', 'Ocorreu uma falha temporária ao comunicar com o banco de dados. O material permanece disponível para leitura.');
}

// Redireciona de volta para a tela do material
header('Location: ' . $basePath . '/materiais/visualizar.php?id=' . $id);
exit;
