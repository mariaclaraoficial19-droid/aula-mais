<?php
/**
 * Barra de Navegação do Sistema Aula+ (IFRO)
 */
declare(strict_types=1);

$usuarioLogado = getUsuarioLogado();
$basePath = getBasePath();
$homeUrl = ($basePath !== '') ? ($basePath . '/index.php') : '/';
?>
<nav class="navbar">
    <div class="container nav-container">
        <a href="<?= e($homeUrl) ?>" class="nav-brand">
            <span>📚 Aula+</span>
            <span class="tagline">IFRO</span>
        </a>

        <ul class="nav-menu">
            <li><a href="<?= e($homeUrl) ?>" class="nav-link">Início</a></li>
            <li><a href="<?= e($basePath . '/materiais/index.php') ?>" class="nav-link">Materiais</a></li>
            <li><a href="<?= e($basePath . '/materias/index.php') ?>" class="nav-link">Matérias</a></li>

            <?php if ($usuarioLogado): ?>
                <li><a href="<?= e($basePath . '/dashboard/index.php') ?>" class="nav-link">Painel</a></li>

                <?php if ($usuarioLogado['perfil'] === 'professor'): ?>
                    <li><a href="<?= e($basePath . '/materiais/cadastrar.php') ?>" class="btn btn-sm btn-primary">+ Novo Material</a></li>
                <?php endif; ?>

                <li class="nav-user">
                    <span class="badge-role badge-<?= e($usuarioLogado['perfil']) ?>">
                        <?= e(ucfirst($usuarioLogado['perfil'])) ?>
                    </span>
                    <span style="font-size: 0.9rem; font-weight: 600;"><?= e($usuarioLogado['nome']) ?></span>
                    <a href="<?= e($basePath . '/auth/logout.php') ?>" class="btn btn-sm btn-secondary" title="Encerrar Sessão">Sair</a>
                </li>
            <?php else: ?>
                <li><a href="<?= e($basePath . '/auth/login.php') ?>" class="nav-link">Entrar</a></li>
                <li><a href="<?= e($basePath . '/auth/cadastro.php') ?>" class="btn btn-sm btn-primary">Cadastre-se</a></li>
            <?php endif; ?>
        </ul>
    </div>
</nav>
