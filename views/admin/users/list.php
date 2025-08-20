<div class="admin-container">
    <div class="admin-header">
        <h2>Benutzer verwalten</h2>
        <div class="header-actions">
            <a href="/admin/users/create" class="btn btn-primary">Neuen Benutzer erstellen</a>
            <a href="/admin" class="btn btn-secondary">Zurück</a>
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
    
    <?php if (empty($users)): ?>
    <div class="empty-state">
        <p>Noch keine Benutzer angelegt.</p>
        <a href="/admin/users/create" class="btn btn-primary">Ersten Benutzer erstellen</a>
    </div>
    <?php else: ?>
    <div class="users-table">
        <table class="table">
            <thead>
                <tr>
                    <th>E-Mail</th>
                    <th>Galerien</th>
                    <th>Upload-Statistiken</th>
                    <th>Erstellt am</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td>
                        <span class="badge"><?php echo $user['gallery_count']; ?> Galerien</span>
                        <?php if ($user['gallery_names']): ?>
                        <small class="gallery-names"><?php echo htmlspecialchars($user['gallery_names']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="upload-stats">
                            <div class="stat-item">
                                <span class="stat-label">Dateien:</span>
                                <span class="stat-value"><?php echo $user['total_files_uploaded']; ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Speicher:</span>
                                <span class="stat-value"><?php echo $user['total_storage_formatted']; ?></span>
                            </div>
                        </div>
                    </td>
                    <td><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></td>
                    <td>
                        <div class="action-buttons">
                            <a href="/admin/users/<?php echo $user['id']; ?>" class="btn btn-small btn-secondary">Bearbeiten</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>