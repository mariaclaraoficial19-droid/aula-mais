-- =====================================================================
-- SCRIPT DE CRIAÇÃO DO BANCO DE DADOS — PROJETO AULA+ (IFRO)
-- Sistema: Aula+ — Simplificar o Estudo
-- Compatibilidade: MySQL 8.x / MariaDB (XAMPP / MySQL Workbench)
-- =====================================================================

-- 1. Criação do Banco de Dados (se não existir)
-- Usamos utf8mb4 para suporte completo a caracteres em português, acentos e emojis.
CREATE DATABASE IF NOT EXISTS aula_mais
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- Seleciona o banco para aplicar as tabelas
USE aula_mais;

-- =====================================================================
-- 2. Tabela: usuarios
-- Armazena os dados cadastrais de alunos e professores.
-- A senha NUNCA é salva em texto limpo; o campo armazena o hash do password_hash().
-- =====================================================================
CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    perfil ENUM('aluno', 'professor') NOT NULL DEFAULT 'aluno',
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 3. Tabela: materias
-- Armazena o catálogo de disciplinas cadastradas por professores.
-- O nome da matéria é único para evitar duplicidades (ex: "Programação Web").
-- =====================================================================
CREATE TABLE IF NOT EXISTS materias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE,
    descricao TEXT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 4. Tabela: materiais
-- Armazena os conteúdos, resumos, anotações de estudo e links úteis.
-- Relaciona-se com a matéria (materia_id) e com o professor autor (usuario_id).
-- =====================================================================
CREATE TABLE IF NOT EXISTS materiais (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    materia_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    titulo VARCHAR(180) NOT NULL,
    assunto VARCHAR(120) NOT NULL,
    descricao TEXT NOT NULL,
    conteudo LONGTEXT NULL,
    link_externo VARCHAR(255) NULL,
    resumo_ia LONGTEXT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Chaves Estrangeiras com proteção de integridade referencial:
    -- ON DELETE RESTRICT impede que uma matéria ou professor com materiais vinculados
    -- seja excluído acidentalmente sem antes tratar os materiais dependentes.
    CONSTRAINT fk_materiais_materia
        FOREIGN KEY (materia_id)
        REFERENCES materias(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_materiais_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    -- Índices para otimizar pesquisas por título e assunto
    -- Definidos diretamente na tabela para compatibilidade total com MySQL e MariaDB
    INDEX idx_materiais_titulo (titulo),
    INDEX idx_materiais_assunto (assunto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

