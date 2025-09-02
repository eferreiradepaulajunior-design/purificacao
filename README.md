# Purificacao

Aplicação PHP para ingestão e enriquecimento de dados de clientes com uma interface web simples. O sistema busca empresas em uma API externa, salva no banco de dados e realiza o enriquecimento de informações em background.

## Requisitos

- PHP 8.1 ou superior
- Extensão `pdo_mysql`
- Servidor MySQL compatível
- Acesso a um terminal para executar scripts de worker

## Estrutura do Projeto

```
.
├── api/                 # Endpoints consumidos pelo dashboard e por integrações externas
├── assets/              # CSS/JS utilizados na interface
├── includes/            # Autenticação, conexão com o banco e funções auxiliares
├── workers/             # Scripts de processamento assíncrono
├── dashboard.php        # Interface principal de visualização
├── settings.php         # Tela de configuração das chaves e URLs
├── webhooks_docs.php    # Documentação do webhook de saída
└── ...                  # Demais arquivos de página (login, logout, etc.)
```

## Instalação

1. **Clone o repositório**
   ```bash
   git clone <url-do-repo>
   cd purificacao
   ```
2. **Configure variáveis de ambiente**
   ```bash
   cp .env.example .env
   ```
   Edite o arquivo `.env` e informe:
   - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` – credenciais do MySQL
   - `API_TOKEN` – token exigido pelo webhook de saída
   - `WEBHOOK_URL` – URL opcional para disparo automático do webhook
3. **Prepare o banco de dados**
   Crie um banco com as tabelas: `users`, `clients`, `client_contacts`, `settings` e `jobs`.
   Importar o esquema fica a cargo do usuário; um exemplo mínimo:
   ```sql
   CREATE TABLE users (...);
   CREATE TABLE clients (...);
   ```
4. **Inicie o servidor de desenvolvimento**
   ```bash
   php -S localhost:8000
   ```
   Acesse `http://localhost:8000` no navegador e faça login.

## Processamento e Worker

O processo de enriquecimento é feito por jobs armazenados na tabela `jobs`.
Execute o worker periodicamente (via cron ou manualmente):
```bash
php workers/enrichment_worker.php
```
Ele buscará jobs pendentes, consultará APIs externas e salvará os resultados.

## Serviço LinkedIn (FastAPI)

Um pequeno serviço em FastAPI disponível em `linkedin_service/` oferece integração com a SerpAPI para buscas de perfis. Para executá-lo em modo de desenvolvimento:

```bash
cd linkedin_service
uvicorn main:app --reload
```

Garanta que o PHP possa acessar `http://localhost:8000` (ajuste regras de firewall ou proxy conforme necessário).

## Endpoints Principais

- `GET /api/get_clients.php` – lista clientes e estatísticas para o dashboard
- `POST /api/run_worker.php` – agenda um job de sincronização e enriquecimento
- `GET /api/outbound_webhook.php?cnpj=...&token=...` – retorna dados enriquecidos de um CNPJ específico. O token deve corresponder a `API_TOKEN` definido no `.env`.

## Segurança

- Credenciais sensíveis ficam no arquivo `.env`, que é ignorado pelo Git.
- O webhook de saída exige um token válido para evitar acesso não autorizado.
- Recomenda-se executar o sistema atrás de HTTPS em produção.

## Desenvolvimento

Não há suíte de testes automatizados, porém é possível validar a sintaxe dos arquivos PHP:
```bash
php -l includes/env.php
php -l includes/db.php
php -l api/outbound_webhook.php
```
Contribuições são bem-vindas; mantenha o estilo simples e funcional.
Aplicação PHP para ingestão e enriquecimento de dados de clientes. 

## Configuração

1. Copie o arquivo `.env.example` para `.env` e ajuste as variáveis de acordo com o seu ambiente:
   ```bash
   cp .env.example .env
   ```
2. Defina as credenciais do banco de dados e o token de acesso da API no `.env`.
3. Certifique-se de que o servidor web tenha acesso a essas variáveis de ambiente.

## Segurança do Webhook

O endpoint `api/outbound_webhook.php` agora exige um token de acesso informado via query string:
```
/api/outbound_webhook.php?cnpj=12345678901234&token=SEU_TOKEN
```
Defina `API_TOKEN` no `.env` para controlar o acesso.

