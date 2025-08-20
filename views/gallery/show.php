<div class="gallery-container">
    <header class="gallery-header">
        <h2><?php echo htmlspecialchars($gallery['name']); ?></h2>
        <div class="gallery-meta">
            <span><?php echo count($media); ?> Medien</span>
        </div>
    </header>
    
    <?php if (empty($media)): ?>
    <div class="empty-state">
        <p>Diese Galerie enthält noch keine Medien.</p>
    </div>
    <?php else: ?>
    <div class="media-gallery">
        <?php foreach ($media as $item): ?>
        <div class="media-item" onclick="openLightbox(<?php echo htmlspecialchars(json_encode($item)); ?>)">
            <?php if ($item['type'] === 'photo'): ?>
            <img src="/uploads/<?php echo htmlspecialchars($item['filename']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" loading="lazy">
            <?php else: ?>
            <div class="video-thumbnail">
                <video>
                    <source src="/uploads/<?php echo htmlspecialchars($item['filename']); ?>" type="<?php echo htmlspecialchars($item['mime_type']); ?>">
                </video>
                <div class="play-button">▶</div>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Lightbox -->
<div id="lightbox" class="lightbox" onclick="closeLightbox()">
    <div class="lightbox-content" onclick="event.stopPropagation()">
        <button class="lightbox-close" onclick="closeLightbox()">&times;</button>
        <div class="lightbox-media"></div>
        <div class="lightbox-info">
            <h4 id="lightbox-title"></h4>
            <p id="lightbox-date"></p>
        </div>
        <div class="lightbox-nav">
            <button id="prev-btn" onclick="navigateLightbox(-1)">‹</button>
            <button id="next-btn" onclick="navigateLightbox(1)">›</button>
        </div>
    </div>
</div>

<script>
const mediaItems = <?php echo json_encode($media); ?>;
let currentMediaIndex = 0;
</script>