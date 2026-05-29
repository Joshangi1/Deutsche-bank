<?php
$pageTitle = 'Photo Catalog';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
ensure_banking_schema();
require_admin();

$agents = db()->query('SELECT id, name, display_name, email, agent_id, profile_photo, role, status FROM admins ORDER BY id DESC')->fetchAll();
$users = db()->query('SELECT id, first_name, last_name, email, avatar, status, created_at FROM users ORDER BY id DESC LIMIT 300')->fetchAll();

include __DIR__ . '/../includes/admin_header.php';
?>
<div class="banking-hero security-hero mb-4">
    <div>
        <div class="eyebrow">Persistent upload library</div>
        <h2>Photo Catalog</h2>
        <p>Review stored agent profile photos and client avatars from the permanent upload folders.</p>
    </div>
    <i class="fa-solid fa-images"></i>
</div>

<div class="row g-4">
    <div class="col-xl-5">
        <div class="table-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <h5 class="fw-bold mb-1">Banking agents</h5>
                    <p class="muted mb-0">Photos are loaded from <code>uploads/admin_profiles</code>.</p>
                </div>
                <span class="status-pill status-success"><?= count($agents) ?> agents</span>
            </div>

            <div class="d-flex flex-column gap-3">
                <?php foreach ($agents as $agent): ?>
                    <?php
                        $agentName = admin_display_name($agent);
                        $agentPhoto = admin_profile_photo_url($agent['profile_photo'] ?? null);
                        $stored = trim((string) ($agent['profile_photo'] ?? ''));
                    ?>
                    <div class="d-flex align-items-center gap-3 p-3 rounded-3 border bg-white">
                        <span class="admin-agent-photo flex-shrink-0">
                            <?php if ($agentPhoto): ?>
                                <img src="<?= e($agentPhoto) ?>" alt="<?= e($agentName) ?>">
                            <?php else: ?>
                                <span><?= e(initials_from_name($agentName)) ?></span>
                            <?php endif; ?>
                        </span>
                        <div class="min-w-0">
                            <strong><?= e($agentName) ?></strong>
                            <div class="small muted">Agent ID: <?= e(admin_agent_id($agent)) ?></div>
                            <div class="small muted"><?= e($agent['email'] ?? '') ?></div>
                            <div class="small <?= $agentPhoto ? 'text-success' : 'text-danger' ?>">
                                <?= $agentPhoto ? 'Photo file available' : ($stored !== '' ? 'Stored photo is missing from uploads/admin_profiles' : 'No photo uploaded') ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$agents): ?>
                    <div class="text-center muted py-5">No agents found.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-7">
        <div class="table-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <h5 class="fw-bold mb-1">Client profile photos</h5>
                    <p class="muted mb-0">Photos are loaded from <code>uploads/avatars</code>.</p>
                </div>
                <span class="status-pill status-success"><?= count($users) ?> clients</span>
            </div>

            <div class="row g-3">
                <?php foreach ($users as $client): ?>
                    <?php
                        $clientName = trim((string) ($client['first_name'] ?? '') . ' ' . (string) ($client['last_name'] ?? '')) ?: 'Client';
                        $avatar = avatar_url($client['avatar'] ?? null);
                        $stored = trim((string) ($client['avatar'] ?? ''));
                        $hasUploadedAvatar = $stored !== '' && strpos($avatar, 'default-avatar.svg') === false;
                    ?>
                    <div class="col-md-6">
                        <div class="d-flex align-items-center gap-3 p-3 rounded-3 border bg-white h-100">
                            <img class="avatar-sm flex-shrink-0" src="<?= e($avatar) ?>" alt="<?= e($clientName) ?>">
                            <div class="min-w-0">
                                <strong><?= e($clientName) ?></strong>
                                <div class="small muted text-truncate"><?= e($client['email'] ?? '') ?></div>
                                <div class="small <?= $hasUploadedAvatar ? 'text-success' : ($stored !== '' ? 'text-danger' : 'muted') ?>">
                                    <?= $hasUploadedAvatar ? 'Photo file available' : ($stored !== '' ? 'Stored avatar is missing from uploads/avatars' : 'No photo uploaded') ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$users): ?>
                    <div class="col-12 text-center muted py-5">No clients found.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
