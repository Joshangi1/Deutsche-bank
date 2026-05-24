<?php
$pageTitle = 'Client Onboarding Links';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
ensure_banking_schema();
$admin = require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    try {
        if (isset($_POST['save_agent_profile'])) {
            $displayName = trim((string) ($_POST['display_name'] ?? ''));
            $agentId = strtoupper(trim((string) ($_POST['agent_id'] ?? '')));
            if ($displayName === '') {
                throw new InvalidArgumentException('Enter the agent display name.');
            }
            if ($agentId === '') {
                $agentId = admin_agent_id($admin);
            }
            if (!preg_match('/^[A-Z0-9-]{3,40}$/', $agentId)) {
                throw new InvalidArgumentException('Agent ID may contain letters, numbers, and hyphens only.');
            }
            $existing = db()->prepare('SELECT id FROM admins WHERE agent_id=? AND id<>? LIMIT 1');
            $existing->execute([$agentId, (int) $admin['id']]);
            if ($existing->fetch()) {
                throw new InvalidArgumentException('That agent ID is already assigned.');
            }
            $photo = secure_admin_profile_photo_upload($_FILES['profile_photo'] ?? []);
            db()->prepare('UPDATE admins SET display_name=?, agent_id=?, profile_photo=COALESCE(?, profile_photo), status="active" WHERE id=?')
                ->execute([$displayName, $agentId, $photo, (int) $admin['id']]);
            log_admin((int) $admin['id'], 'agent_profile_update', 'Updated onboarding agent profile');
            flash('success', 'Agent profile saved.');
        }

        if (isset($_POST['create_onboarding_link'])) {
            $token = admin_onboarding_create_link((int) $admin['id'], $_POST);
            log_admin((int) $admin['id'], 'client_onboarding_link_create', 'Generated client onboarding link', null, null, [
                'client_email' => trim((string) ($_POST['client_email'] ?? '')),
                'country' => trim((string) ($_POST['country'] ?? '')),
            ]);
            flash('success', 'Onboarding link created: ' . admin_onboarding_link_public_url($token));
        }
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    header('Location: onboarding_links.php');
    exit;
}

