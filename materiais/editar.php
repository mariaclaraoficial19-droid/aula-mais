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

    // 3. Atualização no Banco via PDO Prepared Statement
    if (empty($erros)) {
        try {
            $sqlUpdate = "
                UPDATE materiais
                SET materia_id   = :materia_id,
                    titulo       = :titulo,
                    assunto      = :assunto,
                    descricao    = :descricao,
                    conteudo     = :conteudo,
                    link_externo = :link_externo
                WHERE id = :id AND usuario_id = :usuario_id
            ";
            $stmtUpdate = $pdo->prepare($sqlUpdate);
            $stmtUpdate->execute([
                ':materia_id'   => $materiaId,
                ':titulo'       => $titulo,
                ':assunto'      => $assunto,
                ':descricao'    => $descricao,
                ':conteudo'     => !empty($conteudo) ? $conteudo : null,
                ':link_externo' => !empty($linkExterno) ? $linkExterno : null,
                ':id'           => $id,
                ':usuario_id'   => $usuario['id']
            ]);

            setFlashMessage('sucesso', 'Material de estudo atualizado com sucesso!');
            header('Location: ' . $basePath . '/materiais/visualizar.php?id=' . $id);
            exit;
        } catch (Throwable $e) {
            error_log("Erro ao atualizar material #$id: " . $e->getMessage());
            $erros[] = 'Erro ao atualizar os dados no banco de dados.';
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

        <form action="<?= e($basePath . '/materiais/editar.php?id=' . $id) ?>" method="POST">
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

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                <a href="<?= e($basePath . '/materiais/visualizar.php?id=' . $id) ?>" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
