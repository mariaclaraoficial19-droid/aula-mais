# Aula+ — Simplificar o Estudo 📚

> **Projeto Acadêmico**  
> Curso Técnico em Informática — Instituto Federal de Rondônia (IFRO)  
> Sistema web seguro para centralização e busca de materiais de estudo com suporte a resumos inteligentes por IA.

---

## 📌 Visão Geral

O **Aula+** nasceu com o objetivo de solucionar a dispersão de conteúdos didáticos entre diferentes pastas, aplicativos de mensagens e plataformas digitais.

A aplicação web permite que:
* **Professores** cadastrem disciplinas e publiquem materiais de estudo contendo anotações didáticas, resumos e links externos de referência.
* **Alunos** consultem e pesquisem materiais através de um sistema de busca multifiltro inteligente por matéria, título ou assunto.
* Cada docente gerencie com exclusividade os materiais de sua própria autoria.
* O sistema suporte a integração resiliente de resumos automatizados por Inteligência Artificial (Google Gemini) sem quebrar as operações básicas caso o serviço externo esteja offline.

---

## 🛠️ Tecnologias Utilizadas

* **Linguagem Back-end:** PHP 8.x nativo (orientado a rotas modulares com PDO).
* **Banco de Dados:** MySQL 8.x / MariaDB (InnoDB, codificação `utf8mb4_unicode_ci`).
* **Front-end:** HTML5 semântico, CSS3 moderno com variáveis (design responsivo) e JavaScript Vanilla.
* **Servidor Web Local:** Apache (XAMPP) ou Servidor Embutido do PHP (`php -S`).
* **Controle de Versão:** Git e GitHub.
* **Ferramenta de Gerenciamento de Banco:** MySQL Workbench / phpMyAdmin.

---

## 🔒 Princípios de Segurança e Requisitos Não Funcionais (RNF)

* **RNF01 (Armazenamento de Senhas):** Criptografia irreversível usando a função nativa `password_hash()` (Bcrypt/Argon2) e verificação estrita com `password_verify()`.
* **RNF02 (Validação no Servidor):** Validação e sanitização rigorosa de todos os dados recebidos antes da persistência.
* **RNF03 (Prevenção contra SQL Injection):** Uso exclusivo de PDO com `Prepared Statements` nativos e vinculação de parâmetros (`prepare` e `execute`).
* **RNF04 (Prevenção contra XSS):** Escape de todos os dados exibidos nas páginas com `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
* **RNF05 (Proteção contra CSRF):** Tokens criptográficos aleatórios por sessão validados em todas as requisições POST.
* **RNF06 (Sessões Seguras):** Regeneração de ID de sessão com `session_regenerate_id(true)` no login e cookies configurados com flags `HttpOnly` e `SameSite=Lax`.
* **RNF07 (Tratamento Protegido de Erros):** Tratamento de `PDOException` sem expor senhas, queries ou caminhos internos ao usuário final.

---

## 📂 Estrutura de Arquivos

```text
aula_mais/
├── auth/                 # Controladores e telas de autenticação
│   ├── cadastro.php      # Formulário de cadastro de alunos e professores
│   ├── login.php         # Formulário de autenticação e início de sessão
│   └── logout.php        # Encerramento seguro de sessão
├── config/               # Configuração e diagnóstico
│   ├── database.php      # Conexão PDO segura (Padrão Singleton)
│   ├── local_env.php     # (Opcional) Credenciais locais protegidas no .gitignore
│   └── test_connection.php # Diagnóstico visual/CLI de conexão com o banco
├── database/             # Scripts do banco de dados
│   └── schema.sql        # Criação não destrutiva do banco e tabelas relacionais
├── dashboard/            # Painel principal do sistema
│   └── index.php         # Visões diferenciadas para Alunos e Professores
├── includes/             # Componentes reutilizáveis
│   ├── header.php        # Cabeçalho HTML5 e exibição de alertas flash
│   ├── footer.php        # Rodapé institucional do IFRO
│   ├── nav.php           # Barra de navegação responsiva e adaptável
│   └── security.php      # Sessão segura, CSRF, sanitização e permissões
├── materias/             # Módulo de disciplinas
│   ├── index.php         # Catálogo de matérias e contagem de materiais
│   └── cadastrar.php     # Cadastro de novas matérias (exclusivo para professores)
├── materiais/            # Módulo de materiais de estudo
│   ├── index.php         # Busca multifiltro e catálogo geral
│   ├── visualizar.php    # Visualização de notas de aula, links e resumo IA
│   ├── cadastrar.php     # Cadastro de material vinculado ao professor
│   ├── editar.php        # Edição autorizada exclusiva do autor
│   └── excluir.php       # Exclusão segura com CSRF exclusiva do autor
├── public/               # Ativos estáticos públicos
│   ├── css/style.css     # Folha de estilos semântica e responsiva
│   └── js/main.js        # Confirmações e interações do front-end
├── index.php             # Página inicial pública com busca rápida
├── spec.md               # Especificação formal com as 8 seções do professor
├── CHANGELOG.md          # Histórico detalhado de versões e decisões
├── README.md             # Guia de configuração e execução
└── .gitignore            # Arquivos e credenciais ignorados pelo Git
```

---

## 🚀 Como Executar o Projeto em Outro Computador

Para rodar este projeto em qualquer máquina com Windows (ou Linux/macOS):

### 1. Pré-requisitos
* **PHP 8.0 ou superior** instalado.
* **MySQL Server 8.0** ou **XAMPP** com MySQL/MariaDB ativo.
* **MySQL Workbench** ou acesso ao phpMyAdmin.

---

### 2. Configurar o Banco de Dados

1. Inicie o serviço do **MySQL** (pelo painel do XAMPP ou pelo serviço do Windows).
2. Abra o **MySQL Workbench** e conecte-se à sua instância local.
3. Clique em **File -> Open SQL Script...** e selecione o arquivo:
   ```text
   database/schema.sql
   ```
4. Clique no **ícone de raio ⚡** para executar o script.
   * Ele criará de forma declarativa o banco de dados `aula_mais` e as tabelas `usuarios`, `materias` e `materiais`.
   * O script é **não destrutivo** (`CREATE TABLE IF NOT EXISTS`), sem comandos `DROP`.

---

### 3. Configurar Credenciais Locais (Se Necessário)

Por padrão, o arquivo `config/database.php` conecta com:
* **Host:** `127.0.0.1`
* **Porta:** `3306`
* **Banco:** `aula_mais`
* **Usuário:** `root`
* **Senha:** `""` (vazia, padrão do XAMPP).

Se o MySQL no seu computador possuir uma senha para o usuário `root`, crie o arquivo `config/local_env.php` (já protegido no `.gitignore`):
```php
<?php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'aula_mais');
define('DB_USER', 'root');
define('DB_PASS', 'sua_senha_aqui');
```

---

### 4. Formas de Execução

Você pode executar o Aula+ de duas maneiras:

#### Opção A — Pelo XAMPP (Apache)
1. Coloque a pasta do projeto dentro de `C:\xampp\htdocs\aula_mais` (ou crie um link simbólico com `mklink /J "C:\xampp\htdocs\aula_mais" "caminho_do_projeto"`).
2. Inicie os módulos **Apache** e **MySQL** no XAMPP Control Panel.
3. Abra o navegador e acesse:
   ```text
   http://localhost/aula_mais
   ```

#### Opção B — Pelo Servidor Embutido do PHP (Rápido e Portátil)
1. Abra o terminal (PowerShell ou CMD) dentro da pasta raiz do projeto (`aula_mais`).
2. Execute o comando:
   ```powershell
   php -S localhost:8000
   ```
3. Abra o navegador e acesse:
   ```text
   http://localhost:8000
   ```

---

### 5. Validando a Instalação

Acesse a página de diagnóstico:
* Pelo XAMPP: `http://localhost/aula_mais/config/test_connection.php`
* Pelo PHP Server: `http://localhost:8000/config/test_connection.php`
* Pelo terminal: `php config/test_connection.php`

