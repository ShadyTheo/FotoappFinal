<div class="admin-container">
    <div class="admin-header">
        <h2>Admin Dashboard</h2>
        <div class="header-actions">
            <a href="/admin/galleries/create" class="btn btn-primary">Neue Galerie erstellen</a>
            <a href="/admin/users" class="btn btn-secondary">Benutzer verwalten</a>
            <a href="/admin/activity" class="btn btn-outline">Aktivitätsprotokoll</a>
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
    
    <!-- Statistics Dashboard -->
    <div class="stats-overview">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📁</div>
                <div class="stat-content">
                    <h3><?php echo $stats['galleries']; ?></h3>
                    <p>Galerien</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🖼️</div>
                <div class="stat-content">
                    <h3><?php echo $stats['media']['total_media'] ?: 0; ?></h3>
                    <p>Medien gesamt</p>
                    <small><?php echo $stats['media']['total_photos'] ?: 0; ?> Fotos, <?php echo $stats['media']['total_videos'] ?: 0; ?> Videos</small>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-content">
                    <h3><?php echo $stats['users']['total_users'] ?: 0; ?></h3>
                    <p>Benutzer</p>
                    <small><?php echo $stats['users']['admin_count'] ?: 0; ?> Admins, <?php echo $stats['users']['client_count'] ?: 0; ?> Kunden</small>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">💾</div>
                <div class="stat-content">
                    <h3><?php echo $stats['media']['total_storage'] ? $this->formatFileSize($stats['media']['total_storage']) : '0 B'; ?></h3>
                    <p>Speicherplatz</p>
                    <small><?php echo $stats['media']['recent_uploads'] ?: 0; ?> neue Uploads (7 Tage)</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <?php if (!empty($stats['recent_uploads'])): ?>
    <div class="recent-activity">
        <h3>Letzte Uploads</h3>
        <div class="activity-list">
            <?php foreach (array_slice($stats['recent_uploads'], 0, 5) as $upload): ?>
            <div class="activity-item">
                <div class="activity-icon">
                    <?php echo $upload['type'] === 'photo' ? '🖼️' : '🎥'; ?>
                </div>
                <div class="activity-content">
                    <strong><?php echo htmlspecialchars($upload['title']); ?></strong>
                    <span>in <a href="/admin/galleries/<?php echo $upload['gallery_id']; ?>"><?php echo htmlspecialchars($upload['gallery_name']); ?></a></span>
                    <small><?php echo date('d.m.Y H:i', strtotime($upload['uploaded_at'])); ?></small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="galleries-section">
        <h3>Galerien</h3>
        <div class="galleries-grid">
        <?php if (empty($galleries)): ?>
        <div class="empty-state">
            <p>Noch keine Galerien vorhanden.</p>
            <a href="/admin/galleries/create" class="btn btn-primary">Erste Galerie erstellen</a>
        </div>
        <?php else: ?>
        <?php foreach ($galleries as $gallery): ?>
        <div class="gallery-card enhanced">
            <div class="gallery-header">
                <h3>
                    <a href="/admin/galleries/<?php echo $gallery['id']; ?>">
                        <?php echo htmlspecialchars($gallery['name']); ?>
                    </a>
                </h3>
                <div class="gallery-status">
                    <?php if ($gallery['has_paywall']): ?>
                    <span class="status-paywall">💰 <?php echo number_format($gallery['price_amount'], 2); ?> <?php echo $gallery['price_currency']; ?></span>
                    <?php endif; ?>
                    <?php if ($gallery['is_public']): ?>
                    <span class="status-public">Öffentlich</span>
                    <?php elseif ($gallery['access_code']): ?>
                    <span class="status-code">Mit Code</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="gallery-stats">
                <div class="stat-item">
                    <span class="stat-value"><?php echo $gallery['media_count'] ?: 0; ?></span>
                    <span class="stat-label">Gesamt</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?php echo $gallery['photo_count'] ?: 0; ?></span>
                    <span class="stat-label">Fotos</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?php echo $gallery['video_count'] ?: 0; ?></span>
                    <span class="stat-label">Videos</span>
                </div>
                <?php if ($gallery['total_size']): ?>
                <div class="stat-item">
                    <span class="stat-value"><?php echo $this->formatFileSize($gallery['total_size']); ?></span>
                    <span class="stat-label">Größe</span>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($gallery['last_upload']): ?>
            <div class="gallery-activity">
                <small>Letzter Upload: <?php echo date('d.m.Y H:i', strtotime($gallery['last_upload'])); ?></small>
            </div>
            <?php endif; ?>
            
            <div class="gallery-actions">
                <a href="/admin/galleries/<?php echo $gallery['id']; ?>" class="btn btn-primary btn-small">Bearbeiten</a>
                <a href="/gallery/<?php echo $gallery['id']; ?>" class="btn btn-outline btn-small" target="_blank">Ansehen</a>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>