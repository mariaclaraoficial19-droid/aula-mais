# CHANGELOG — Histórico de Alterações do Sistema Aula+

Todas as alterações relevantes, decisões de arquitetura e refinamentos do projeto são registrados neste documento.

O formato baseia-se no [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/).

---

## [0.1.0] - 2026-10-08

### Adicionado
- **Especificação Inicial (`spec.md`):** Criação das 8 seções obrigatórias do professor (Objetivo, Usuários, Casos de Uso, RF, RNF, Modelo de Dados/DER, Critérios de Aceite no formato "Dado que/Quando/Então", Decisão de Stack e Deploy).
- **Estrutura de Documentação:** Criação do `README.md` com visão geral e instruções de configuração para o ambiente XAMPP.
- **Configuração de Git:** Criação do `.gitignore` protegendo credenciais sensíveis, chaves de API e arquivos temporários.

### Decisões Técnicas Registradas
- **Back-end puro em PHP 8:** Optou-se por PHP sem frameworks pesados para garantir portabilidade nativa no XAMPP e facilitar a compreensão dos conceitos fundamentais de segurança (sessões, hashes de senha com `password_hash`, tokens CSRF e PDO Prepared Statements).
- **Desacoplamento da IA:** O módulo de Inteligência Artificial foi planejado como serviço complementar e assíncrono/resiliente; o sistema continuará 100% funcional caso o serviço de IA esteja indisponível ou a chave não esteja configurada.
- **Controle de Autoria dos Materiais:** Decidiu-se que professores só podem editar e excluir materiais cadastrados por eles mesmos (`usuario_id = session.id`), mantendo a integridade dos dados acadêmicos.
- **Escopo de Materiais sem Upload:** Confirmado que materiais conterão título, matéria, assunto, descrição, notas de estudo em texto e links externos, dispensando upload de arquivos físicos para manter o sistema leve e seguro.

## [0.2.0] - 2026-10-08

### Adicionado
- **Script SQL Inicial (`database/schema.sql`):** Criação declarativa e não destrutiva do banco `aula_mais` e das tabelas `usuarios`, `materias` e `materiais`, com chaves primárias, chaves estrangeiras com `ON DELETE RESTRICT`, codificação `utf8mb4` e índices de performance.
- **Camada de Conexão PDO Segura (`config/database.php`):** Implementação de função singleton `getDatabaseConnection()` com tratamento robusto de erros via `PDOException`, prepared statements nativos sem emulação e proteção contra vazamento de credenciais na interface.
- **Diagnóstico de Conexão (`config/test_connection.php`):** Página didática para testar a comunicação entre PHP e MySQL e verificar tabelas existentes de forma segura.
### Modificado / Ajustado
- **Compatibilidade Rigorosa no SQL (`database/schema.sql`):** Os índices de busca (`idx_materiais_titulo` e `idx_materiais_assunto`) foram movidos para o interior da declaração `CREATE TABLE IF NOT EXISTS materiais`, eliminando o comando `CREATE INDEX IF NOT EXISTS` que gera erro de sintaxe no MySQL padrão da Oracle (versões 5.7, 8.0 e 8.4).
- **Flexibilidade de Credenciais (`config/database.php`):** Adicionado suporte para arquivo opcional `config/local_env.php` (ignorado no Git) e suporte a execução de testes via CLI (terminal) e navegador web em `config/test_connection.php`.

## [0.3.0] - 2026-10-08

### Adicionado
- **Página Inicial (`index.php`):** Interface pública com apresentação do Aula+ (IFRO), busca rápida e destaques de materiais recentes com tratamento resiliente caso o banco ainda não esteja inicializado.
- **Segurança e Sessão Centralizada (`includes/security.php`):** Inicialização de sessão com cookies seguros (HttpOnly, SameSite=Lax), tokens CSRF em formulários POST, escape de dados contra XSS via função `e()`, controle de permissões por perfil (`aluno` vs `professor`) e mensagens flash.
- **Autenticação Completa (`auth/`):**
  - Cadastro de usuários (`auth/cadastro.php`) com validação rigorosa no servidor, verificação de e-mails duplicados e senhas protegidas com `password_hash()`.
  - Login seguro (`auth/login.php`) com `password_verify()`, regeneração de ID de sessão (`session_regenerate_id(true)`) e proteção contra ataques de enumeração.
  - Encerramento de sessão seguro (`auth/logout.php`) com destruição total da sessão e invalidação de cookies.
