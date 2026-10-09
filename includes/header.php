<?php
/**
 * Cabeçalho Padrão do Sistema Aula+ (IFRO)
 */
declare(strict_types=1);

require_once __DIR__ . '/security.php';

$pageTitle = isset($pageTitle) ? $pageTitle . ' — Aula+' : 'Aula+ — Simplificar o Estudo';
$basePath = getBasePath();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= e($basePath . '/public/css/style.css') ?>">
</head>
<body>

<?php require_once __DIR__ . '/nav.php'; ?>

<main class="main-content">
    <div class="container">
        <?php
        $mensagensFlash = getFlashMessages();
        if (!empty($mensagensFlash)):
            foreach ($mensagensFlash as $flash):
                $tipoClass = match ($flash['tipo']) {
                    'sucesso' => 'alert-sucesso',
                    'erro'    => 'alert-erro',
                    default   => 'alert-aviso'
                };
        ?>
            <div class="alert <?= e($tipoClass) ?>" role="alert">
                <?= e($flash['mensagem']) ?>
            </div>
        <?php
            endforeach;
        endif;
        ?>
