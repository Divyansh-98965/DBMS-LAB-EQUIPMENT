<?php
$user = current_user();
$nav = [
 ['dashboard','Dashboard','fa-gauge-high'],
 ['equipment','Equipment','fa-microscope'],
 ['laboratories','Laboratories','fa-building'],
 ['consumables','Consumables','fa-boxes-stacked'],
 ['borrowings','Borrowing','fa-arrow-right-arrow-left'],
 ['maintenance','Maintenance','fa-screwdriver-wrench'],
 ['transactions','Transactions','fa-clock-rotate-left'],
 ['departments','Departments','fa-diagram-project'],
 ['categories','Categories','fa-layer-group'],
 ['suppliers','Suppliers','fa-truck'],
 ['users','Users','fa-users'],
];
$current = $_GET['page'] ?? 'dashboard';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(page_title($current)) ?> · LabTrace | NSUT</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="assets/css/app.css" rel="stylesheet">
</head>
<body>
<div class="app-shell">
<aside class="sidebar">
  <a class="brand" href="index.php"><span class="brand-mark">NSUT</span><span>LabTrace<small>LABORATORY MANAGEMENT</small></span></a>
  <div class="nav-label">WORKSPACE</div>
  <?php foreach ($nav as [$key,$label,$icon]): ?>
    <?php if ($key !== 'users' || in_array($user['role'] ?? '', ['admin'], true)): ?>
    <a class="nav-link <?= $current === $key ? 'active' : '' ?>" href="index.php?page=<?= e($key) ?>">
      <i class="fa-solid <?= e($icon) ?>"></i><span><?= e($label) ?></span>
    </a>
    <?php endif; ?>
  <?php endforeach; ?>
  <div class="nav-label nav-label-spaced">DATABASE REFERENCE</div>
  <a class="nav-link <?= $current === 'schema' ? 'active' : '' ?>" href="index.php?page=schema"><i class="fa-solid fa-table-list"></i><span>Relational schema</span></a>
  <a class="nav-link <?= $current === 'er_diagram' ? 'active' : '' ?>" href="index.php?page=er_diagram"><i class="fa-solid fa-diagram-project"></i><span>ER diagram</span></a>
  <div class="sidebar-bottom">
    <div class="user-chip"><div class="avatar"><?= e(strtoupper(substr($user['full_name'] ?? 'U',0,1))) ?></div><div><strong><?= e($user['full_name'] ?? '') ?></strong><small><?= e(ucfirst($user['role'] ?? '')) ?></small></div></div>
    <a class="nav-link" href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i><span>Sign out</span></a>
  </div>
</aside>
<main class="main-area">
<header class="topbar"><div><div class="eyebrow">NETAJI SUBHAS UNIVERSITY OF TECHNOLOGY · LABTRACE</div><h1><?= e(page_title($current)) ?></h1></div><div class="topbar-right"><span class="topbar-institution">NSUT · DELHI</span><span class="top-avatar"><?= e(strtoupper(substr($user['full_name'] ?? 'U',0,1))) ?></span></div></header>
<div class="content">
<?php if (!empty($_SESSION['flash'])): $f=$_SESSION['flash']; unset($_SESSION['flash']); ?>
<div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert"><?= e($f['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
