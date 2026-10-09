<?php
/**
 * Autenticação de Usuários (Login) — Sistema Aula+ (IFRO)
 * 
 * Implementa:
 * - RF02: Login seguro por e-mail e senha
 * - RNF01: Verificação de senha com password_verify()
 * - RNF05: Proteção contra CSRF
 * - RNF06: Renovação de identificador da sessão (session_regenerate_id)
 * - RNF07: Mensagens de erro seguras (sem enumeração de e-mails)
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/database.php';

$basePath = getBasePath();

// Se já estiver logado, encaminha direto para o painel
if (estaAutenticado()) {
    header('Location: ' . $basePath . '/dashboard/index.php');
    exit;
}

$erro = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validação do Token CSRF (RNF05)
    if (!validarCsrf($_POST['csrf_token'] ?? null)) {
        $erro = 'Falha de validação de segurança (token CSRF inválido). Atualize a página e tente novamente.';
    } else {
        $email = trim((string)($_POST['email'] ?? ''));
        $senha = (string)($_POST['senha'] ?? '');

        if (empty($email) || empty($senha)) {
            $erro = 'Por favor, preencha o e-mail e a senha.';
        } else {
            try {
                $pdo = getDatabaseConnection();

                // Busca o usuário pelo e-mail com Prepared Statement (RNF03)
                $stmt = $pdo->prepare("SELECT id, nome, email, senha, perfil FROM usuarios WHERE email = :email LIMIT 1");
                $stmt->execute([':email' => $email]);
                $usuario = $stmt->fetch();

                // Verifica a senha com hash seguro (RNF01)
                if ($usuario && password_verify($senha, $usuario['senha'])) {
                    // Regenera o ID da sessão para prevenir Session Fixation (RNF06)
                    session_regenerate_id(true);

                    // Registra os dados seguros na sessão
                    $_SESSION['usuario_id']     = (int)$usuario['id'];
                    $_SESSION['usuario_nome']   = $usuario['nome'];
                    $_SESSION['usuario_email']  = $usuario['email'];
                    $_SESSION['usuario_perfil'] = $usuario['perfil'];

                    setFlashMessage('sucesso', 'Bem-vindo de volta, ' . $usuario['nome'] . '!');
                    header('Location: ' . $basePath . '/dashboard/index.php');
                    exit;
                } else {
                    // Mensagem neutra para evitar ataques de enumeração de e-mails
                    $erro = 'E-mail ou senha inválidos. Por favor, tente novamente.';
                }
            } catch (Throwable $e) {
                error_log('Erro na autenticação: ' . $e->getMessage());
                $erro = 'Erro temporário ao conectar ao banco de dados. Tente novamente em instantes.';
            }
        }
    }
}

$pageTitle = 'Entrar';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 440px; margin: 2rem auto;">
    <div class="card">
        <div class="card-header" style="text-align: center;">
            <h1 style="font-size: 1.6rem; color: var(--primary);">Entrar no Aula+</h1>
            <p style="color: var(--text-muted); font-size: 0.9rem;">
                Acesse seus materiais e organize seus estudos
            </p>
        </div>

        <?php if ($erro): ?>
            <div class="alert alert-erro">
                <?= e($erro) ?>
            </div>
        <?php endif; ?>

        <form action="<?= e($basePath . '/auth/login.php') ?>" method="POST" novalidate>
            <?= campoCsrf() ?>

            <div class="form-group">
                <label for="email" class="form-label">E-mail</label>
                <input type="email" id="email" name="email" class="form-control" 
                       value="<?= e($email) ?>" required autofocus placeholder="seu.email@exemplo.com">
            </div>

            <div class="form-group">
                <label for="senha" class="form-label">Senha</label>
                <input type="password" id="senha" name="senha" class="form-control" 
                       required placeholder="Sua senha segura">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.75rem;">
                Acessar Minha Conta
            </button>
        </form>

        <div style="margin-top: 1.5rem; text-align: center; border-top: 1px solid var(--border-color); padding-top: 1rem; font-size: 0.9rem;">
            Ainda não tem conta? <a href="<?= e($basePath . '/auth/cadastro.php') ?>" style="color: var(--primary); font-weight: 600;">Cadastre-se gratuitamente</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
