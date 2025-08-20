<div class="admin-container">
    <div class="admin-header">
        <h2>Aktivitätsprotokoll</h2>
        <div class="header-actions">
            <a href="/admin" class="btn btn-secondary">Zurück</a>
        </div>
    </div>
    
    <div class="activity-overview">
        <div class="activity-stats">
            <h3>Aktivitäten der letzten 7 Tage</h3>
            <div class="stats-summary">
                <p><strong><?php echo $totalCount; ?></strong> Aktivitäten insgesamt</p>
                <div class="recent-actions">
                    <?php
                    $actionCounts = [];
                    foreach ($activityStats as $stat) {
                        $key = $stat['action'];
                        $actionCounts[$key] = ($actionCounts[$key] ?? 0) + $stat['count'];
                    }
                    ?>
                    <?php foreach ($actionCounts as $action => $count): ?>
                    <span class="action-badge <?php echo $action; ?>">
                        <?php echo ucfirst($action); ?>: <?php echo $count; ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="activity-log-table">
        <div class="table-responsive">
            <table class="table">
            <thead>
                <tr>
                    <th>Zeit</th>
                    <th>Benutzer</th>
                    <th>Aktion</th>
                    <th>Typ</th>
                    <th>Details</th>
                    <th>IP-Adresse</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($activities)): ?>
                <tr>
                    <td colspan="6" class="text-center">Keine Aktivitäten gefunden</td>
                </tr>
                <?php else: ?>
                <?php foreach ($activities as $activity): ?>
                <tr>
                    <td>
                        <div class="activity-time">
                            <?php echo date('d.m.Y', strtotime($activity['created_at'])); ?>
                            <small><?php echo date('H:i:s', strtotime($activity['created_at'])); ?></small>
                        </div>
                    </td>
                    <td>
                        <?php if ($activity['user_email']): ?>
                        <span class="user-email"><?php echo htmlspecialchars($activity['user_email']); ?></span>
                        <?php else: ?>
                        <span class="text-muted">System</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="action-tag <?php echo $activity['action']; ?>">
                            <?php echo $this->getActionLabel($activity['action']); ?>
                        </span>
                    </td>
                    <td>
                        <span class="entity-type"><?php echo ucfirst($activity['entity_type']); ?></span>
                        <?php if ($activity['entity_id']): ?>
                        <small>#<?php echo $activity['entity_id']; ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="activity-details">
                            <?php echo htmlspecialchars($activity['details']); ?>
                        </div>
                    </td>
                    <td>
                        <small class="ip-address"><?php echo htmlspecialchars($activity['ip_address']); ?></small>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
    
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="/admin/activity?page=<?php echo $i; ?>" 
           class="page-link <?php echo $i === $currentPage ? 'active' : ''; ?>">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php
// Helper function for action labels
function getActionLabel($action) {
    $labels = [
        'login' => 'Anmeldung',
        'logout' => 'Abmeldung',
        'login_failed' => 'Fehlgeschl. Anmeldung',
        'create' => 'Erstellt',
        'update' => 'Aktualisiert',
        'delete' => 'Gelöscht',
        'upload' => 'Hochgeladen',
        'download' => 'Heruntergeladen',
        'view' => 'Angesehen'
    ];
    return $labels[$action] ?? ucfirst($action);
}
?>