-- Script de migração para adicionar coluna de perfil LinkedIn nos contatos
ALTER TABLE client_contacts
    ADD COLUMN linkedin_url VARCHAR(255) NULL AFTER info;
