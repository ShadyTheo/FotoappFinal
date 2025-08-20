<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Foto-Galerie</title>
    <link rel="stylesheet" href="/css/style.css">
    <?php if (session_status() === PHP_SESSION_ACTIVE): ?>
    <?php echo \App\Security\CSRFToken::getMetaTag(); ?>
    <?php endif; ?>
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="nav-brand">
                <h1>Foto-Galerie</h1>
            </div>
            <?php if (isset($_SESSION['user_id'])): ?>
            <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                <span>☰</span>
            </button>
            <div class="nav-menu" id="nav-menu">
                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                <a href="/admin" class="nav-link">Dashboard</a>
                <a href="/admin/users" class="nav-link">Benutzer</a>
                <a href="/admin/activity" class="nav-link">Aktivitätsprotokoll</a>
                <?php else: ?>
                <a href="/galleries" class="nav-link">Meine Galerien</a>
                <?php endif; ?>
                <form method="POST" action="/logout" style="display: inline;">
                    <button type="submit" class="nav-link btn-link">Abmelden</button>
                </form>
            </div>
            <?php endif; ?>
        </nav>
    </header>
    
    <main class="main-content">
        <?php echo $content; ?>
    </main>
    
    <script src="/js/app.js"></script>
</body>
</html>