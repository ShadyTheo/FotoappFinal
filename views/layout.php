<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Foto-Galerie</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="nav-brand">
                <h1>Foto-Galerie</h1>
            </div>
            <?php if (isset($_SESSION['user_id'])): ?>
            <div class="nav-menu">
                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                <a href="/admin" class="nav-link">Dashboard</a>
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