# Purificacao

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

