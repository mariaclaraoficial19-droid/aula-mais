<?php
/**
 * Cadastro de Matérias — Exclusivo para Professores — Sistema Aula+ (IFRO)
 * 
 * Implementa:
 * - RF04: Cadastro de matérias por professores
 * - RNF02: Validação no servidor
 * - RNF03: PDO Prepared Statements
 * - RNF05: Validação de Token CSRF
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/database.php';

// Bloqueia alunos e visitantes no servidor (RNF02 e RF04)
exigirPerfil('professor');

$basePath = getBasePath();
$erros = [];
$nome = '';
$descricao = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validação de CSRF
    if (!validarCsrf($_POST['csrf_token'] ?? null)) {
        $erros[] = 'Token de segurança CSRF inválido. Recarregue a página e tente novamente.';
    }

    $nome = trim((string)($_POST['nome'] ?? ''));
    $descricao = trim((string)($_POST['descricao'] ?? ''));

    if (mb_strlen($nome) < 2 || mb_strlen($nome) > 100) {
        $erros[] = 'O nome da matéria deve conter entre 2 e 100 caracteres.';
    }

    if (empty($erros)) {
        try {
            $pdo = getDatabaseConnection();

            // Verifica se a matéria já existe
            $stmtCheck = $pdo->prepare("SELECT id FROM materias WHERE nome = :nome LIMIT 1");
            $stmtCheck->execute([':nome' => $nome]);

            if ($stmtCheck->fetch()) {
                $erros[] = 'Já existe uma matéria cadastrada com esse nome.';
            } else {
                $sqlInsert = "INSERT INTO materias (nome, descricao) VALUES (:nome, :descricao)";
                $stmtInsert = $pdo->prepare($sqlInsert);
                $stmtInsert->execute([
                    ':nome'      => $nome,
                    ':descricao' => !empty($descricao) ? $descricao : null
                ]);

                setFlashMessage('sucesso', 'Matéria "' . $nome . '" cadastrada com sucesso!');
                header('Location: ' . $basePath . '/materias/index.php');
                exit;
            }
        } catch (Throwable $e) {
            error_log("Erro ao cadastrar matéria: " . $e->getMessage());
            $erros[] = 'Erro ao salvar a matéria no banco de dados.';
        }
    }
}

$pageTitle = 'Cadastrar Matéria';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 580px; margin: 1rem auto;">
    <div class="card">
        <div class="card-header">
            <h1 style="font-size: 1.5rem; color: var(--primary);">Nova Matéria</h1>
            <p style="color: var(--text-muted); font-size: 0.9rem;">
                Adicione uma nova disciplina para organizar os materiais de estudo.
            </p>
        </div>

        <?php if (!empty($erros)): ?>
            <div class="alert alert-erro">
                <strong>Não foi possível salvar:</strong>
                <ul style="margin: 0.5rem 0 0 1.25rem;">
                    <?php foreach ($erros as $erro): ?>
                        <li><?= e($erro) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="<?= e($basePath . '/materias/cadastrar.php') ?>" method="POST">
            <?= campoCsrf() ?>

            <div class="form-group">
                <label for="nome" class="form-label">Nome da Matéria *</label>
                <input type="text" id="nome" name="nome" class="form-control" 
                       value="<?= e($nome) ?>" required placeholder="Ex: Programação Web II">
            </div>

            <div class="form-group">
                <label for="descricao" class="form-label">Descrição da Disciplina (Opcional)</label>
                <textarea id="descricao" name="descricao" class="form-control" 
                          placeholder="Informe a ementa resumida ou tópicos principais abordados na disciplina..."><?= e($descricao) ?></textarea>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary">Salvar Matéria</button>
                <a href="<?= e($basePath . '/materias/index.php') ?>" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
