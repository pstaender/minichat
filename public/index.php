<?php

require __DIR__ . '/../vendor/autoload.php';

use Dumbo\Context;
use Dumbo\Dumbo;
use Dumbo\Helpers\CORS;
use Philipp\Minichat\Auth;
use Philipp\Minichat\Config;
use Philipp\Minichat\Providers\AnthropicProvider;
use Philipp\Minichat\Providers\OpenAIProvider;
use Philipp\Minichat\RoleStore;
use Philipp\Minichat\SessionStore;
use Philipp\Minichat\Sse;
use Philipp\Minichat\Usage;

$rootDir = dirname(__DIR__);

$config = new Config($rootDir . '/chat_config.json');
$usage = new Usage($rootDir . '/storage/usage');
$sessions = new SessionStore($rootDir . '/storage/sessions');
$roles = new RoleStore($rootDir . '/storage/roles');

function resolveProviderMessages(array $messages): array
{
    $result = [];

    foreach ($messages as $m) {
        $text = trim((string) ($m['text'] ?? ''));
        if ($text === '') {
            continue;
        }

        $role = in_array($m['role'], ['assistant', 'system'], true) ? $m['role'] : 'user';
        $result[] = ['role' => $role, 'text' => $text];
    }

    return $result;
}

function resolveProvider(string $name): ?object
{
    return match ($name) {
        'openai' => new OpenAIProvider(),
        'anthropic' => new AnthropicProvider(),
        default => null,
    };
}

/**
 * Validates a model spec against a user's configured `models` list and
 * resolves it to a provider + bare model name, checking that an API key
 * is actually configured for that provider. Shared by session creation
 * and changing a session's model.
 *
 * @return array{ok: true, provider: string, model: string}|array{ok: false, error: string, code: string}
 */
function resolveModel(array $user, string $modelSpec, Config $config): array
{
    if ($modelSpec === '' || !in_array($modelSpec, $user['models'], true)) {
        return ['ok' => false, 'error' => 'Invalid model.', 'code' => 'invalid_model'];
    }

    $resolved = Config::parseModelSpec($modelSpec);
    if (!$resolved['provider']) {
        return [
            'ok' => false,
            'error' => "Could not determine a provider for model \"$modelSpec\". Prefix it, e.g. \"openai.$modelSpec\".",
            'code' => 'model_provider_unresolved',
        ];
    }

    if (!$config->apiKeyFor($user, $resolved['provider'])) {
        return ['ok' => false, 'error' => 'No API key configured for this provider.', 'code' => 'missing_api_key'];
    }

    return ['ok' => true, 'provider' => $resolved['provider'], 'model' => $resolved['model']];
}

$app = new Dumbo();

// Reflect the request origin and allow credentials so the cookie-based
// session also works if the frontend isn't proxied behind the same origin.
// In normal dev use (Vite proxying /api to this server) requests are
// same-origin and this middleware never even engages.
$app->use(CORS::cors([
    'origin' => fn(string $origin) => $origin,
    'credentials' => true,
    'allow_headers' => ['Content-Type', 'Accept'],
]));

$requireAuth = function (Context $context, callable $next) use ($config) {
    $userId = Auth::userId();
    if (!$userId) {
        return $context->json(['error' => 'Unauthorized.', 'code' => 'unauthorized'], 401);
    }

    $user = $config->findById($userId);
    if (!$user) {
        Auth::logout();
        return $context->json(['error' => 'Unauthorized.', 'code' => 'unauthorized'], 401);
    }

    $context->set('user', $user);
    return $next($context);
};

$app->post('/api/login', function (Context $context) use ($config, $usage) {
    $body = $context->req->body();
    $password = (string) ($body['password'] ?? '');
    $email = isset($body['email']) && trim((string) $body['email']) !== '' ? trim((string) $body['email']) : null;

    if ($password === '') {
        return $context->json(['error' => 'Password is required.', 'code' => 'password_required'], 400);
    }

    $user = $config->findByCredentials($email, $password);
    if (!$user) {
        return $context->json(['error' => 'Invalid credentials.', 'code' => 'invalid_credentials'], 401);
    }

    Auth::login($user['id']);

    return $context->json(['user' => $config->publicUser($user), 'usage' => $usage->get($user['id'])]);
});

$app->post('/api/logout', function (Context $context) {
    Auth::logout();
    return $context->json(['ok' => true]);
}, [$requireAuth]);

