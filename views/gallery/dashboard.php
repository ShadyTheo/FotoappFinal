<div class="gallery-container">
    <div class="gallery-header">
        <h2>Meine Galerien</h2>
        <div class="gallery-meta">
            <span><?php echo count($galleries); ?> Galerien</span>
        </div>
    </div>
    
    <?php if ($flash = $this->getFlash('success')): ?>
    <div class="alert alert-success">
        <?php echo htmlspecialchars($flash); ?>
    </div>
    <?php endif; ?>
    
    <?php if ($flash = $this->getFlash('error')): ?>
    <div class="alert alert-error">
        <?php echo htmlspecialchars($flash); ?>
    </div>
    <?php endif; ?>
    
    <?php if (empty($galleries)): ?>
    <div class="empty-state">
        <p>Ihnen wurden noch keine Galerien zugewiesen.</p>
        <small>Wenden Sie sich an den Administrator, um Zugang zu Galerien zu erhalten.</small>
    </div>
    <?php else: ?>
    <div class="galleries-grid">
        <?php foreach ($galleries as $gallery): ?>
        <div class="gallery-card">
            <h3>
                <a href="/gallery/<?php echo $gallery['id']; ?>">
                    <?php echo htmlspecialchars($gallery['name']); ?>
                </a>
            </h3>
            <div class="gallery-meta">
                <span class="media-count"><?php echo $gallery['media_count']; ?> Medien</span>
                <span class="gallery-date">
                    <?php echo date('d.m.Y', strtotime($gallery['created_at'])); ?>
                </span>
            </div>
            <div class="gallery-actions">
                <a href="/gallery/<?php echo $gallery['id']; ?>" class="btn btn-primary">Galerie ansehen</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>