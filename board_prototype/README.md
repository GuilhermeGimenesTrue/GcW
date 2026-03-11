# Chat Público (PHP)

Este é um protótipo simples de chat público em PHP.

## Como usar

1. Coloque a pasta `board_prototype` dentro de `htdocs` do XAMPP.
2. Abra no navegador: `http://localhost/board_prototype/`
3. Envie mensagens e imagens. Todas as mensagens são exibidas para todos.

## Arquivos principais

- `index.php`: única página que serve o HTML e oferece endpoints para enviar/buscar mensagens.
- `uploads/`: pasta onde as imagens são salvas.

## Configuração de banco de dados (MySQL)

1. Crie um banco no MySQL (phpMyAdmin ou linha de comando):

```sql
CREATE DATABASE board_prototype
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

2. Certifique-se de que o usuário do MySQL em `index.php` (`root` / senha vazia por padrão) tenha acesso ao banco.

> Se quiser, ajuste as constantes `DB_HOST`, `DB_NAME`, `DB_USER` e `DB_PASS` em `index.php`.

## Notas

- Imagens são limitadas a 2 MB e tipos JPEG, PNG, GIF.
- O chat não usa autenticação: qualquer um pode enviar e ver mensagens.
