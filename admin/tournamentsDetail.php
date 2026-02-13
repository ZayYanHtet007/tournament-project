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


$sql = "SELECT t.*, g.name AS game_name, g.genre, ta.rules AS announcement_rules
        FROM tournaments t 
        INNER JOIN games g ON t.game_id = g.game_id 
        LEFT JOIN tournament_announcements ta 
            ON ta.tournament_id = t.tournament_id
            AND ta.announcement_id = (
                SELECT ta2.announcement_id
                FROM tournament_announcements ta2
                WHERE ta2.tournament_id = t.tournament_id
                ORDER BY ta2.last_update DESC, ta2.created_at DESC
                LIMIT 1
            )
        WHERE t.tournament_id = ?";
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
            <div class="col-12">
                <label>Tournament Rules</label>
                <div class="t-d-card">
                    <?php
                    // Rules ရှိရင်ပြမယ်၊ မရှိရင် စာသားအနည်းငယ်ပြမယ်
                    $rules_text = $tournament['announcement_rules'] ?? '';
                    if (!empty($rules_text)) {
                        
                        echo nl2br(htmlspecialchars($rules_text));
                    } else {
                        echo '<span>No specific rules announced.</span>';
                    }
                    ?>
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

<style>
    .tournament-reject-backdrop.swal2-backdrop-show {
        background: radial-gradient(circle at 25% 20%, rgba(99, 102, 241, 0.2), transparent 45%), rgba(2, 6, 23, 0.55);
        backdrop-filter: blur(7px);
    }

    .tournament-reject-popup {
        background: linear-gradient(145deg, rgba(30, 41, 59, 0.82), rgba(15, 23, 42, 0.78)) !important;
        border: 1px solid rgba(148, 163, 184, 0.26) !important;
        border-radius: 18px !important;
        box-shadow: 0 24px 55px rgba(2, 6, 23, 0.6) !important;
        color: #e2e8f0 !important;
    }

    .tournament-reject-title {
        color: #f8fafc !important;
        font-weight: 800 !important;
        letter-spacing: 0.02em !important;
    }

    .tournament-reject-html {
        margin-top: 8px;
        text-align: left;
    }

    .tournament-reject-group {
        display: grid;
        gap: 8px;
    }

    .tournament-reject-option {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 9px 10px;
        border-radius: 10px;
        border: 1px solid rgba(148, 163, 184, 0.24);
        background: rgba(15, 23, 42, 0.45);
        color: #e2e8f0;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .tournament-reject-option:hover {
        border-color: rgba(129, 140, 248, 0.58);
        background: rgba(15, 23, 42, 0.68);
    }

    .tournament-reject-radio {
        appearance: none;
        -webkit-appearance: none;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        border: 2px solid rgba(148, 163, 184, 0.7);
        background: transparent;
        margin: 0;
        position: relative;
        flex-shrink: 0;
    }

    .tournament-reject-radio:checked {
        border-color: #818cf8;
    }

    .tournament-reject-radio:checked::after {
        content: "";
        position: absolute;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #818cf8;
        top: 2px;
        left: 2px;
    }

    .tournament-reject-text {
        font-size: 14px;
        font-weight: 600;
        line-height: 1.2;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .tournament-reject-other {
        width: 100%;
        margin-top: 10px;
        display: none;
        padding: 10px 12px;
        border-radius: 10px;
        border: 1px solid rgba(148, 163, 184, 0.32);
        background: rgba(15, 23, 42, 0.58);
        color: #f8fafc;
        min-height: 84px;
        resize: vertical;
        outline: none;
    }

    .tournament-reject-other:focus {
        border-color: rgba(129, 140, 248, 0.75);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.22);
    }

    .tournament-reject-confirm,
    .tournament-reject-cancel,
    .tournament-action-confirm {
        border-radius: 10px !important;
        padding: 10px 18px !important;
        font-weight: 700 !important;
        border: 1px solid transparent !important;
        transition: all 0.2s ease !important;
    }

    .tournament-reject-confirm {
        background: linear-gradient(135deg, #ef4444, #dc2626) !important;
        border-color: rgba(248, 113, 113, 0.45) !important;
        color: #fff !important;
    }

    .tournament-action-confirm {
        background: linear-gradient(135deg, #4f46e5, #4338ca) !important;
        border-color: rgba(129, 140, 248, 0.5) !important;
        color: #fff !important;
    }

    .tournament-reject-cancel {
        background: rgba(148, 163, 184, 0.18) !important;
        border-color: rgba(148, 163, 184, 0.3) !important;
        color: #f8fafc !important;
    }

    .tournament-reject-actions {
        gap: 16px !important;
        margin-top: 22px !important;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    async function handleApproval(action) {
        let rejectionReason = '';
        let result = null;

        if (action === 'reject') {
            result = await Swal.fire({
                title: 'Reject tournament?',
                icon: 'warning',
                background: 'transparent',
                color: '#f8fafc',
                customClass: {
                    container: 'tournament-reject-backdrop',
                    popup: 'tournament-reject-popup',
                    title: 'tournament-reject-title',
                    htmlContainer: 'tournament-reject-html',
                    actions: 'tournament-reject-actions',
                    confirmButton: 'tournament-reject-confirm',
                    cancelButton: 'tournament-reject-cancel'
                },
                buttonsStyling: false,
                showCancelButton: true,
                confirmButtonText: 'Yes, Change it!',
                html: `
                    <div class="tournament-reject-group">
                        <label class="tournament-reject-option">
                            <input type="radio" class="tournament-reject-radio" name="reject_reason" value="Incomplete details">
                            <span class="tournament-reject-text">Incomplete details</span>
                        </label>
                        <label class="tournament-reject-option">
                            <input type="radio" class="tournament-reject-radio" name="reject_reason" value="Not meeting requirements">
                            <span class="tournament-reject-text">Not meeting requirements</span>
                        </label>
                        <label class="tournament-reject-option">
                            <input type="radio" class="tournament-reject-radio" name="reject_reason" value="Policy violation">
                            <span class="tournament-reject-text">Policy violation</span>
                        </label>
                        <label class="tournament-reject-option">
                            <input type="radio" class="tournament-reject-radio" name="reject_reason" value="Other" id="tournament_reject_other_radio">
                            <span class="tournament-reject-text">Other</span>
                        </label>
                        <textarea id="tournament_reject_other_text" class="tournament-reject-other" placeholder="Type custom reason..."></textarea>
                    </div>
                `,
                didOpen: () => {
                    const otherRadio = document.getElementById('tournament_reject_other_radio');
                    const otherText = document.getElementById('tournament_reject_other_text');
                    const radios = document.querySelectorAll('input[name="reject_reason"]');

                    radios.forEach(r => r.addEventListener('change', () => {
                        if (otherRadio.checked) {
                            otherText.style.display = 'block';
                            otherText.focus();
                        } else {
                            otherText.style.display = 'none';
                            otherText.value = '';
                        }
                    }));
                },
                preConfirm: () => {
                    const selected = document.querySelector('input[name="reject_reason"]:checked');
                    if (!selected) {
                        Swal.showValidationMessage('Please select a rejection reason.');
                        return false;
                    }
                    if (selected.value === 'Other') {
                        const otherText = document.getElementById('tournament_reject_other_text').value.trim();
                        if (!otherText) {
                            Swal.showValidationMessage('Please provide a custom reason.');
                            return false;
                        }
                        return otherText;
                    }
                    return selected.value;
                }
            });

            if (!result.isConfirmed) {
                return;
            }
            rejectionReason = result.value || '';
        } else {
            result = await Swal.fire({
                title: `Confirm ${action}?`,
                icon: 'question',
                background: 'transparent',
                color: '#f8fafc',
                showCancelButton: true,
                confirmButtonText: 'Yes, proceed',
                customClass: {
                    container: 'tournament-reject-backdrop',
                    popup: 'tournament-reject-popup',
                    title: 'tournament-reject-title',
                    actions: 'tournament-reject-actions',
                    confirmButton: 'tournament-action-confirm',
                    cancelButton: 'tournament-reject-cancel'
                },
                buttonsStyling: false
            });
        }

        if (result.isConfirmed) {
            try {
                const response = await fetch('update_approval.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `tournament_id=<?= $tournament_id ?>&action=${action}&reason=${encodeURIComponent(rejectionReason)}`
                });
                const data = await response.json();
                if (data.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: data.message || 'Tournament approval updated.',
                        icon: 'success',
                        background: 'transparent',
                        color: '#f8fafc',
                        customClass: {
                            container: 'tournament-reject-backdrop',
                            popup: 'tournament-reject-popup',
                            title: 'tournament-reject-title',
                            confirmButton: 'tournament-action-confirm'
                        },
                        buttonsStyling: false
                    })
                        .then(() => location.reload());
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: data.message || 'Unable to update approval.',
                        icon: 'error',
                        background: 'transparent',
                        color: '#f8fafc',
                        customClass: {
                            container: 'tournament-reject-backdrop',
                            popup: 'tournament-reject-popup',
                            title: 'tournament-reject-title',
                            confirmButton: 'tournament-action-confirm'
                        },
                        buttonsStyling: false
                    });
                }
            } catch (e) {
                Swal.fire({
                    title: 'Error!',
                    text: 'Request failed.',
                    icon: 'error',
                    background: 'transparent',
                    color: '#f8fafc',
                    customClass: {
                        container: 'tournament-reject-backdrop',
                        popup: 'tournament-reject-popup',
                        title: 'tournament-reject-title',
                        confirmButton: 'tournament-action-confirm'
                    },
                    buttonsStyling: false
                });
            }
        }
    }
</script>