$admin = current_admin() ?: $admin;
$links = db()->query('SELECT l.*, a.name AS admin_name, a.display_name, a.agent_id
    FROM admin_onboarding_links l
    JOIN admins a ON a.id = l.admin_id
    ORDER BY l.created_at DESC
    LIMIT 150')->fetchAll();
$agentName = admin_display_name($admin);
$agentId = admin_agent_id($admin);
$agentPhoto = admin_profile_photo_url($admin['profile_photo'] ?? null);
$countryOptions = [
    '' => 'Optional country',
    'United States' => 'United States',
    'United Kingdom' => 'United Kingdom',
    'Canada' => 'Canada',
    'Nigeria' => 'Nigeria',
    'Australia' => 'Australia',
    'Germany' => 'Germany',
    'Switzerland' => 'Switzerland',
];
include __DIR__ . '/../includes/admin_header.php';
?>
<div class="banking-hero security-hero mb-4">
    <div>
        <div class="eyebrow">Agent onboarding</div>
        <h2>Create secure client signup links</h2>
        <p>Generate one-time account opening links that show the client who is onboarding them.</p>
    </div>
    <i class="fa-solid fa-user-shield"></i>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-4">
        <form class="table-card p-4 h-100" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="admin-agent-profile mb-3">
                <div class="admin-agent-photo">
                    <?php if ($agentPhoto): ?><img src="<?= e($agentPhoto) ?>" alt="<?= e($agentName) ?>"><?php else: ?><span><?= e(initials_from_name($agentName)) ?></span><?php endif; ?>
                </div>
                <div>
                    <h5 class="fw-bold mb-1">Agent profile</h5>
                    <p class="muted mb-0">Shown publicly on valid onboarding links.</p>
                </div>
            </div>
            <label class="form-label">Display name</label>
            <input name="display_name" class="form-control mb-3" maxlength="140" value="<?= e($agentName) ?>" required>
            <label class="form-label">Agent ID</label>
            <input name="agent_id" class="form-control mb-3 text-uppercase" maxlength="40" value="<?= e($agentId) ?>" required>
            <label class="form-label">Profile photo</label>
            <input name="profile_photo" type="file" accept="image/jpeg,image/png,image/webp" class="form-control mb-3">
            <button name="save_agent_profile" value="1" class="btn btn-light border w-100">Save agent profile</button>
        </form>
    </div>
    <div class="col-xl-8">
        <form class="table-card p-4 h-100" method="post">
            <?= csrf_field() ?>
            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <h5 class="fw-bold mb-1">Create onboarding link</h5>
                    <p class="muted mb-0">Client details are optional and used only to personalize the signup form.</p>
                </div>
                <span class="status-pill status-success">Secure token</span>
            </div>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Client name optional</label><input name="client_name" class="form-control" maxlength="140"></div>
                <div class="col-md-6"><label class="form-label">Client email optional</label><input name="client_email" type="email" class="form-control" maxlength="160"></div>
                <div class="col-md-6">
                    <label class="form-label">Country optional</label>
                    <select name="country" class="form-select">
                        <?php foreach ($countryOptions as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label">Expiry date optional</label><input name="expires_at" type="date" class="form-control"></div>
                <div class="col-12"><label class="form-label">Internal note optional</label><textarea name="note" class="form-control" rows="3" maxlength="1000"></textarea></div>
                <div class="col-12"><button name="create_onboarding_link" value="1" class="btn btn-navy"><i class="fa-solid fa-link me-1"></i>Create onboarding link</button></div>
            </div>
        </form>
    </div>
</div>

<div class="table-card">
    <div class="p-4 d-flex justify-content-between gap-3 flex-wrap">
        <div>
            <h5 class="fw-bold mb-0">Generated onboarding links</h5>
            <p class="muted mb-0">Track link status without exposing admin email to clients.</p>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Client</th><th>Agent</th><th>Status</th><th>Created</th><th>Used</th><th>Link</th></tr></thead>
            <tbody>
            <?php foreach ($links as $link): ?>
                <?php
                    $status = admin_onboarding_link_effective_status($link);
                    $statusClass = $status === 'active' ? 'success' : ($status === 'used' ? 'warning' : 'danger');
                    $publicUrl = admin_onboarding_link_public_url((string) $link['token']);
                    $creatorName = admin_display_name(['display_name' => $link['display_name'] ?? null, 'name' => $link['admin_name'] ?? null]);
                    $creatorAgentId = admin_agent_id(['agent_id' => $link['agent_id'] ?? null, 'id' => $link['admin_id'] ?? null]);
                ?>
                <tr>
                    <td>
                        <strong><?= e($link['client_name'] ?: 'Open client') ?></strong>
                        <div class="small muted"><?= e($link['client_email'] ?: 'No email prefilled') ?></div>
                        <?php if (!empty($link['country'])): ?><div class="small muted"><?= e($link['country']) ?></div><?php endif; ?>
                    </td>
                    <td><strong><?= e($creatorName) ?></strong><div class="small muted">Agent ID: <?= e($creatorAgentId) ?></div></td>
                    <td><span class="status-pill status-<?= e($statusClass) ?>"><?= e(strtoupper($status)) ?></span><?php if (!empty($link['expires_at'])): ?><div class="small muted">Expires <?= e($link['expires_at']) ?></div><?php endif; ?></td>
                    <td><?= e($link['created_at']) ?></td>
                    <td><?= e($link['used_at'] ?: '-') ?></td>
                    <td><button type="button" class="btn btn-sm btn-light border" data-copy-text="<?= e($publicUrl) ?>"><i class="fa-solid fa-copy me-1"></i><span>Copy link</span></button></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$links): ?><tr><td colspan="6" class="text-center muted py-5">No onboarding links generated yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
