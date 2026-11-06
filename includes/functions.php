<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function e(mixed $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}
function require_login(): void {
    if (empty($_SESSION['user'])) redirect('login.php');
}
function current_user(): array {
    return $_SESSION['user'] ?? [];
}
function require_role(array $roles): void {
    require_login();
    if (!in_array(current_user()['role'] ?? '', $roles, true)) {
        http_response_code(403);
        exit('You do not have permission to perform this action.');
    }
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function verify_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $sent = $_POST['csrf'] ?? '';
        if (!hash_equals($_SESSION['csrf'] ?? '', $sent)) {
            http_response_code(419);
            exit('Invalid form token. Go back and try again.');
        }
    }
}
function flash(string $message, string $type = 'success'): void {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}
function page_title(string $page): string {
    $titles = ['er_diagram' => 'ER Diagram', 'schema' => 'Relational Schema'];
    return $titles[$page] ?? ucwords(str_replace('_', ' ', $page));
}
