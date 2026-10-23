-- =====================================================================
-- MIGRAÇÃO 001: Suporte a Anexos em PDF na Tabela de Materiais de Estudo
-- Sistema: Aula+ — Simplificar o Estudo (IFRO)
-- 
-- INSTRUÇÕES DE EXECUÇÃO MANUAL:
-- 1. Abra o MySQL Workbench ou acesse o phpMyAdmin (http://localhost/phpmyadmin).
-- 2. Selecione o banco de dados 'aula_mais' ou execute o script abaixo.
-- 3. Execute este script para adicionar as colunas de suporte a PDF.
-- =====================================================================

USE aula_mais;

-- Adiciona as colunas necessárias para anexos PDF de até 10 MB:
-- - arquivo_pdf: Nome físico aleatório e seguro do arquivo no disco
-- - arquivo_pdf_nome_original: Nome original enviado pelo professor
-- - arquivo_pdf_tamanho: Tamanho do arquivo em bytes (para formatação em KB/MB)
ALTER TABLE materiais
    ADD COLUMN arquivo_pdf VARCHAR(255) NULL AFTER link_externo,
    ADD COLUMN arquivo_pdf_nome_original VARCHAR(255) NULL AFTER arquivo_pdf,
    ADD COLUMN arquivo_pdf_tamanho INT UNSIGNED NULL AFTER arquivo_pdf_nome_original;
