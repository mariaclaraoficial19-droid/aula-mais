<?php
/**
 * Cadastro de Usuários — Sistema Aula+ (IFRO)
 * 
 * Implementa:
 * - RF01: Cadastro com Nome, E-mail, Senha e Perfil (Aluno ou Professor)
 * - RNF01: Senhas protegidas com password_hash()
 * - RNF02: Validação rigorosa no servidor
 * - RNF03: PDO com Prepared Statements
 * - RNF04: Escape de dados com htmlspecialchars()
 * - RNF05: Proteção contra CSRF
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/database.php';

$basePath = getBasePath();

// Se o usuário já estiver logado, redireciona para o painel
if (estaAutenticado()) {
    header('Location: ' . $basePath . '/dashboard/index.php');
    exit;
}

$erros = [];
$nome = '';
$email = '';
$perfil = 'aluno';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validação de Token CSRF (RNF05)
    if (!validarCsrf($_POST['csrf_token'] ?? null)) {
        $erros[] = 'Falha na validação de segurança (token CSRF inválido ou expirado). Atualize a página e tente novamente.';
    }

    // 2. Coleta e Sanitização dos Dados
    $nome             = trim((string)($_POST['nome'] ?? ''));
    $email            = trim((string)($_POST['email'] ?? ''));
    $senha            = (string)($_POST['senha'] ?? '');
    $confirmacaoSenha = (string)($_POST['confirmacao_senha'] ?? '');
    $perfilPost       = (string)($_POST['perfil'] ?? 'aluno');

    // 3. Validação Rigorosa no Servidor (RNF02)
    if (mb_strlen($nome) < 3 || mb_strlen($nome) > 120) {
        $erros[] = 'O nome completo deve conter entre 3 e 120 caracteres.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
        $erros[] = 'Informe um endereço de e-mail válido.';
    }

    if (mb_strlen($senha) < 6) {
        $erros[] = 'A senha deve possuir no mínimo 6 caracteres para garantir a segurança.';
    }

    if ($senha !== $confirmacaoSenha) {
        $erros[] = 'A confirmação de senha não coincide com a senha digitada.';
    }

    if (!in_array($perfilPost, ['aluno', 'professor'], true)) {
        $erros[] = 'Tipo de perfil inválido. Selecione Aluno ou Professor.';
    } else {
        $perfil = $perfilPost;
    }

    // 4. Verificação de E-mail Duplicado e Persistência no Banco (RNF03)
    if (empty($erros)) {
        try {
            $pdo = getDatabaseConnection();

            // Verifica se o e-mail já existe
            $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email LIMIT 1");
            $stmtCheck->execute([':email' => $email]);

            if ($stmtCheck->fetch()) {
                $erros[] = 'Este e-mail já está cadastrado no sistema. Utilize outro ou faça login.';
            } else {
                // Criptografa a senha com hash seguro (RNF01)
                $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

                $sqlInsert = "
                    INSERT INTO usuarios (nome, email, senha, perfil)
                    VALUES (:nome, :email, :senha, :perfil)
                ";
                $stmtInsert = $pdo->prepare($sqlInsert);
                $stmtInsert->execute([
                    ':nome'   => $nome,
                    ':email'  => $email,
                    ':senha'  => $senhaHash,
                    ':perfil' => $perfil
                ]);

                setFlashMessage('sucesso', 'Cadastro concluído com sucesso! Entre com seu e-mail e senha.');
                header('Location: ' . $basePath . '/auth/login.php');
                exit;
            }
        } catch (Throwable $e) {
            error_log('Erro ao cadastrar usuário: ' . $e->getMessage());
            $erros[] = 'Erro ao salvar os dados no banco. Certifique-se de que o banco de dados está ativo.';
        }
    }
}

$pageTitle = 'Criar Conta';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 520px; margin: 1.5rem auto;">
    <div class="card">
        <div class="card-header">
            <h1 style="font-size: 1.6rem; color: var(--primary);">Cadastre-se no Aula+</h1>
            <p style="color: var(--text-muted); font-size: 0.9rem;">
                Crie sua conta para organizar e consultar seus materiais de estudo do IFRO.
            </p>
        </div>

        <?php if (!empty($erros)): ?>
            <div class="alert alert-erro">
                <strong>Atenção:</strong>
                <ul style="margin: 0.5rem 0 0 1.25rem;">
                    <?php foreach ($erros as $erro): ?>
                        <li><?= e($erro) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="<?= e($basePath . '/auth/cadastro.php') ?>" method="POST" novalidate>
            <?= campoCsrf() ?>

            <div class="form-group">
                <label for="nome" class="form-label">Nome Completo *</label>
                <input type="text" id="nome" name="nome" class="form-control" 
                       value="<?= e($nome) ?>" required placeholder="Ex: Maria dos Santos">
            </div>

            <div class="form-group">
                <label for="email" class="form-label">E-mail Institucional / Pessoal *</label>
                <input type="email" id="email" name="email" class="form-control" 
                       value="<?= e($email) ?>" required placeholder="seu.email@exemplo.com">
            </div>

            <div class="form-group">
                <label for="perfil" class="form-label">Tipo de Perfil *</label>
                <select id="perfil" name="perfil" class="form-control" required>
                    <option value="aluno" <?= $perfil === 'aluno' ? 'selected' : '' ?>>Aluno (Consultar e Estudar)</option>
                    <option value="professor" <?= $perfil === 'professor' ? 'selected' : '' ?>>Professor (Publicar e Gerenciar)</option>
                </select>
                <div class="form-help">Professores têm permissão para criar matérias e cadastrar materiais.</div>
            </div>

            <div class="form-group">
                <label for="senha" class="form-label">Senha de Acesso *</label>
                <input type="password" id="senha" name="senha" class="form-control" 
                       required placeholder="Mínimo de 6 caracteres">
            </div>

            <div class="form-group">
                <label for="confirmacao_senha" class="form-label">Confirmar Senha *</label>
                <input type="password" id="confirmacao_senha" name="confirmacao_senha" class="form-control" 
                       required placeholder="Repita a senha digitada">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                Finalizar Cadastro
            </button>
        </form>

        <div style="margin-top: 1.5rem; text-align: center; border-top: 1px solid var(--border-color); padding-top: 1rem; font-size: 0.9rem;">
            Já possui uma conta? <a href="<?= e($basePath . '/auth/login.php') ?>" style="color: var(--primary); font-weight: 600;">Entrar agora</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