$app->get('/api/me', function (Context $context) use ($config, $usage) {
    $user = $context->get('user');
    return $context->json(['user' => $config->publicUser($user), 'usage' => $usage->get($user['id'])]);
}, [$requireAuth]);

$app->get('/api/sessions', function (Context $context) use ($sessions) {
    $user = $context->get('user');
    return $context->json(['sessions' => $sessions->list($user['id'])]);
}, [$requireAuth]);

$app->post('/api/sessions', function (Context $context) use ($sessions, $roles, $config) {
    $user = $context->get('user');
    $body = $context->req->body();

    $modelSpec = (string) ($body['model'] ?? ($user['models'][0] ?? ''));
    $resolved = resolveModel($user, $modelSpec, $config);
    if (!$resolved['ok']) {
        return $context->json(['error' => $resolved['error'], 'code' => $resolved['code']], 400);
    }

    $roleId = isset($body['role_id']) && $body['role_id'] !== '' ? (string) $body['role_id'] : null;
    if ($roleId !== null && !$roles->get($user['id'], $roleId)) {
        return $context->json(['error' => 'Role not found.', 'code' => 'role_not_found'], 400);
    }

    $title = trim((string) ($body['title'] ?? '')) ?: 'New chat';
    $session = $sessions->create($user['id'], $resolved['provider'], $resolved['model'], $title, $roleId);

    return $context->json(['session' => $session], 201);
}, [$requireAuth]);

$app->get('/api/sessions/:id', function (Context $context) use ($sessions) {
    $user = $context->get('user');
    $session = $sessions->get($user['id'], (string) $context->req->param('id'));

    if (!$session) {
        return $context->json(['error' => 'Session not found.', 'code' => 'session_not_found'], 404);
    }

    return $context->json(['session' => $session]);
}, [$requireAuth]);

$app->patch('/api/sessions/:id', function (Context $context) use ($sessions, $config) {
    $user = $context->get('user');
    $body = $context->req->body();
    $id = (string) $context->req->param('id');

    $modelChange = null;
    if (isset($body['model'])) {
        $resolved = resolveModel($user, (string) $body['model'], $config);
        if (!$resolved['ok']) {
            return $context->json(['error' => $resolved['error'], 'code' => $resolved['code']], 400);
        }
        $modelChange = $resolved;
    }

    $updated = $sessions->update($user['id'], $id, function (array $s) use ($body, $modelChange) {
        if (isset($body['title']) && trim((string) $body['title']) !== '') {
            $s['title'] = trim((string) $body['title']);
        }
        if ($modelChange) {
            $s['provider'] = $modelChange['provider'];
            $s['model'] = $modelChange['model'];
        }
        return $s;
    });

    if (!$updated) {
        return $context->json(['error' => 'Session not found.', 'code' => 'session_not_found'], 404);
    }

    return $context->json(['session' => $updated]);
}, [$requireAuth]);

$app->delete('/api/sessions/:id', function (Context $context) use ($sessions) {
    $user = $context->get('user');
    $sessions->delete($user['id'], (string) $context->req->param('id'));
    return $context->json(['ok' => true]);
}, [$requireAuth]);

$app->get('/api/roles', function (Context $context) use ($roles) {
    $user = $context->get('user');
    return $context->json(['roles' => $roles->list($user['id'])]);
}, [$requireAuth]);

$app->post('/api/roles', function (Context $context) use ($roles) {
    $user = $context->get('user');
    $body = $context->req->body();

    $name = trim((string) ($body['name'] ?? ''));
    if ($name === '') {
        return $context->json(['error' => 'Role name is required.', 'code' => 'role_name_required'], 400);
    }

    $prompt = trim((string) ($body['prompt'] ?? ''));
    $role = $roles->create($user['id'], $name, $prompt);

    return $context->json(['role' => $role], 201);
}, [$requireAuth]);

$app->patch('/api/roles/:id', function (Context $context) use ($roles) {
    $user = $context->get('user');
    $body = $context->req->body();
    $id = (string) $context->req->param('id');

    $updated = $roles->update($user['id'], $id, function (array $r) use ($body) {
        if (isset($body['name']) && trim((string) $body['name']) !== '') {
            $r['name'] = trim((string) $body['name']);
        }
        if (isset($body['prompt'])) {
            $r['prompt'] = trim((string) $body['prompt']);
        }
        return $r;
    });

    if (!$updated) {
        return $context->json(['error' => 'Role not found.', 'code' => 'role_not_found'], 404);
    }

    return $context->json(['role' => $updated]);
}, [$requireAuth]);

