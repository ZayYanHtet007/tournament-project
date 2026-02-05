<?php
require_once __DIR__ . '/../database/dbConfig.php';
require_once __DIR__ . '/sidebar.php';

$tournament_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($tournament_id === 0) {
    die("Invalid Tournament ID.");
}

function formatDate($date)
{
    if (!$date || $date == '0000-00-00' || $date == '0000-00-00 00:00:00') {
        return 'Not set';
    }
    return date('F j, Y', strtotime($date));
}

function formatCurrency($amount)
{
    return $amount == '0.00' || $amount == 0 ? 'Free' : '$' . number_format(floatval($amount), 2);
}

$success_message = '';
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'approved') {
        $success_message = 'Tournament approved successfully!';
    } elseif ($_GET['success'] === 'rejected') {
        $success_message = 'Tournament rejected!';
    }
}

$sql = "SELECT t.*, g.name AS game_name, g.genre FROM tournaments t 
        INNER JOIN games g ON t.game_id = g.game_id WHERE t.tournament_id = ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $tournament_id);
    $stmt->execute();
    $tournament = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else {
    die("Error: " . $conn->error);
}

if (!$tournament) {
    die("Tournament not found!");
}
$current_approval = $tournament['admin_status'];
?>

<div class="container py-4">
    <div class="tournament-container">
        <h2 class="mb-4" style="color: var(--text-primary);">Tournament Details</h2>

        <?php if ($success_message): ?>
            <div class="alert alert-success border-0 mb-4" style="background: var(--approval-success-bg); color: var(--approval-success-text);">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-12">
                <label>Tournament Title</label>
                <div class="t-d-card"><?php echo htmlspecialchars($tournament['title']); ?></div>
            </div>
            <div class="col-12">
                <label>Description</label>
                <div class="t-d-card"><?php echo nl2br(htmlspecialchars($tournament['description'])); ?></div>
            </div>
            <div class="col-md-6">
                <label>Game Name</label>
                <div class="t-d-card"><?php echo htmlspecialchars($tournament['game_name']); ?></div>
            </div>
            <div class="col-md-6">
                <label>Game Genre</label>
                <div class="t-d-card"><?php echo htmlspecialchars($tournament['genre']); ?></div>
            </div>
            <div class="col-md-6">
                <label>Max Participants</label>
                <div class="t-d-card" style="color: var(--icon-color); font-weight: bold;">
                    <?php echo number_format($tournament['max_participants']); ?> Players
                </div>
            </div>
            <div class="col-md-6">
                <label>Entry Fee</label>
                <div class="t-d-card" style="color: var(--fee-color); font-weight: bold;">
                    <?php echo formatCurrency($tournament['fee']); ?>
                </div>
            </div>
            <div class="col-md-6">
                <label>Registration Deadline</label>
                <div class="t-d-card"><?php echo formatDate($tournament['registration_deadline']); ?></div>
            </div>
            <div class="col-md-6">
                <label>Start Date</label>
                <div class="t-d-card"><?php echo formatDate($tournament['start_date']); ?></div>
            </div>
            <div class="col-md-6">
                <label>Status</label>
                <div class="t-d-card" style="font-weight: 700; color: var(--status-ongoing-text);">
                    <?php echo strtoupper($tournament['status']); ?>
                </div>
            </div>
        </div>

        <div class="action-row">
            <button onclick="handleApproval('approve')" class="btn-custom btn-approve" id="approveBtn" <?= ($current_approval === 'approved') ? 'disabled' : '' ?>>
                Approve
            </button>

            <button onclick="handleApproval('reject')" class="btn-custom btn-reject" id="rejectBtn" <?= ($current_approval === 'rejected') ? 'disabled' : '' ?>>
                Reject
            </button>

            <a href="tournaments.php" class="btn-custom btn-back">
                ← Back to List
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    async function handleApproval(action) {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const result = await Swal.fire({
            title: `Confirm ${action}?`,
            icon: 'question',
            background: isDark ? '#1e293b' : '#ffffff',
            color: isDark ? '#f8fafc' : '#1e293b',
            showCancelButton: true,
            confirmButtonColor: action === 'approve' ? '#16a34a' : '#b91c1c',
            confirmButtonText: 'Yes, proceed'
        });

        if (result.isConfirmed) {
            try {
                const response = await fetch('update_approval.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `tournament_id=<?= $tournament_id ?>&action=${action}`
                });
                const data = await response.json();
                if (data.success) {
                    Swal.fire({
                            title: 'Success!',
                            icon: 'success',
                            background: isDark ? '#1e293b' : '#ffffff'
                        })
                        .then(() => location.reload());
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            } catch (e) {
                Swal.fire('Error!', 'Request failed.', 'error');
            }
        }
    }
</script>