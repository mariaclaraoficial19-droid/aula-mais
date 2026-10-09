<?php
/**
 * Painel Principal (Dashboard) — Áreas do Aluno e do Professor — Sistema Aula+ (IFRO)
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/database.php';

// Exige login para acessar o painel
exigirAutenticacao();

$usuario = getUsuarioLogado();
$basePath = getBasePath();

$totalMaterias = 0;
$totalMateriaisGeral = 0;
$totalMeusMateriais = 0;
$meusMateriais = [];
$materiaisRecentes = [];
$erroBanco = null;

try {
    $pdo = getDatabaseConnection();

    // Contagem de matérias
    $stmtMat = $pdo->query("SELECT COUNT(*) FROM materias");
    $totalMaterias = (int)$stmtMat->fetchColumn();

    // Contagem geral de materiais
    $stmtTotal = $pdo->query("SELECT COUNT(*) FROM materiais");
    $totalMateriaisGeral = (int)$stmtTotal->fetchColumn();

    if ($usuario['perfil'] === 'professor') {
        // Estatísticas e materiais criados pelo próprio professor
        $stmtMeusCount = $pdo->prepare("SELECT COUNT(*) FROM materiais WHERE usuario_id = :usuario_id");
        $stmtMeusCount->execute([':usuario_id' => $usuario['id']]);
        $totalMeusMateriais = (int)$stmtMeusCount->fetchColumn();

        $stmtMeus = $pdo->prepare("
            SELECT m.id, m.titulo, m.assunto, m.criado_em, m.resumo_ia,
                   mat.nome AS materia_nome
            FROM materiais m
            INNER JOIN materias mat ON m.materia_id = mat.id
            WHERE m.usuario_id = :usuario_id
            ORDER BY m.id DESC
        ");
        $stmtMeus->execute([':usuario_id' => $usuario['id']]);
        $meusMateriais = $stmtMeus->fetchAll();
    } else {
        // Área do aluno: materiais mais recentes para estudo
        $stmtRecentes = $pdo->query("
            SELECT m.id, m.titulo, m.assunto, m.descricao, m.criado_em,
                   mat.nome AS materia_nome,
                   u.nome AS professor_nome
            FROM materiais m
            INNER JOIN materias mat ON m.materia_id = mat.id
            INNER JOIN usuarios u ON m.usuario_id = u.id
            ORDER BY m.id DESC
            LIMIT 10
        ");
        $materiaisRecentes = $stmtRecentes->fetchAll();
    }
} catch (Throwable $e) {
    error_log("Erro no dashboard: " . $e->getMessage());
    $erroBanco = "Não foi possível carregar os dados do banco. Verifique se as tabelas foram criadas via MySQL Workbench.";
}

$pageTitle = 'Meu Painel';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Saudação e Cabeçalho do Painel -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.8rem; color: var(--text-main);">
            Olá, <?= e($usuario['nome']) ?>!
        </h1>
        <p style="color: var(--text-muted); font-size: 0.95rem;">
            Você está conectado como 
            <strong style="text-transform: capitalize; color: var(--primary);">
                <?= e($usuario['perfil']) ?>
            </strong> no Instituto Federal de Rondônia.
        </p>
    </div>

    <div style="display: flex; gap: 0.75rem;">
        <?php if ($usuario['perfil'] === 'professor'): ?>
            <a href="<?= e($basePath . '/materias/cadastrar.php') ?>" class="btn btn-secondary">+ Nova Matéria</a>
            <a href="<?= e($basePath . '/materiais/cadastrar.php') ?>" class="btn btn-primary">+ Novo Material</a>
        <?php else: ?>
            <a href="<?= e($basePath . '/materiais/index.php') ?>" class="btn btn-primary">🔍 Buscar Materiais</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($erroBanco): ?>
    <div class="alert alert-erro">
        <strong>Aviso:</strong> <?= e($erroBanco) ?>
    </div>
<?php endif; ?>

<!-- Cards de Estatísticas -->
<div class="grid-3" style="margin-bottom: 2.5rem;">
    <?php if ($usuario['perfil'] === 'professor'): ?>
        <div class="card">
            <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">
                Materiais Cadastrados por Você
            </span>
            <div style="font-size: 2.2rem; font-weight: 700; color: var(--primary); margin-top: 0.25rem;">
                <?= $totalMeusMateriais ?>
            </div>
            <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
                Disponíveis para os estudantes
            </p>
        </div>
    <?php endif; ?>

    <div class="card">
        <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">
            Total de Matérias
        </span>
        <div style="font-size: 2.2rem; font-weight: 700; color: var(--secondary); margin-top: 0.25rem;">
            <?= $totalMaterias ?>
        </div>
        <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
            Disciplinas registradas no Aula+
        </p>
    </div>

    <div class="card">
        <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">
            Materiais no Catálogo
        </span>
        <div style="font-size: 2.2rem; font-weight: 700; color: #4338ca; margin-top: 0.25rem;">
            <?= $totalMateriaisGeral ?>
        </div>
        <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
            Conteúdos centralizados
        </p>
    </div>
</div>

<?php if ($usuario['perfil'] === 'professor'): ?>
    <!-- Visão Exclusiva do Professor: Gestão de seus Próprios Materiais -->
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h2 style="font-size: 1.25rem; font-weight: 700;">Meus Materiais de Estudo</h2>
                <p style="font-size: 0.85rem; color: var(--text-muted);">
                    Gerencie os conteúdos, resumos e links disponibilizados por você.
                </p>
            </div>
            <a href="<?= e($basePath . '/materiais/cadastrar.php') ?>" class="btn btn-sm btn-primary">+ Adicionar</a>
        </div>

        <?php if (empty($meusMateriais)): ?>
            <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                <p>Você ainda não cadastrou nenhum material de estudo.</p>
                <a href="<?= e($basePath . '/materiais/cadastrar.php') ?>" class="btn btn-primary btn-sm" style="margin-top: 1rem;">
                    Criar meu primeiro material
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Matéria</th>
                            <th>Assunto</th>
                            <th>Resumo IA</th>
                            <th>Data</th>
                            <th style="text-align: right;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($meusMateriais as $item): ?>
                            <tr>
                                <td>
                                    <strong><?= e($item['titulo']) ?></strong>
                                </td>
                                <td><span class="badge-role badge-aluno"><?= e($item['materia_nome']) ?></span></td>
                                <td><?= e($item['assunto']) ?></td>
                                <td>
                                    <?php if (!empty($item['resumo_ia'])): ?>
                                        <span style="color: #059669; font-weight: 600; font-size: 0.85rem;">✔ Gerado</span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 0.85rem;">Pendente</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d/m/Y', strtotime($item['criado_em'])) ?></td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <a href="<?= e($basePath . '/materiais/visualizar.php?id=' . $item['id']) ?>" 
                                       class="btn btn-sm btn-secondary" title="Visualizar">Ver</a>
                                    
                                    <a href="<?= e($basePath . '/materiais/editar.php?id=' . $item['id']) ?>" 
                                       class="btn btn-sm btn-secondary" title="Editar este material">Editar</a>

                                    <form action="<?= e($basePath . '/materiais/excluir.php') ?>" method="POST" style="display: inline-block;">
                                        <?= campoCsrf() ?>
                                        <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" 
                                                data-confirm="Tem certeza de que deseja excluir o material '<?= e($item['titulo']) ?>'?" 
                                                title="Excluir">
                                            Excluir
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php else: ?>
    <!-- Visão Exclusiva do Aluno: Materiais Recentes e Busca Rápida -->
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h2 style="font-size: 1.25rem; font-weight: 700;">Últimos Materiais Disponibilizados</h2>
                <p style="font-size: 0.85rem; color: var(--text-muted);">
                    Selecione um conteúdo para revisar notas de aula e links úteis.
                </p>
            </div>
            <a href="<?= e($basePath . '/materiais/index.php') ?>" class="btn btn-sm btn-secondary">Ver Catálogo Completo &rarr;</a>
        </div>

        <?php if (empty($materiaisRecentes)): ?>
            <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                Nenhum material cadastrado pelos professores até o momento.
            </div>
        <?php else: ?>
            <div class="grid-2" style="margin-top: 1rem;">
                <?php foreach ($materiaisRecentes as $mat): ?>
                    <div style="border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 1.25rem; background: #ffffff;">
                        <span class="badge-role badge-aluno" style="margin-bottom: 0.5rem; display: inline-block;">
                            <?= e($mat['materia_nome']) ?>
                        </span>
                        <h3 style="font-size: 1.1rem; color: var(--text-main); margin-bottom: 0.35rem;">
                            <?= e($mat['titulo']) ?>
                        </h3>
                        <p style="font-size: 0.85rem; font-weight: 600; color: var(--secondary); margin-bottom: 0.5rem;">
                            Assunto: <?= e($mat['assunto']) ?>
                        </p>
                        <p style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 1rem;">
                            <?= e(mb_strimwidth($mat['descricao'], 0, 120, '...')) ?>
                        </p>
                        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); padding-top: 0.75rem;">
                            <span style="font-size: 0.8rem; color: var(--text-muted);">
                                Prof. <?= e($mat['professor_nome']) ?>
                            </span>
                            <a href="<?= e($basePath . '/materiais/visualizar.php?id=' . $mat['id']) ?>" class="btn btn-sm btn-primary">
                                Estudar Material
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
