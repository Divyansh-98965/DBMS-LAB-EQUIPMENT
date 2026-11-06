<?php
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/functions.php';

if (!empty($_SESSION['user'])) redirect('index.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare("SELECT user_id, university_id, full_name, email, password_hash, role, account_status FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && $user['account_status'] === 'active' && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        unset($user['password_hash']);
        $_SESSION['user'] = $user;
        redirect('index.php');
    }
    $error = 'Invalid credentials or inactive account.';
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in · LabTrace | NSUT</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="assets/css/app.css" rel="stylesheet"></head>
<body class="login-page"><div class="login-card"><div class="login-institution">NETAJI SUBHAS UNIVERSITY OF TECHNOLOGY</div><div class="login-logo">LabTrace</div><div class="login-muted">Laboratory equipment and inventory management</div>
<?php if ($error): ?><div class="alert alert-danger py-2 small"><?= e($error) ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><div class="mb-3"><label class="form-label">Email address</label><input class="form-control" type="email" name="email" required autofocus></div><div class="mb-4"><label class="form-label">Password</label><input class="form-control" type="password" name="password" required></div><button class="btn btn-primary w-100">Sign in</button></form>
<div class="text-center mt-4 small text-secondary">Demo: admin@labtrace.local / password</div></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>
