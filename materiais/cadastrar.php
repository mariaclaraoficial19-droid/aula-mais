<?php
/**
 * Cadastro de Material de Estudo — Exclusivo para Professores — Sistema Aula+ (IFRO)
 * 
 * Implementa:
 * - RF06: Cadastro de material associado à matéria, com título, assunto, descrição, conteúdo e link
 * - RF07: Associação automática ao professor autor logado (usuario_id)
 * - RNF02: Validação estrita no servidor
 * - RNF03: Prepared Statements via PDO
 * - RNF05: Proteção contra CSRF
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/database.php';

// Exige que o usuário seja professor (RNF02 e RF06)
exigirPerfil('professor');

$usuario = getUsuarioLogado();
$basePath = getBasePath();

$erros = [];
$materiaId = 0;
$titulo = '';
$assunto = '';
$descricao = '';
$conteudo = '';
$linkExterno = '';
$materias = [];

try {
    $pdo = getDatabaseConnection();
    // Carrega a lista de disciplinas para o select
    $stmtMat = $pdo->query("SELECT id, nome FROM materias ORDER BY nome ASC");
    $materias = $stmtMat->fetchAll();
} catch (Throwable $e) {
    error_log("Erro ao carregar matérias para cadastro: " . $e->getMessage());
    $erros[] = "Não foi possível carregar a lista de matérias do banco de dados.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validação CSRF
    if (!validarCsrf($_POST['csrf_token'] ?? null)) {
        $erros[] = 'Token de segurança CSRF inválido ou expirado. Atualize a página e tente novamente.';
    }

    $materiaId   = (int)($_POST['materia_id'] ?? 0);
    $titulo      = trim((string)($_POST['titulo'] ?? ''));
    $assunto     = trim((string)($_POST['assunto'] ?? ''));
    $descricao   = trim((string)($_POST['descricao'] ?? ''));
    $conteudo    = trim((string)($_POST['conteudo'] ?? ''));
    $linkExterno = trim((string)($_POST['link_externo'] ?? ''));

    // 2. Validações no Servidor
    if ($materiaId <= 0) {
        $erros[] = 'Selecione uma matéria/disciplina válida para vincular ao material.';
    }

    if (mb_strlen($titulo) < 3 || mb_strlen($titulo) > 180) {
        $erros[] = 'O título do material deve conter entre 3 e 180 caracteres.';
    }

    if (mb_strlen($assunto) < 2 || mb_strlen($assunto) > 120) {
        $erros[] = 'O assunto/tópico deve conter entre 2 e 120 caracteres.';
    }

    if (mb_strlen($descricao) < 5) {
        $erros[] = 'A descrição do material deve conter pelo menos 5 caracteres.';
    }

    if (!empty($linkExterno) && !filter_var($linkExterno, FILTER_VALIDATE_URL)) {
        $erros[] = 'O link externo informado não é uma URL válida (exemplo válido: https://exemplo.com).';
    }

    // 3. Persistência no Banco com Prepared Statement
    if (empty($erros)) {
        try {
            $sqlInsert = "
                INSERT INTO materiais (materia_id, usuario_id, titulo, assunto, descricao, conteudo, link_externo)
                VALUES (:materia_id, :usuario_id, :titulo, :assunto, :descricao, :conteudo, :link_externo)
            ";
            $stmt = $pdo->prepare($sqlInsert);
            $stmt->execute([
                ':materia_id'   => $materiaId,
                ':usuario_id'   => $usuario['id'], // Registra o professor autenticado
                ':titulo'       => $titulo,
                ':assunto'      => $assunto,
                ':descricao'    => $descricao,
                ':conteudo'     => !empty($conteudo) ? $conteudo : null,
                ':link_externo' => !empty($linkExterno) ? $linkExterno : null,
            ]);

            $novoId = (int)$pdo->lastInsertId();

            setFlashMessage('sucesso', 'Material de estudo cadastrado com sucesso!');
            header('Location: ' . $basePath . '/materiais/visualizar.php?id=' . $novoId);
            exit;
        } catch (Throwable $e) {
            error_log("Erro ao salvar material: " . $e->getMessage());
            $erros[] = 'Erro ao salvar o material no banco de dados.';
        }
    }
}

$pageTitle = 'Cadastrar Material de Estudo';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 780px; margin: 1.5rem auto;">
    <div class="card">
        <div class="card-header">
            <h1 style="font-size: 1.6rem; color: var(--primary);">Novo Material de Estudo</h1>
            <p style="color: var(--text-muted); font-size: 0.9rem;">
                Disponibilize notas de aula, orientações de estudo e links para seus alunos.
            </p>
        </div>

        <?php if (empty($materias)): ?>
            <div class="alert alert-aviso">
                <strong>Atenção:</strong> Nenhuma matéria cadastrada no momento. 
                Você precisa <a href="<?= e($basePath . '/materias/cadastrar.php') ?>" style="font-weight: 700; color: inherit; text-decoration: underline;">cadastrar uma matéria</a> antes de criar um material.
            </div>
        <?php endif; ?>

        <?php if (!empty($erros)): ?>
            <div class="alert alert-erro">
                <strong>Corrija os seguintes itens:</strong>
                <ul style="margin: 0.5rem 0 0 1.25rem;">
                    <?php foreach ($erros as $erro): ?>
                        <li><?= e($erro) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="<?= e($basePath . '/materiais/cadastrar.php') ?>" method="POST" <?= empty($materias) ? 'style="opacity: 0.6; pointer-events: none;"' : '' ?>>
            <?= campoCsrf() ?>

            <div class="form-group">
                <label for="materia_id" class="form-label">Disciplina / Matéria *</label>
                <select id="materia_id" name="materia_id" class="form-control" required>
                    <option value="">Selecione uma matéria...</option>
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
                           value="<?= e($titulo) ?>" required placeholder="Ex: Introdução ao PDO e Prepared Statements">
                </div>

                <div class="form-group">
                    <label for="assunto" class="form-label">Assunto Específico / Tópico *</label>
                    <input type="text" id="assunto" name="assunto" class="form-control" 
                           value="<?= e($assunto) ?>" required placeholder="Ex: Banco de Dados em PHP">
                </div>
            </div>

            <div class="form-group">
                <label for="descricao" class="form-label">Breve Descrição / Visão Geral *</label>
                <textarea id="descricao" name="descricao" class="form-control" style="min-height: 80px;" 
                          required placeholder="Explique brevemente sobre o que este material trata..."><?= e($descricao) ?></textarea>
            </div>

            <div class="form-group">
                <label for="conteudo" class="form-label">Conteúdo Textual e Anotações de Estudo (Opcional)</label>
                <textarea id="conteudo" name="conteudo" class="form-control" style="min-height: 180px;" 
                          placeholder="Digite ou cole aqui o texto da aula, tópicos de estudo, exercícios propostos ou explicação detalhada..."><?= e($conteudo) ?></textarea>
                <div class="form-help">Este conteúdo poderá ser utilizado para a geração do resumo por IA.</div>
            </div>

            <div class="form-group">
                <label for="link_externo" class="form-label">Link Externo de Apoio (Opcional)</label>
                <input type="url" id="link_externo" name="link_externo" class="form-control" 
                       value="<?= e($linkExterno) ?>" placeholder="https://exemplo.com/slides-ou-repositorio">
                <div class="form-help">URL para repositório do GitHub, Google Drive, slides ou artigo de referência.</div>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary">Salvar e Publicar Material</button>
                <a href="<?= e($basePath . '/dashboard/index.php') ?>" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
