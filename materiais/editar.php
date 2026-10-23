<?php
/**
 * Edição de Material de Estudo — Exclusivo para o Professor Autor — Sistema Aula+ (IFRO)
 *
 * Implementa:
 * - RF09: Edição de dados do material
 * - RNF02: Validação no servidor
 * - RNF03: Prepared Statements via PDO
 * - RNF05: Validação CSRF
 * - RNF06: Verificação de autorização (apenas o autor tem permissão)
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/database.php';

// Exige perfil de professor
exigirPerfil('professor');

$usuario = getUsuarioLogado();
$basePath = getBasePath();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlashMessage('erro', 'Material não especificado.');
    header('Location: ' . $basePath . '/dashboard/index.php');
    exit;
}

$material = null;
$materias = [];
$erros = [];

try {
    $pdo = getDatabaseConnection();

    // Carrega o material garantindo a verificação de autoria (RNF06)
    $stmtMat = $pdo->prepare("SELECT * FROM materiais WHERE id = :id LIMIT 1");
    $stmtMat->execute([':id' => $id]);
    $material = $stmtMat->fetch();

    if (!$material) {
        setFlashMessage('erro', 'Material não encontrado.');
        header('Location: ' . $basePath . '/dashboard/index.php');
        exit;
    }

    // Regra acordada: O professor só pode editar materiais de sua autoria
    if ((int)$material['usuario_id'] !== (int)$usuario['id']) {
        http_response_code(403);
        setFlashMessage('erro', 'Acesso negado: você só tem permissão para editar os materiais criados por você.');
        header('Location: ' . $basePath . '/dashboard/index.php');
        exit;
    }

    // Carrega as matérias para o dropdown
    $stmtListaMat = $pdo->query("SELECT id, nome FROM materias ORDER BY nome ASC");
    $materias = $stmtListaMat->fetchAll();

} catch (Throwable $e) {
    error_log("Erro ao carregar dados para edição: " . $e->getMessage());
    setFlashMessage('erro', 'Erro ao acessar o banco de dados.');
    header('Location: ' . $basePath . '/dashboard/index.php');
    exit;
}

$materiaId   = (int)$material['materia_id'];
$titulo      = (string)$material['titulo'];
$assunto     = (string)$material['assunto'];
$descricao   = (string)$material['descricao'];
$conteudo    = (string)($material['conteudo'] ?? '');
$linkExterno = (string)($material['link_externo'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validação CSRF
    if (!validarCsrf($_POST['csrf_token'] ?? null)) {
        $erros[] = 'Token de segurança CSRF inválido. Tente novamente.';
    }

    $materiaId   = (int)($_POST['materia_id'] ?? 0);
    $titulo      = trim((string)($_POST['titulo'] ?? ''));
    $assunto     = trim((string)($_POST['assunto'] ?? ''));
    $descricao   = trim((string)($_POST['descricao'] ?? ''));
    $conteudo    = trim((string)($_POST['conteudo'] ?? ''));
    $linkExterno = trim((string)($_POST['link_externo'] ?? ''));

    // 2. Validações
    if ($materiaId <= 0) {
        $erros[] = 'Selecione uma matéria válida.';
    }

    if (mb_strlen($titulo) < 3 || mb_strlen($titulo) > 180) {
        $erros[] = 'O título do material deve conter entre 3 e 180 caracteres.';
    }

    if (mb_strlen($assunto) < 2 || mb_strlen($assunto) > 120) {
        $erros[] = 'O assunto deve conter entre 2 e 120 caracteres.';
    }

    if (mb_strlen($descricao) < 5) {
        $erros[] = 'A descrição do material deve conter pelo menos 5 caracteres.';
    }

    if (!empty($linkExterno) && !filter_var($linkExterno, FILTER_VALIDATE_URL)) {
        $erros[] = 'O link externo informado não é uma URL válida.';
    }

    // Validação de PDF (substituição ou manutenção)
    $validacaoPdf = validarUploadPdf($_FILES['arquivo_pdf'] ?? null);
    if ($validacaoPdf['tem_arquivo'] && !$validacaoPdf['valido']) {
        $erros[] = $validacaoPdf['erro'];
    }

    $removerPdf = !empty($_POST['remover_pdf']);

    // 3. Atualização no Banco via PDO Prepared Statement
    if (empty($erros)) {
        $novoArquivoSalvo = null;
        $arquivoAntigoParaExcluir = null;

        try {
            $colunaArquivoPdf         = $material['arquivo_pdf'];
            $colunaArquivoNome        = $material['arquivo_pdf_nome_original'];
            $colunaArquivoTamanho     = $material['arquivo_pdf_tamanho'];

            if ($validacaoPdf['tem_arquivo']) {
                // Caso 1: Professor enviou um novo arquivo PDF para anexar ou substituir
                $novoArquivoSalvo = salvarUploadPdf($_FILES['arquivo_pdf']);
                $colunaArquivoPdf     = $novoArquivoSalvo;
                $colunaArquivoNome    = $validacaoPdf['nome_original'];
                $colunaArquivoTamanho = $validacaoPdf['tamanho'];

                // Marca o arquivo antigo para ser excluído SOMENTE após o sucesso da gravação no banco
                if (!empty($material['arquivo_pdf'])) {
                    $arquivoAntigoParaExcluir = (string)$material['arquivo_pdf'];
                }
            } elseif ($removerPdf) {
                // Caso 2: Professor solicitou a remoção do PDF existente
                $colunaArquivoPdf     = null;
                $colunaArquivoNome    = null;
                $colunaArquivoTamanho = null;

                if (!empty($material['arquivo_pdf'])) {
                    $arquivoAntigoParaExcluir = (string)$material['arquivo_pdf'];
                }
            }

            $sqlUpdate = "
                UPDATE materiais
                SET materia_id                = :materia_id,
                    titulo                    = :titulo,
                    assunto                   = :assunto,
                    descricao                 = :descricao,
                    conteudo                  = :conteudo,
                    link_externo              = :link_externo,
                    arquivo_pdf               = :arquivo_pdf,
                    arquivo_pdf_nome_original = :arquivo_pdf_nome_original,
                    arquivo_pdf_tamanho       = :arquivo_pdf_tamanho
                WHERE id = :id AND usuario_id = :usuario_id
            ";
            $stmtUpdate = $pdo->prepare($sqlUpdate);
            $stmtUpdate->execute([
                ':materia_id'                => $materiaId,
                ':titulo'                    => $titulo,
                ':assunto'                   => $assunto,
                ':descricao'                 => $descricao,
                ':conteudo'                  => !empty($conteudo) ? $conteudo : null,
                ':link_externo'              => !empty($linkExterno) ? $linkExterno : null,
                ':arquivo_pdf'               => $colunaArquivoPdf,
                ':arquivo_pdf_nome_original' => $colunaArquivoNome,
                ':arquivo_pdf_tamanho'       => $colunaArquivoTamanho,
                ':id'                        => $id,
                ':usuario_id'                => $usuario['id']
            ]);

            // Confirmação: banco atualizado com sucesso. Agora é seguro remover o arquivo antigo físico.
            if ($arquivoAntigoParaExcluir !== null) {
                excluirArquivoPdfFisico($arquivoAntigoParaExcluir);
            }

            setFlashMessage('sucesso', 'Material de estudo atualizado com sucesso!');
            header('Location: ' . $basePath . '/materiais/visualizar.php?id=' . $id);
            exit;
        } catch (Throwable $e) {
            // Se ocorreu falha na persistência, remove apenas o novo arquivo recém-salvo desta requisição
            if ($novoArquivoSalvo !== null) {
                excluirArquivoPdfFisico($novoArquivoSalvo);
            }
            error_log("Erro ao atualizar material #$id: " . $e->getMessage());
            $erros[] = 'Erro ao atualizar os dados no banco de dados. Caso a migração das colunas de PDF não tenha sido executada, atualize o banco.';
        }
    }
}

$pageTitle = 'Editar Material: ' . $material['titulo'];
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 780px; margin: 1.5rem auto;">
    <div class="card">
        <div class="card-header">
            <h1 style="font-size: 1.6rem; color: var(--primary);">Editar Material de Estudo</h1>
            <p style="color: var(--text-muted); font-size: 0.9rem;">
                Atualize as informações, anotações de aula ou referências deste conteúdo.
            </p>
        </div>

        <?php if (!empty($erros)): ?>
            <div class="alert alert-erro">
                <strong>Corrija os erros abaixo:</strong>
                <ul style="margin: 0.5rem 0 0 1.25rem;">
                    <?php foreach ($erros as $erro): ?>
                        <li><?= e($erro) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="<?= e($basePath . '/materiais/editar.php?id=' . $id) ?>" method="POST" enctype="multipart/form-data">
            <?= campoCsrf() ?>

            <div class="form-group">
                <label for="materia_id" class="form-label">Disciplina / Matéria *</label>
                <select id="materia_id" name="materia_id" class="form-control" required>
                    <?php foreach ($materias as $mat): ?>
                        <option value="<?= e($mat['id']) ?>" <?= $materiaId === (int)$mat['id'] ? 'selected' : '' ?>>
                            <?= e($mat['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label for="titulo" class="form-label">Título do Material *</label>
                    <input type="text" id="titulo" name="titulo" class="form-control"
                           value="<?= e($titulo) ?>" required>
                </div>

                <div class="form-group">
                    <label for="assunto" class="form-label">Assunto Específico / Tópico *</label>
                    <input type="text" id="assunto" name="assunto" class="form-control"
                           value="<?= e($assunto) ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="descricao" class="form-label">Breve Descrição *</label>
                <textarea id="descricao" name="descricao" class="form-control" style="min-height: 80px;"
                          required><?= e($descricao) ?></textarea>
            </div>

            <div class="form-group">
                <label for="conteudo" class="form-label">Conteúdo Textual e Anotações de Estudo</label>
                <textarea id="conteudo" name="conteudo" class="form-control" style="min-height: 180px;"><?= e($conteudo) ?></textarea>
            </div>

            <div class="form-group">
                <label for="link_externo" class="form-label">Link Externo de Apoio</label>
                <input type="url" id="link_externo" name="link_externo" class="form-control"
                       value="<?= e($linkExterno) ?>">
            </div>

            <div class="form-group" style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 1.25rem;">
                <label class="form-label" style="font-weight: 700; color: var(--primary);">Documento Anexo em PDF (Opcional — até 10 MB)</label>

                <?php if (!empty($material['arquivo_pdf'])): ?>
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; padding: 0.75rem; background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-sm);">
                        <div>
                            <span style="font-weight: 600; color: var(--text-main);">📄 <?= e($material['arquivo_pdf_nome_original']) ?></span>
                            <span style="font-size: 0.85rem; color: var(--text-muted); margin-left: 0.5rem;">(<?= formatarTamanhoBytes((int)$material['arquivo_pdf_tamanho']) ?>)</span>
                        </div>
                        <a href="<?= e($basePath . '/materiais/download.php?id=' . $id) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-secondary">
                            Visualizar PDF Atual
                        </a>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <label style="display: inline-flex; align-items: center; gap: 0.5rem; color: var(--danger-text); cursor: pointer; font-size: 0.95rem;">
                            <input type="checkbox" name="remover_pdf" value="1">
                            <span>Excluir e remover este anexo em PDF</span>
                        </label>
                    </div>
                <?php endif; ?>

                <label for="arquivo_pdf" class="form-label" style="font-size: 0.9rem;">
                    <?= !empty($material['arquivo_pdf']) ? 'Substituir por outro arquivo PDF:' : 'Enviar arquivo PDF:' ?>
                </label>
                <input type="file" id="arquivo_pdf" name="arquivo_pdf" class="form-control" accept=".pdf,application/pdf">
                <div class="form-help">Apenas arquivos .pdf são aceitos (tamanho máximo: 10 MB).</div>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                <a href="<?= e($basePath . '/materiais/visualizar.php?id=' . $id) ?>" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
