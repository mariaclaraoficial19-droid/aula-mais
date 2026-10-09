<?php
/**
 * Catálogo e Busca de Materiais de Estudo — Sistema Aula+ (IFRO)
 * 
 * Implementa:
 * - RF08: Busca de materiais por termo textual (título, assunto, descrição) e filtro por matéria
 * - RNF03: Prepared Statements dinâmicos e seguros contra SQL Injection
 * - RNF04: Escape rigoroso com htmlspecialchars()
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/database.php';

$usuario = getUsuarioLogado();
$basePath = getBasePath();

$termoBusca  = trim((string)($_GET['q'] ?? ''));
$materiaFiltro = (int)($_GET['materia_id'] ?? 0);

$materiais = [];
$materias = [];
$erro = null;

try {
    $pdo = getDatabaseConnection();

    // Carrega todas as matérias para o dropdown de filtro
    $stmtMat = $pdo->query("SELECT id, nome FROM materias ORDER BY nome ASC");
    $materias = $stmtMat->fetchAll();

    // Constrói a consulta dinâmica com Prepared Statements
    $sql = "
        SELECT m.id, m.titulo, m.assunto, m.descricao, m.link_externo, m.resumo_ia, m.criado_em,
               m.usuario_id,
               mat.nome AS materia_nome,
               u.nome AS professor_nome
        FROM materiais m
        INNER JOIN materias mat ON m.materia_id = mat.id
        INNER JOIN usuarios u ON m.usuario_id = u.id
        WHERE 1=1
    ";

    $params = [];

    // Filtro por matéria
    if ($materiaFiltro > 0) {
        $sql .= " AND m.materia_id = :materia_id";
        $params[':materia_id'] = $materiaFiltro;
    }

    // Filtro textual por termo (busca em título, assunto e descrição)
    if (!empty($termoBusca)) {
        $sql .= " AND (m.titulo LIKE :termo OR m.assunto LIKE :termo OR m.descricao LIKE :termo)";
        $params[':termo'] = '%' . $termoBusca . '%';
    }

    $sql .= " ORDER BY m.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $materiais = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log("Erro na busca de materiais: " . $e->getMessage());
    $erro = "Não foi possível carregar os materiais de estudo. Verifique a conexão com o banco.";
}

$pageTitle = 'Materiais de Estudo';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.8rem; color: var(--text-main);">Catálogo de Materiais</h1>
        <p style="color: var(--text-muted); font-size: 0.95rem;">
            Explore e filtre conteúdos de estudo cadastrados pelos docentes do IFRO.
        </p>
    </div>

    <?php if ($usuario && $usuario['perfil'] === 'professor'): ?>
        <a href="<?= e($basePath . '/materiais/cadastrar.php') ?>" class="btn btn-primary">
            + Cadastrar Material
        </a>
    <?php endif; ?>
</div>

<!-- Filtro de Busca -->
<section class="search-box">
    <form action="<?= e($basePath . '/materiais/index.php') ?>" method="GET">
        <div class="search-grid">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="q" class="form-label">Palavra-chave</label>
                <input type="text" id="q" name="q" class="form-control" 
                       value="<?= e($termoBusca) ?>" placeholder="Buscar por título, assunto ou termo...">
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label for="materia_id" class="form-label">Disciplina / Matéria</label>
                <select id="materia_id" name="materia_id" class="form-control">
                    <option value="">Todas as disciplinas</option>
                    <?php foreach ($materias as $mat): ?>
                        <option value="<?= e($mat['id']) ?>" <?= $materiaFiltro === (int)$mat['id'] ? 'selected' : '' ?>>
                            <?= e($mat['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">Filtrar</button>
                <?php if (!empty($termoBusca) || $materiaFiltro > 0): ?>
                    <a href="<?= e($basePath . '/materiais/index.php') ?>" class="btn btn-secondary" title="Limpar Filtros">Limpar</a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</section>

<?php if ($erro): ?>
    <div class="alert alert-erro"><?= e($erro) ?></div>
<?php endif; ?>

<!-- Resultados da Busca -->
<?php if (empty($materiais)): ?>
    <div class="card" style="text-align: center; padding: 3rem 1.5rem;">
        <p style="color: var(--text-muted); font-size: 1.1rem; margin-bottom: 0.5rem;">
            Nenhum material encontrado para os filtros selecionados.
        </p>
        <p style="font-size: 0.9rem; color: var(--text-muted);">
            Tente pesquisar por outros termos ou selecionar outra disciplina.
        </p>
        <?php if (!empty($termoBusca) || $materiaFiltro > 0): ?>
            <a href="<?= e($basePath . '/materiais/index.php') ?>" class="btn btn-secondary btn-sm" style="margin-top: 1rem;">
                Ver Todos os Materiais
            </a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div style="margin-bottom: 1rem; color: var(--text-muted); font-size: 0.9rem;">
        Exibindo <strong><?= count($materiais) ?></strong> material(is) encontrado(s).
    </div>

    <div class="grid-3">
        <?php foreach ($materiais as $item): ?>
            <div class="card" style="display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                        <span class="badge-role badge-aluno">
                            <?= e($item['materia_nome']) ?>
                        </span>
                        <?php if (!empty($item['resumo_ia'])): ?>
                            <span style="font-size: 0.75rem; background: #dcfce7; color: #166534; padding: 0.15rem 0.5rem; border-radius: 9999px; font-weight: 600;">
                                ✦ IA
                            </span>
                        <?php endif; ?>
                    </div>

                    <h2 style="font-size: 1.2rem; color: var(--text-main); font-weight: 700; margin-bottom: 0.35rem;">
                        <?= e($item['titulo']) ?>
                    </h2>

                    <p style="font-size: 0.85rem; font-weight: 600; color: var(--secondary); margin-bottom: 0.5rem;">
                        Assunto: <?= e($item['assunto']) ?>
                    </p>

                    <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                        <?= e(mb_strimwidth($item['descricao'], 0, 110, '...')) ?>
                    </p>
                </div>

                <div style="border-top: 1px solid var(--border-color); padding-top: 0.75rem; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 0.8rem; color: var(--text-muted);">
                        Prof. <?= e($item['professor_nome']) ?>
                    </span>
                    <a href="<?= e($basePath . '/materiais/visualizar.php?id=' . $item['id']) ?>" class="btn btn-sm btn-primary">
                        Acessar &rarr;
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
