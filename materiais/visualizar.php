<?php
/**
 * Visualização Detalhada do Material de Estudo — Sistema Aula+ (IFRO)
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/database.php';

$usuario = getUsuarioLogado();
$basePath = getBasePath();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlashMessage('erro', 'Material de estudo não especificado.');
    header('Location: ' . $basePath . '/materiais/index.php');
    exit;
}

$material = null;

try {
    $pdo = getDatabaseConnection();
    $sql = "
        SELECT m.id, m.materia_id, m.usuario_id, m.titulo, m.assunto, m.descricao,
               m.conteudo, m.link_externo, m.resumo_ia, m.criado_em, m.atualizado_em,
               mat.nome AS materia_nome,
               u.nome AS professor_nome,
               u.email AS professor_email
        FROM materiais m
        INNER JOIN materias mat ON m.materia_id = mat.id
        INNER JOIN usuarios u ON m.usuario_id = u.id
        WHERE m.id = :id
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $material = $stmt->fetch();
} catch (Throwable $e) {
    error_log("Erro ao carregar material #$id: " . $e->getMessage());
}

if (!$material) {
    setFlashMessage('erro', 'O material solicitado não foi encontrado ou não existe mais.');
    header('Location: ' . $basePath . '/materiais/index.php');
    exit;
}

$ehAutor = ($usuario && (int)$usuario['id'] === (int)$material['usuario_id']);

$pageTitle = $material['titulo'];
require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom: 1.5rem;">
    <a href="<?= e($basePath . '/materiais/index.php') ?>" class="btn btn-sm btn-secondary">
        &larr; Voltar para a lista de materiais
    </a>
</div>

<article class="card" style="margin-bottom: 2rem;">
    <header style="border-bottom: 1px solid var(--border-color); padding-bottom: 1.25rem; margin-bottom: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap;">
            <div>
                <span class="badge-role badge-aluno" style="margin-bottom: 0.5rem; display: inline-block;">
                    <?= e($material['materia_nome']) ?>
                </span>
                <h1 style="font-size: 2rem; color: var(--text-main); margin-bottom: 0.5rem; line-height: 1.25;">
                    <?= e($material['titulo']) ?>
                </h1>
                <p style="font-size: 1rem; font-weight: 600; color: var(--secondary);">
                    Assunto: <?= e($material['assunto']) ?>
                </p>
            </div>

            <?php if ($ehAutor): ?>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="<?= e($basePath . '/materiais/editar.php?id=' . $material['id']) ?>" class="btn btn-secondary btn-sm">
                        ✏ Editar
                    </a>
                    <form action="<?= e($basePath . '/materiais/excluir.php') ?>" method="POST" style="display: inline-block;">
                        <?= campoCsrf() ?>
                        <input type="hidden" name="id" value="<?= e($material['id']) ?>">
                        <button type="submit" class="btn btn-danger btn-sm" 
                                data-confirm="Tem certeza de que deseja excluir permanentemente este material?">
                            🗑 Excluir
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <div style="margin-top: 1rem; font-size: 0.85rem; color: var(--text-muted); display: flex; gap: 1.5rem; flex-wrap: wrap;">
            <span>Docente responsável: <strong>Prof. <?= e($material['professor_nome']) ?></strong></span>
            <span>Publicado em: <strong><?= date('d/m/Y \à\s H:i', strtotime($material['criado_em'])) ?></strong></span>
            <?php if ($material['atualizado_em'] !== $material['criado_em']): ?>
                <span>Atualizado em: <strong><?= date('d/m/Y \à\s H:i', strtotime($material['atualizado_em'])) ?></strong></span>
            <?php endif; ?>
        </div>
    </header>

    <!-- Descrição -->
    <section style="margin-bottom: 2rem;">
        <h2 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--primary);">
            Visão Geral / Descrição
        </h2>
        <div style="background: #f8fafc; border-left: 4px solid var(--primary); padding: 1rem 1.25rem; border-radius: 4px; font-size: 1rem; color: #334155;">
            <?= nl2br(e($material['descricao'])) ?>
        </div>
    </section>

    <!-- Conteúdo Textual / Notas de Aula -->
    <?php if (!empty($material['conteudo'])): ?>
        <section style="margin-bottom: 2rem;">
            <h2 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 0.75rem; color: var(--text-main);">
                Anotações e Conteúdo de Estudo
            </h2>
            <div style="background: #ffffff; border: 1px solid var(--border-color); padding: 1.5rem; border-radius: var(--radius-sm); line-height: 1.7; font-size: 0.98rem; white-space: pre-wrap; font-family: inherit;">
                <?= e($material['conteudo']) ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Link Externo de Apoio -->
    <?php if (!empty($material['link_externo'])): ?>
        <section style="margin-bottom: 2rem;">
            <h2 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--text-main);">
                Material Complementar / Link Externo
            </h2>
            <a href="<?= e($material['link_externo']) ?>" target="_blank" rel="noopener noreferrer" 
               class="btn btn-secondary" style="display: inline-flex; gap: 0.5rem;">
                🔗 Acessar Link Externo: <?= e($material['link_externo']) ?> &rarr;
            </a>
        </section>
    <?php endif; ?>

    <!-- Seção de Resumo por Inteligência Artificial -->
    <section style="border-top: 1px solid var(--border-color); padding-top: 1.5rem; margin-top: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
            <h2 style="font-size: 1.2rem; font-weight: 700; color: #047857; display: flex; align-items: center; gap: 0.5rem;">
                <span>✦</span> Resumo Inteligente por IA (Google Gemini)
            </h2>

            <?php if (!empty($material['resumo_ia']) && $ehAutor): ?>
                <form action="<?= e($basePath . '/materiais/gerar_resumo.php') ?>" method="POST" style="margin: 0;">
                    <?= campoCsrf() ?>
                    <input type="hidden" name="id" value="<?= e($material['id']) ?>">
                    <button type="submit" class="btn btn-sm btn-secondary" 
                            data-confirm="Deseja solicitar uma nova versão do resumo para este material?">
                        🔄 Atualizar Resumo com IA
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (!empty($material['resumo_ia'])): ?>
            <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: var(--radius-sm); padding: 1.25rem; color: #065f46; line-height: 1.6; white-space: pre-wrap; font-family: inherit;">
                <?= e($material['resumo_ia']) ?>
            </div>
            <p style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.5rem;">
                ℹ Resumo gerado de forma automatizada por Inteligência Artificial (Google Gemini) como apoio pedagógico.
            </p>
        <?php else: ?>
            <div style="background: #f8fafc; border: 1px dashed var(--border-color); border-radius: var(--radius-sm); padding: 1.5rem; text-align: center;">
                <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 1rem;">
                    Este material ainda não possui um resumo educacional gerado por Inteligência Artificial.
                </p>
                <?php if ($usuario): ?>
                    <form action="<?= e($basePath . '/materiais/gerar_resumo.php') ?>" method="POST" style="display: inline-block;">
                        <?= campoCsrf() ?>
                        <input type="hidden" name="id" value="<?= e($material['id']) ?>">
                        <button type="submit" class="btn btn-primary btn-sm">
                            ✨ Gerar Resumo com Inteligência Artificial
                        </button>
                    </form>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.5rem;">
                        Recurso complementar resiliente. Não afeta o acesso às anotações da aula caso a IA esteja offline.
                    </p>
                <?php else: ?>
                    <p style="font-size: 0.85rem; color: var(--text-muted);">
                        <a href="<?= e($basePath . '/auth/login.php') ?>" style="color: var(--primary); font-weight: 600;">Faça login</a> 
                        para gerar resumos inteligentes e estudar este conteúdo.
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</article>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
