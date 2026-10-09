<?php
/**
 * Conexão com o Banco de Dados — Sistema Aula+ (IFRO)
 * 
 * Utiliza PDO (PHP Data Objects) com consultas preparadas para garantir
 * segurança máxima contra injeção de SQL (SQL Injection - RNF03) e
 * tratamento protegido de erros (RNF07).
 */

declare(strict_types=1);

/**
 * Retorna uma instância ativa da conexão PDO com o MySQL.
 * Implementa o padrão Singleton para reutilizar a mesma conexão durante a requisição.
 *
 * @throws RuntimeException Caso ocorra falha de conexão sem expor senhas na tela.
 * @return PDO Instância pronta para execução de consultas preparadas.
 */
function getDatabaseConnection(): PDO
{
    static $pdoInstance = null;

    if ($pdoInstance !== null) {
        return $pdoInstance;
    }

    // Permite carregar credenciais locais personalizadas sem expor no Git
    $localEnvFile = __DIR__ . '/local_env.php';
    if (file_exists($localEnvFile)) {
        require_once $localEnvFile;
    }

    // Configurações padrão para ambiente local XAMPP
    // Podem ser definidas via constantes locais ou variáveis de ambiente
    $host     = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: '127.0.0.1');
    $port     = defined('DB_PORT') ? DB_PORT : (getenv('DB_PORT') ?: '3306');
    $dbName   = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: 'aula_mais');
    $username = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: 'root');
    $password = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
    $charset  = 'utf8mb4';

    $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}";

    $options = [
        // Lança exceções PDOException sempre que ocorrer um erro de consulta
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        // Retorna registros como arrays associativos por padrão (ex: $linha['titulo'])
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Desativa a emulação de prepared statements, garantindo execução nativa segura
        PDO::ATTR_EMULATE_PREPARES   => false,
        // Garante timeout razoável para conexões
        PDO::ATTR_TIMEOUT            => 5,
    ];

    try {
        $pdoInstance = new PDO($dsn, $username, $password, $options);
        return $pdoInstance;
    } catch (PDOException $e) {
        // Registra o detalhe técnico no log do servidor sem exibir ao usuário final
        error_log('Erro na conexão com o banco aula_mais: ' . $e->getMessage());

        // Lança uma exceção limpa e amigável (RNF07: Segurança na exibição de erros)
        throw new RuntimeException(
            'Não foi possível conectar ao banco de dados do Aula+. ' .
            'Certifique-se de que o serviço MySQL está ativo no XAMPP e que o banco "aula_mais" foi criado.'
        );
    }
}