A página informará se o PHP conseguiu se conectar ao MySQL e listará as 3 tabelas criadas.

---

## 🤖 Configuração do Recurso de IA (Google Gemini)

O Aula+ integra a API do **Google Gemini (modelo Gemini 1.5 Flash)** como um **recurso educacional complementar** para resumir conteúdos e notas de aula.

### 🔒 Proteção da Chave de API
* A chave da API **NUNCA** é exposta em arquivos públicos, scripts JavaScript, HTML ou no histórico do GitHub.
* A chave é lida exclusivamente pelo servidor PHP a partir do arquivo local `config/local_env.php` (ignorado no `.gitignore`) ou de variáveis de ambiente.

### 📝 Passo a Passo para Habilitar a IA:
1. Acesse o **[Google AI Studio](https://aistudio.google.com/)** e faça login com sua conta Google.
2. Clique no menu **Get API key** e em **Create API key**.
3. Copie o arquivo modelo `config/local_env.example.php` criando o arquivo `config/local_env.php`:
   ```php
   <?php
   // config/local_env.php (este arquivo é protegido e não vai para o Git)
   define('GEMINI_API_KEY', 'Cole_Sua_Chave_Aqui');
   ```
4. Salve o arquivo. Ao acessar qualquer material de estudo, clique em **✨ Gerar Resumo com Inteligência Artificial**.

### ⚠️ Resiliência e Limitações do Recurso:
* **Independência Operacional:** Caso a chave não esteja configurada ou o computador esteja sem acesso à internet, o Aula+ **não quebra**: o material didático permanece 100% legível e editável, exibindo apenas um aviso informativo.
* **Privacidade dos Alunos:** Nenhum dado pessoal (nomes, e-mails ou senhas) é enviado para o Google; apenas o título, assunto e anotações do material didático são processados.
* **Cota do Plano Gratuito:** O plano gratuito da API possui limite de 15 requisições por minuto (RPM). Se o limite for atingido, o sistema exibe aviso amigável solicitando que o usuário aguarde alguns instantes.

