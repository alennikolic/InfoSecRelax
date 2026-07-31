<?php
/**
 * includes/header.php - zajednički layout: <head>, bočna navigacija,
 * i otvaranje glavnog sadržaja.
 *
 * Očekuje da su $menu, $currentItem i $requestedSlug već postavljeni
 * u index.php pre uključivanja ovog fajla.
 */
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($currentItem['title']) ?> — InfoSecRelax</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="app-shell">

        <aside class="sidebar">
            <div class="sidebar-brand">
                <span class="brand-name">InfoSecRelax</span>
                <span class="brand-tag">ISO 27001, korak po korak</span>
            </div>

            <nav class="sidebar-nav">
                <?php
                $lastGroup = null;
                foreach ($menu as $item):
                    if ($item['group'] !== $lastGroup):
                        $lastGroup = $item['group'];
                ?>
                <div class="nav-group-title"><?= htmlspecialchars($lastGroup) ?></div>
                <?php endif; ?>
                <a href="?page=<?= htmlspecialchars($item['slug']) ?>"
                   class="nav-link<?= $requestedSlug === $item['slug'] ? ' active' : '' ?>">
                    <span class="nav-title"><?= htmlspecialchars($item['title']) ?></span>
                    <?php if (!empty($item['iso_ref'])): ?>
                    <span class="nav-ref"><?= htmlspecialchars($item['iso_ref']) ?></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </nav>
        </aside>

        <main class="main-content">
            <header class="content-header">
                <h1><?= htmlspecialchars($currentItem['title']) ?></h1>
                <?php if (!empty($currentItem['iso_ref'])): ?>
                <span class="content-ref"><?= htmlspecialchars($currentItem['iso_ref']) ?></span>
                <?php endif; ?>
            </header>

            <div class="content-body">