$app->delete('/api/roles/:id', function (Context $context) use ($roles) {
    $user = $context->get('user');
    $roles->delete($user['id'], (string) $context->req->param('id'));
    return $context->json(['ok' => true]);
}, [$requireAuth]);

$app->post('/api/chat/stream', function (Context $context) use ($sessions, $usage, $roles, $config) {
    $user = $context->get('user');
    $userId = $user['id'];
    $body = $context->req->body();

    $sessionId = (string) ($body['session_id'] ?? '');
    $text = trim((string) ($body['text'] ?? ''));

    if ($sessionId === '') {
        return $context->json(['error' => 'session_id is required.', 'code' => 'session_id_required'], 400);
    }
    if ($text === '') {
        return $context->json(['error' => 'Message is empty.', 'code' => 'message_empty'], 400);
    }

    $session = $sessions->get($userId, $sessionId);
    if (!$session) {
        return $context->json(['error' => 'Session not found.', 'code' => 'session_not_found'], 404);
    }

    $limitError = $usage->checkLimit($user, $userId);
    if ($limitError) {
        return $context->json(['error' => $limitError['message'], 'code' => $limitError['code']], 429);
    }

    $provider = resolveProvider($session['provider']);
    if (!$provider) {
        return $context->json(['error' => 'Unsupported provider configured for this session.', 'code' => 'unsupported_provider'], 400);
    }

    $apiKey = $config->apiKeyFor($user, $session['provider']);
    if (!$apiKey) {
        return $context->json(['error' => 'No API key configured for this provider.', 'code' => 'missing_api_key'], 400);
    }

    $userMessage = [
        'id' => bin2hex(random_bytes(6)),
        'role' => 'user',
        'text' => $text,
        'created_at' => gmdate('c'),
    ];

    $isFirstMessage = empty($session['messages']);

    $history = $session['messages'] ?? [];
    if (!empty($session['role_id'])) {
        $role = $roles->get($userId, $session['role_id']);
        if ($role && trim($role['prompt']) !== '') {
            array_unshift($history, ['role' => 'system', 'text' => $role['prompt']]);
        }
    }
    $providerMessages = resolveProviderMessages(array_merge($history, [$userMessage]));

    // Release the session file lock before the (potentially long) HTTP
    // stream so other tabs/requests for this user aren't blocked on it.
    Auth::close();
    Sse::start();

    $fullText = '';
    $failed = false;
    $errorMessage = null;
    $usageResult = ['input_tokens' => 0, 'output_tokens' => 0];

    try {
        $usageResult = $provider->stream([
            'apiKey' => $apiKey,
            'model' => $session['model'],
            'messages' => $providerMessages,
            'onDelta' => function (string $chunk) use (&$fullText) {
                $fullText .= $chunk;
                Sse::emit('delta', ['text' => $chunk]);
            },
        ]);
    } catch (\Throwable $e) {
        $failed = true;
        $errorMessage = $e->getMessage();
    }

    $assistantMessage = [
        'id' => bin2hex(random_bytes(6)),
        'role' => 'assistant',
        'text' => $fullText,
        'created_at' => gmdate('c'),
        'error' => $failed ? $errorMessage : null,
    ];

    $updated = $sessions->update($userId, $sessionId, function (array $s) use (
        $userMessage,
        $assistantMessage,
        $isFirstMessage,
        $text
    ) {
        $s['messages'] = $s['messages'] ?? [];
        $s['messages'][] = $userMessage;
        $s['messages'][] = $assistantMessage;

        if ($isFirstMessage && (empty($s['title']) || $s['title'] === 'New chat')) {
            $s['title'] = mb_strlen($text) > 60 ? mb_substr($text, 0, 57) . '...' : $text;
        }

        return $s;
    });

    $newUsage = $usage->add($userId, $usageResult['input_tokens'], $usageResult['output_tokens']);

    if ($failed) {
        Sse::emit('error', ['message' => $errorMessage, 'usage' => $newUsage]);
    } else {
        Sse::emit('done', [
            'usage' => $newUsage,
            'title' => $updated['title'] ?? $session['title'],
            'message_id' => $assistantMessage['id'],
        ]);
    }

    exit;
}, [$requireAuth]);

$app->run();
