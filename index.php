<?php
/**
 * Página Inicial — Sistema Aula+ (IFRO)
 */
declare(strict_types=1);

$pageTitle = 'Início — Simplificar o Estudo';

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/database.php';

$basePath = getBasePath();
$materiaisRecentes = [];
$materiasDisponiveis = [];
$erroBanco = null;

// Tenta carregar matérias e últimos materiais do banco de forma resiliente
try {
    $pdo = getDatabaseConnection();

    // Consulta matérias para o filtro de busca rápida
    $stmtMaterias = $pdo->query("SELECT id, nome FROM materias ORDER BY nome ASC LIMIT 20");
    $materiasDisponiveis = $stmtMaterias->fetchAll();

    // Consulta os 6 materiais mais recentes com os nomes da matéria e do professor autor
    $sqlRecentes = "
        SELECT m.id, m.titulo, m.assunto, m.descricao, m.criado_em,
               mat.nome AS materia_nome,
               u.nome AS professor_nome
        FROM materiais m
        INNER JOIN materias mat ON m.materia_id = mat.id
        INNER JOIN usuarios u ON m.usuario_id = u.id
        ORDER BY m.id DESC
        LIMIT 6
    ";
    $stmtRecentes = $pdo->query($sqlRecentes);
    $materiaisRecentes = $stmtRecentes->fetchAll();
} catch (Throwable $e) {
    // Caso o banco ainda não esteja configurado ou ativo, registra e informa de modo limpo
    error_log("Aviso ao carregar dados na home: " . $e->getMessage());
    $erroBanco = "O banco de dados ainda não foi inicializado ou o serviço MySQL está inativo. Siga as instruções do README para iniciar o banco.";
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div class="container">
        <h1>Aula+ — Simplificar o Estudo</h1>
        <p>
            Centralize, organize e localize seus materiais didáticos em um só lugar. 
            Conteúdos categorizados por matéria, título e assunto, projetados para a comunidade do IFRO.
        </p>
        <div class="hero-actions">
            <?php if (estaAutenticado()): ?>
                <a href="<?= e($basePath . '/dashboard/index.php') ?>" class="btn btn-secondary">Acessar Meu Painel</a>
                <a href="<?= e($basePath . '/materiais/index.php') ?>" class="btn btn-outline">Explorar Materiais</a>
            <?php else: ?>
                <a href="<?= e($basePath . '/auth/cadastro.php') ?>" class="btn btn-secondary">Criar Minha Conta</a>
                <a href="<?= e($basePath . '/auth/login.php') ?>" class="btn btn-outline">Fazer Login</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Formulário de Busca Rápida -->
<section class="search-box">
    <form action="<?= e($basePath . '/materiais/index.php') ?>" method="GET">
        <div class="search-grid">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="termo" class="form-label">Buscar Conteúdos</label>
                <input type="text" id="termo" name="q" class="form-control" placeholder="Digite um título, assunto ou palavra-chave...">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label for="materia_id" class="form-label">Filtrar por Matéria</label>
                <select id="materia_id" name="materia_id" class="form-control">
                    <option value="">Todas as matérias</option>
                    <?php foreach ($materiasDisponiveis as $mat): ?>
                        <option value="<?= e($mat['id']) ?>"><?= e($mat['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Pesquisar</button>
            </div>
        </div>
    </form>
</section>

<?php if ($erroBanco): ?>
    <div class="alert alert-aviso">
        <strong>Nota de Inicialização:</strong> <?= e($erroBanco) ?>
    </div>
<?php endif; ?>

<!-- Seção de Destaques / Materiais Recentes -->
<section style="margin-top: 2.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.5rem; font-weight: 700;">Materiais Recentes</h2>
        <a href="<?= e($basePath . '/materiais/index.php') ?>" class="btn btn-sm btn-secondary">Ver todos &rarr;</a>
    </div>

    <?php if (empty($materiaisRecentes)): ?>
        <div class="card" style="text-align: center; padding: 3rem 1.5rem;">
            <p style="color: var(--text-muted); font-size: 1.1rem; margin-bottom: 1rem;">
                Nenhum material de estudo cadastrado ainda.
            </p>
            <?php if (estaAutenticado() && getUsuarioLogado()['perfil'] === 'professor'): ?>
                <a href="<?= e($basePath . '/materiais/cadastrar.php') ?>" class="btn btn-primary">+ Cadastrar Primeiro Material</a>
            <?php else: ?>
                <p style="font-size: 0.9rem; color: var(--text-muted);">
                    Professores podem se cadastrar e disponibilizar materiais para a turma.
                </p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="grid-3">
            <?php foreach ($materiaisRecentes as $mat): ?>
                <div class="card" style="display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <span class="badge-role badge-aluno" style="margin-bottom: 0.5rem; display: inline-block;">
                            <?= e($mat['materia_nome']) ?>
                        </span>
                        <h3 style="font-size: 1.15rem; margin-bottom: 0.5rem; color: var(--text-main);">
                            <?= e($mat['titulo']) ?>
                        </h3>
                        <p style="font-size: 0.85rem; font-weight: 600; color: var(--secondary); margin-bottom: 0.5rem;">
                            Assunto: <?= e($mat['assunto']) ?>
                        </p>
                        <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1rem;">
                            <?= e(mb_strimwidth($mat['descricao'], 0, 110, '...')) ?>
                        </p>
                    </div>
                    <div style="border-top: 1px solid var(--border-color); padding-top: 0.75rem; display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 0.8rem; color: var(--text-muted);">
                            Prof. <?= e($mat['professor_nome']) ?>
                        </span>
                        <a href="<?= e($basePath . '/materiais/visualizar.php?id=' . $mat['id']) ?>" class="btn btn-sm btn-secondary">
                            Acessar
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
