<?php
/**
 * Listagem de Matérias — Sistema Aula+ (IFRO)
 * 
 * Implementa:
 * - RF05: Listagem de todas as matérias cadastradas e total de materiais em cada uma
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/database.php';

$usuario = getUsuarioLogado();
$basePath = getBasePath();

$materias = [];
$erro = null;

try {
    $pdo = getDatabaseConnection();
    // Consulta as matérias e faz a contagem de materiais associados (LEFT JOIN)
    $sql = "
        SELECT m.id, m.nome, m.descricao, m.criado_em,
               COUNT(mat.id) AS total_materiais
        FROM materias m
        LEFT JOIN materiais mat ON m.id = mat.materia_id
        GROUP BY m.id, m.nome, m.descricao, m.criado_em
        ORDER BY m.nome ASC
    ";
    $stmt = $pdo->query($sql);
    $materias = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log("Erro ao listar matérias: " . $e->getMessage());
    $erro = "Não foi possível carregar a lista de matérias no momento.";
}

$pageTitle = 'Matérias Cadastradas';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.8rem; color: var(--text-main);">Catálogo de Matérias</h1>
        <p style="color: var(--text-muted); font-size: 0.95rem;">
            Disciplinas cadastradas no Instituto Federal de Rondônia.
        </p>
    </div>

    <?php if ($usuario && $usuario['perfil'] === 'professor'): ?>
        <a href="<?= e($basePath . '/materias/cadastrar.php') ?>" class="btn btn-primary">
            + Cadastrar Nova Matéria
        </a>
    <?php endif; ?>
</div>

<?php if ($erro): ?>
    <div class="alert alert-erro">
        <?= e($erro) ?>
    </div>
<?php endif; ?>

<?php if (empty($materias)): ?>
    <div class="card" style="text-align: center; padding: 3rem 1.5rem;">
        <p style="color: var(--text-muted); font-size: 1.1rem; margin-bottom: 1rem;">
            Nenhuma matéria cadastrada no momento.
        </p>
        <?php if ($usuario && $usuario['perfil'] === 'professor'): ?>
            <a href="<?= e($basePath . '/materias/cadastrar.php') ?>" class="btn btn-primary">
                + Cadastrar Primeira Matéria
            </a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="grid-3">
        <?php foreach ($materias as $materia): ?>
            <div class="card" style="display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                        <h2 style="font-size: 1.2rem; color: var(--primary); font-weight: 700;">
                            <?= e($materia['nome']) ?>
                        </h2>
                        <span class="badge-role badge-aluno" title="Total de materiais cadastrados">
                            <?= (int)$materia['total_materiais'] ?> material(is)
                        </span>
                    </div>

                    <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                        <?= e($materia['descricao'] ?: 'Sem descrição detalhada registrada.') ?>
                    </p>
                </div>

                <div style="border-top: 1px solid var(--border-color); padding-top: 0.75rem;">
                    <a href="<?= e($basePath . '/materiais/index.php?materia_id=' . $materia['id']) ?>" 
                       class="btn btn-sm btn-secondary" style="width: 100%;">
                        Ver Materiais desta Disciplina &rarr;
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