- **Painel Personalizado (`dashboard/index.php`):** Áreas distintas para alunos (consulta e acesso a materiais) e professores (gestão de suas publicações e métricas).
- **Módulo de Matérias (`materias/`):** Listagem de disciplinas com contagem de materiais (`materias/index.php`) e cadastro exclusivo para docentes (`materias/cadastrar.php`).
- **Módulo de Materiais (`materiais/`):**
  - Catálogo e busca multifiltro (`materiais/index.php`) combinando termos textuais e disciplinas via consultas preparadas seguras.
  - Visualização detalhada (`materiais/visualizar.php`) com notas de estudo, links externos, indicação do autor e espaço para resumo de IA.
  - Cadastro de materiais (`materiais/cadastrar.php`) vinculado automaticamente ao ID do professor autor.
  - Edição e exclusão (`materiais/editar.php`, `materiais/excluir.php`) restritas estritamente ao professor autor do conteúdo.
- **Identidade Visual e Scripts Front-end (`public/css/style.css`, `public/js/main.js`):** Folha de estilos semântica, responsiva e moderna, com controle em JS para confirmação de exclusões e fechamento de alertas.

## [0.3.1] - 2026-10-08

### Corrigido / Aprimorado na Auditoria
- **Redirecionamento Seguro no Logout (`auth/logout.php`):** Corrigida a concatenação da URL no header de redirecionamento para evitar formação de URL com barra dupla (`//auth/login.php`) quando executado no servidor embutido do PHP (`php -S`) com caminho base vazio.
- **Detecção Dinâmica do Caminho Base (`includes/security.php`):** A função `getBasePath()` foi aprimorada para comparar dinamicamente `DOCUMENT_ROOT` e a raiz do projeto com fallback de rotas, tornando o sistema imune ao nome da subpasta no XAMPP ou em virtual hosts.
- **Navegação Precisa para a Home (`includes/nav.php`):** Links para o início da aplicação agora utilizam explicitamente `$homeUrl`, garantindo compatibilidade com servidores Apache sem depender de reescritas de diretório.
- **Documentação para Outros Computadores (`README.md`):** Expandido o guia de execução com instruções passo a passo para XAMPP, servidor embutido `php -S`, configuração de senhas no MySQL via `local_env.php` e importação do banco.

## [0.4.0] - 2026-10-08

### Adicionado (Integração com Google Gemini API)
- **Serviço de IA Desacoplado (`services/gemini_service.php`):** Módulo autônomo para envio de notas de estudo à API do Google Gemini (modelo `gemini-1.5-flash`), com timeout estrito de 12 segundos, prompt pedagógico estruturado para estudantes do ensino médio/técnico e fallback seguro para conexões sem cURL.
- **Controlador de Geração de Resumos (`materiais/gerar_resumo.php`):** Endpoint POST protegido por CSRF que valida autenticação, verifica permissões de autoria (aluno/professor para geração inicial, professor autor para regeneração), invoca a IA sem enviar dados pessoais e persiste o resultado no campo `resumo_ia` via Prepared Statement PDO.
- **Interface e Feedback em `materiais/visualizar.php`:** Integração do botão de acionamento do resumo, visualização com formatação limpa e tratamento tolerante a falhas (exibe aviso amigável sem quebrar o acesso ao material didático caso o serviço externo esteja offline ou a chave não esteja configurada).
- **Modelo de Ambiente Local (`config/local_env.example.php`):** Arquivo modelo documentando como declarar a chave `GEMINI_API_KEY` de forma segura, mantendo-a fora do controle de versão do Git.



