<?php
/**
 * Script de Teste de Conexão com o Banco de Dados — Aula+
 * Suporta execução via terminal (CLI) ou navegador web (Apache/XAMPP).
 */

declare(strict_types=1);

require_once __DIR__ . '/database.php';

$isCli = (php_sapi_name() === 'cli');

if ($isCli) {
    echo "========================================\n";
    echo "  Aula+ — Diagnóstico de Conexão (CLI)  \n";
    echo "========================================\n";

    try {
        $pdo = getDatabaseConnection();
        echo "[SUCESSO] Conexão PDO estabelecida com sucesso com o MySQL!\n\n";

        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        echo "Tabelas detectadas no banco 'aula_mais':\n";
        if (empty($tables)) {
            echo "-> Nenhuma tabela encontrada ainda. Execute o script database/schema.sql no MySQL Workbench.\n";
        } else {
            foreach ($tables as $table) {
                echo "-> " . $table . "\n";
            }
        }
    } catch (Throwable $e) {
        echo "[ERRO NA CONEXÃO]\n";
        echo "Mensagem: " . $e->getMessage() . "\n";
        echo "Dica: Verifique se o serviço MySQL está ativo e se o banco 'aula_mais' foi criado.\n";
    }
    echo "========================================\n";
    exit;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Teste de Conexão — Aula+</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f4f6f9; color: #2d3748; padding: 40px; }
        .card { max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        h1 { font-size: 1.5rem; margin-top: 0; color: #1a202c; }
        .success { background: #def7ec; color: #03543f; padding: 15px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #319795; }
        .error { background: #fde8e8; color: #9b1c1c; padding: 15px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #e53e3e; }
        ul { margin: 10px 0; padding-left: 20px; }
        li { margin: 5px 0; }
        code { background: #edf2f7; padding: 2px 6px; border-radius: 4px; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Aula+ — Diagnóstico de Conexão com o Banco</h1>
        <?php
        try {
            $pdo = getDatabaseConnection();
            echo '<div class="success">';
            echo '<strong>✔ Conexão estabelecida com sucesso via PDO!</strong><br>';
            echo 'O PHP conseguiu se comunicar perfeitamente com o servidor MySQL.';
            echo '</div>';

            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            echo '<h3>Tabelas detectadas no banco <code>aula_mais</code>:</h3>';
            if (empty($tables)) {
                echo '<p style="color: #d69e2e;">⚠ Nenhuma tabela encontrada ainda. Execute o script <code>database/schema.sql</code> no MySQL Workbench para criar a estrutura.</p>';
            } else {
                echo '<ul>';
                foreach ($tables as $table) {
                    echo '<li>Tabela: <strong>' . htmlspecialchars($table, ENT_QUOTES, 'UTF-8') . '</strong></li>';
                }
                echo '</ul>';
            }
        } catch (Throwable $e) {
            echo '<div class="error">';
            echo '<strong>✖ Falha na Conexão:</strong><br>';
            echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            echo '<p style="font-size: 0.9em; margin-top: 10px;">Dica: Verifique se o MySQL está rodando no painel de controle do XAMPP e se o banco <code>aula_mais</code> foi criado.</p>';
            echo '</div>';
        }
        ?>
    </div>
</body>
</html>
