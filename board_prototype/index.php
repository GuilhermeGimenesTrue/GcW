<?php
// Simple PHP chat board (single-page, public messages)
// Stores messages in SQLite and allows optional image uploads.

const DB_PATH = __DIR__ . '/chat.db';
const UPLOAD_DIR = __DIR__ . '/uploads';
const MAX_UPLOAD_BYTES = 2 * 1024 * 1024; // 2 MB
const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif'];

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? ($_SERVER['REQUEST_METHOD'] === 'POST' ? 'send' : 'view');

$db = new PDO('sqlite:' . DB_PATH);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA foreign_keys = ON');
$db->exec(
    'CREATE TABLE IF NOT EXISTS messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        message TEXT NOT NULL,
        image TEXT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    )'
);

function jsonResponse($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function escapeHtml($value) {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

if ($action === 'fetch') {
    $stmt = $db->prepare('SELECT id, name, message, image, created_at FROM messages ORDER BY id DESC LIMIT 150');
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Reverse so oldest is first
    $rows = array_reverse($rows);

    jsonResponse(['ok' => true, 'messages' => $rows]);
}

if ($action === 'send') {
    $name = trim((string)($_POST['name'] ?? '')) ?: 'Anônimo';
    $message = trim((string)($_POST['message'] ?? ''));

    if ($message === '') {
        jsonResponse(['ok' => false, 'error' => 'A mensagem não pode ficar em branco.']);
    }

    $imagePath = null;

    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['image'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(['ok' => false, 'error' => 'Erro ao fazer upload da imagem.']);
        }

        if ($file['size'] > MAX_UPLOAD_BYTES) {
            jsonResponse(['ok' => false, 'error' => 'A imagem deve ter no máximo 2 MB.']);
        }

        if (!in_array(mime_content_type($file['tmp_name']), ALLOWED_IMAGE_TYPES, true)) {
            jsonResponse(['ok' => false, 'error' => 'Tipo de imagem inválido. Apenas JPG, PNG e GIF são permitidos.']);
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $basename = bin2hex(random_bytes(10));
        $filename = $basename . '.' . ($extension ?: 'jpg');
        $target = UPLOAD_DIR . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            jsonResponse(['ok' => false, 'error' => 'Falha ao salvar a imagem.']);
        }

        $imagePath = 'uploads/' . $filename;
    }

    $stmt = $db->prepare('INSERT INTO messages (name, message, image) VALUES (:name, :message, :image)');
    $stmt->execute([
        ':name' => $name,
        ':message' => $message,
        ':image' => $imagePath,
    ]);

    jsonResponse(['ok' => true]);
}

// If we get here, show HTML page
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Chat Público</title>
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f0f2f6;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        header {
            padding: 1rem;
            background: #1b4b72;
            color: #fff;
            text-align: center;
        }

        main {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 1rem;
            max-width: 900px;
            margin: 0 auto;
            width: 100%;
        }

        form {
            background: #fff;
            border-radius: 10px;
            padding: 1rem;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 1rem;
        }

        form > * + * {
            margin-top: 0.75rem;
        }

        input[type="text"], textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ccd0d8;
            border-radius: 8px;
            font-size: 1rem;
        }

        textarea {
            resize: vertical;
            min-height: 5rem;
        }

        .row {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .row > * {
            flex: 1;
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
        }

        button {
            padding: 0.75rem 1.25rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
        }

        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .primary {
            background: #1b4b72;
            color: #fff;
        }

        .secondary {
            background: #f0f2f6;
            color: #1b4b72;
        }

        .messages {
            width: 100%;
            flex: 1;
            overflow: auto;
        }

        .message {
            background: #fff;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.04);
        }

        .message header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 0.5rem;
            gap: 1rem;
        }

        .message header small {
            color: #5a6470;
            font-size: 0.85rem;
        }

        .message p {
            margin: 0.3rem 0 0;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .message img {
            max-width: 100%;
            border-radius: 10px;
            margin-top: 0.75rem;
        }

        #status {
            margin-top: 0.5rem;
            color: #333;
            font-size: 0.9rem;
        }

        @media (max-width: 600px) {
            .row {
                flex-direction: column;
            }

            .actions {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <header>
        <h1>Chat Público</h1>
        <p>Digite algo, envie uma imagem (opcional) e a conversa aparecerá para todo mundo.</p>
    </header>

    <main>
        <form id="chatForm">
            <div class="row">
                <input type="text" name="name" placeholder="Seu nome (ou deixe em branco para anônimo)" maxlength="64" />
                <input type="file" name="image" accept="image/*" />
            </div>

            <textarea name="message" placeholder="Digite sua mensagem..." required maxlength="1000"></textarea>

            <div class="actions">
                <button type="submit" class="primary">Enviar</button>
                <button type="button" id="refreshBtn" class="secondary">Atualizar</button>
            </div>

            <div id="status" aria-live="polite"></div>
        </form>

        <div class="messages" id="messages"></div>
    </main>

    <script>
        const form = document.getElementById('chatForm');
        const messagesEl = document.getElementById('messages');
        const statusEl = document.getElementById('status');
        const refreshBtn = document.getElementById('refreshBtn');

        let isSending = false;

        function setStatus(text, isError = false) {
            statusEl.textContent = text;
            statusEl.style.color = isError ? '#c62828' : '#333';
        }

        function renderMessages(messages) {
            messagesEl.innerHTML = '';

            messages.forEach(msg => {
                const messageEl = document.createElement('article');
                messageEl.className = 'message';

                const header = document.createElement('header');
                const nameEl = document.createElement('strong');
                nameEl.textContent = msg.name || 'Anônimo';

                const timeEl = document.createElement('small');
                const ts = new Date(msg.created_at + 'Z');
                timeEl.textContent = ts.toLocaleString();

                header.appendChild(nameEl);
                header.appendChild(timeEl);

                const body = document.createElement('p');
                body.textContent = msg.message;

                messageEl.appendChild(header);
                messageEl.appendChild(body);

                if (msg.image) {
                    const img = document.createElement('img');
                    img.src = msg.image;
                    img.alt = '';
                    messageEl.appendChild(img);
                }

                messagesEl.appendChild(messageEl);
            });

            messagesEl.scrollTop = messagesEl.scrollHeight;
        }

        async function fetchMessages() {
            try {
                const resp = await fetch('?action=fetch');
                const data = await resp.json();
                if (!data.ok) {
                    setStatus('Falha ao buscar mensagens.', true);
                    return;
                }
                renderMessages(data.messages);
                setStatus('Última atualização: ' + new Date().toLocaleTimeString());
            } catch (error) {
                setStatus('Erro ao conectar-se ao servidor.', true);
            }
        }

        async function sendMessage(formData) {
            if (isSending) return;
            isSending = true;
            setStatus('Enviando...');

            try {
                const resp = await fetch('?action=send', { method: 'POST', body: formData });
                const data = await resp.json();

                if (!data.ok) {
                    setStatus(data.error || 'Erro ao enviar mensagem.', true);
                    return;
                }

                form.reset();
                await fetchMessages();
                setStatus('Enviado com sucesso!');
            } catch (error) {
                setStatus('Erro ao enviar mensagem.', true);
            } finally {
                isSending = false;
            }
        }

        form.addEventListener('submit', event => {
            event.preventDefault();
            const formData = new FormData(form);
            sendMessage(formData);
        });

        refreshBtn.addEventListener('click', () => {
            fetchMessages();
        });

        // Pull new messages every 8 seconds
        setInterval(fetchMessages, 8000);
        fetchMessages();
    </script>
</body>
</html>
