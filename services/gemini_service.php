<?php
/**
 * Serviço de Integração com Google Gemini API — Sistema Aula+ (IFRO)
 * 
 * Implementa o Recurso Complementar de Resumos Inteligentes (RF11 e RF12):
 * - RNF07: Tratamento seguro de exceções e erros de rede
 * - RNF08: Desacoplamento e resiliência da IA (não quebra o sistema se offline)
 * - Segurança: Chave lida de configuração protegida, sem dados pessoais enviados
 */

declare(strict_types=1);

/**
 * Recupera a chave da API do Google Gemini de forma segura.
 * A chave é lida de config/local_env.php (ignorado no Git) ou variáveis de ambiente.
 *
 * @return string|null Chave de API ou null se não configurada.
 */
function obterChaveGemini(): ?string
{
    $localEnvFile = __DIR__ . '/../config/local_env.php';
    if (file_exists($localEnvFile)) {
        require_once $localEnvFile;
    }

    if (defined('GEMINI_API_KEY') && !empty(GEMINI_API_KEY)) {
        return (string)GEMINI_API_KEY;
    }

    $envKey = getenv('GEMINI_API_KEY');
    if (!empty($envKey)) {
        return (string)$envKey;
    }

    return null;
}

/**
 * Envia o conteúdo do material de estudo para a API do Google Gemini e retorna o resumo gerado.
 *
 * @param string $titulo Título do material de estudo.
 * @param string $assunto Assunto ou tópico da disciplina.
 * @param string $descricao Breve descrição fornecida pelo professor.
 * @param string|null $conteudo Texto completo de estudo e notas de aula.
 * @return array{sucesso: bool, resumo: ?string, erro: ?string}
 */
function gerarResumoGemini(string $titulo, string $assunto, string $descricao, ?string $conteudo): array
{
    $apiKey = obterChaveGemini();

    // 1. Verificação de Chave de API Configurada
    if (empty($apiKey)) {
        return [
            'sucesso' => false,
            'resumo'  => null,
            'erro'    => 'A chave da API do Google Gemini não foi configurada no servidor. Para habilitar este recurso complementar, configure a chave no arquivo config/local_env.php.'
        ];
    }

    // 2. Constrói o texto base para o resumo sem dados pessoais
    $textoBase = "TÍTULO: {$titulo}\nASSUNTO: {$assunto}\n";
    if (!empty($descricao)) {
        $textoBase .= "DESCRIÇÃO INICIAL: {$descricao}\n";
    }
    if (!empty($conteudo)) {
        $textoBase .= "CONTEÚDO DE ESTUDO:\n{$conteudo}\n";
    }

    // Limita o tamanho do texto para evitar estouro de tokens
    if (mb_strlen(trim($textoBase)) < 15) {
        return [
            'sucesso' => false,
            'resumo'  => null,
            'erro'    => 'O material não possui conteúdo textual suficiente para gerar um resumo educacional.'
        ];
    }

    // Corta texto de entrada muito extenso para proteção de cota (máx 15.000 caracteres)
    if (mb_strlen($textoBase) > 15000) {
        $textoBase = mb_substr($textoBase, 0, 15000) . "\n[... conteúdo truncado para o resumo ...]";
    }

    // 3. Prompt pedagógico direcionado ao público escolar/técnico
    $instrucaoPedagogica = "Você é um assistente educacional do Instituto Federal de Rondônia (IFRO). " .
        "Elabore um resumo claro, objetivo, didático e bem estruturado para estudantes do curso técnico. " .
        "Organize a resposta com:\n" .
        "1. 📌 Ideia Central (em 1 ou 2 frases concisas)\n" .
        "2. 🔍 Pontos-Chave (marcadores com os conceitos mais importantes)\n" .
        "3. 💡 Dica Prática de Estudo ou Aplicação\n" .
        "Utilize linguagem em português brasileiro, acessível e encorajadora. Não invente conteúdos além do material fornecido.";

    $promptCompleto = "{$instrucaoPedagogica}\n\n--- MATERIAL PARA RESUMO ---\n{$textoBase}";

    // 4. Estrutura o corpo da requisição JSON para a API Gemini
    $corpoRequisicao = json_encode([
        'contents' => [
            [
                'parts' => [
                    ['text' => $promptCompleto]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature'     => 0.3,
            'maxOutputTokens' => 1024,
        ]
    ], JSON_UNESCAPED_UNICODE);

    // Endpoint do modelo Gemini 1.5 Flash (rápido e compatível com o plano gratuito)
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . urlencode($apiKey);

    // 5. Execução da chamada HTTP com timeout estrito
    $respostaJson = false;
    $httpCode = 0;
    $curlError = '';

    if (extension_loaded('curl')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $corpoRequisicao,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'User-Agent: AulaMais-IFRO/1.0'
            ],
            CURLOPT_TIMEOUT        => 12, // Timeout total de 12 segundos (RNF08)
            CURLOPT_CONNECTTIMEOUT => 5,  // Timeout de conexão de 5 segundos
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $respostaJson = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
    } else {
        // Fallback via stream context caso cURL não esteja ativo
        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\nUser-Agent: AulaMais-IFRO/1.0\r\n",
                'content' => $corpoRequisicao,
                'timeout' => 12,
            ]
        ]);
        $respostaJson = @file_get_contents($url, false, $context);
        $httpCode = $respostaJson !== false ? 200 : 500;
    }

    // 6. Tratamento de Erros de Conexão e Rede
    if ($respostaJson === false || !empty($curlError)) {
        error_log('Falha de conexão com a API Gemini no Aula+: ' . $curlError);
        return [
            'sucesso' => false,
            'resumo'  => null,
            'erro'    => 'Não foi possível conectar ao serviço de IA (tempo limite excedido ou sem conexão com a internet). O material continua acessível normalmente.'
        ];
    }

    // 7. Tratamento de Códigos de Resposta HTTP da API do Google
    if ($httpCode !== 200) {
        $dadosErro = json_decode((string)$respostaJson, true);
        $mensagemDetalhe = $dadosErro['error']['message'] ?? '';
        error_log("Erro HTTP {$httpCode} retornado pelo Gemini: " . $mensagemDetalhe);

        if ($httpCode === 400 || $httpCode === 403) {
            return [
                'sucesso' => false,
                'resumo'  => null,
                'erro'    => 'A chave da API do Gemini informada é inválida ou não possui permissão de acesso.'
            ];
        }

        if ($httpCode === 429) {
            return [
                'sucesso' => false,
                'resumo'  => null,
                'erro'    => 'O limite de requisições por minuto da API do Gemini foi atingido. Aguarde alguns instantes e tente novamente.'
            ];
        }

        return [
            'sucesso' => false,
            'resumo'  => null,
            'erro'    => "O serviço do Google Gemini retornou instabilidade temporária (código HTTP {$httpCode}). O material de estudo permanece intacto."
        ];
    }

    // 8. Processamento do Resultado JSON
    $dados = json_decode((string)$respostaJson, true);
    $resumoGerado = $dados['candidates'][0]['content']['parts'][0]['text'] ?? null;

    if (empty($resumoGerado)) {
        return [
            'sucesso' => false,
            'resumo'  => null,
            'erro'    => 'O serviço de IA não retornou um resumo textual válido para este conteúdo.'
        ];
    }

    return [
        'sucesso' => true,
        'resumo'  => trim((string)$resumoGerado),
        'erro'    => null
    ];
}
