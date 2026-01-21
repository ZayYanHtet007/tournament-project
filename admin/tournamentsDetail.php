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


$sql = "SELECT t.*, g.name AS game_name, g.genre 
        FROM tournaments t
        INNER JOIN games g ON t.game_id = g.game_id 
        WHERE t.tournament_id = ?";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $tournament_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $tournament = $result->fetch_assoc();
    $stmt->close();
} else {
    die("Error preparing statement: " . $conn->error);
}

if (!$tournament) {
    die("Tournament not found!");
}
$current_approval = $tournament['admin_status'];
?>

<div class="container-fluid">
    <div class="tournament-container">
        <div class="tournament-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-3">
                        Tournament Details
                    </h1>
                </div>
            </div>
        </div>


        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert" id="successAlert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <script>
                setTimeout(() => {
                    if (window.history.replaceState && window.location.search.includes('success=')) {
                        const newUrl = window.location.pathname + '?id=<?php echo $tournament_id; ?>';
                        window.history.replaceState({}, document.title, newUrl);
                    }
                }, 3000);
            </script>
        <?php endif; ?>

        <div class="p-4">
            <div class="row">
                <div class="col-lg-6">
                    <label>Tournament Title</label>
                    <div class="t-d-card">
                        <?php echo htmlspecialchars($tournament['title'] ?? 'No Title'); ?>
                    </div>
                </div>

                <div class="col-lg-6">
                    <label>Description</label>
                    <div class="t-d-card">
                        <?php
                        $description = $tournament['description'] ?? 'No description provided.';
                        echo nl2br(htmlspecialchars($description));
                        ?>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <label>Game Name</label>
                    <div class="t-d-card">
                        <?php echo htmlspecialchars($tournament['game_name'] ?? 'N/A'); ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <label>Game genre</label>
                    <div class="t-d-card">
                        <?php echo htmlspecialchars($tournament['genre'] ?? 'N/A'); ?>
                    </div>
                </div>


            </div>

            <div class="row">
                <div class="col-md-6">
                    <label>Max Participants</label>
                    <div class="t-d-card">
                        <?php echo number_format($tournament['max_participants']) ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <label>Fee</label>
                    <div class="t-d-card">
                        <?php echo formatCurrency($tournament['fee'] ?? '0.00'); ?>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <label>Registration Deadline</label>
                    <div class="t-d-card">
                        <?php echo formatDate($tournament['registration_deadline'] ?? ''); ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <label>Start Date</label>
                    <div class="t-d-card">
                        <?php echo formatDate($tournament['start_date'] ?? ''); ?>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <label>Status</label>
                    <div class="t-d-card">
                        <?php
                        $status = strtolower($tournament['status'] ?? 'upcoming');
                        $badgeClass = "bg-info text-dark";
                        if ($status == 'ongoing') $badgeClass = 'bg-success';
                        if ($status == 'complete') $badgeClass = 'bg-secondary';
                        ?>
                        <span>
                            <?php echo strtoupper($tournament['status'] ?? ''); ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="t-d-btn-box">
                <button type="button" onclick="handleApproval('approve')" class="t-d-btn"
                    id="approveBtn" <?= ($current_approval === 'approved') ? 'disabled' : '' ?>>
                    Approve
                </button>

                <button type="button" onclick="handleApproval('reject')" class="t-d-btn"
                    id="rejectBtn" <?= ($current_approval === 'rejected') ? 'disabled' : '' ?>>
                    Reject
                </button>

                <a href="tournaments.php" class="t-d-btn" style="margin-left: 300px; color: white; text-decoration: none; text-align: center;">
                    Back
                </a>
            </div>


        </div>
    </div>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    async function handleApproval(action) {
        const result = await Swal.fire({
            title: `${action} tournament?`,
            text: `Are you sure you want to ${action} this tournament?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: action === 'approve' ? '#198754' : '#dc3545',
            confirmButtonText: 'Yes, Change it!'
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
                    Swal.fire('Success!', data.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            } catch (e) {
                Swal.fire('Error!', 'Something went wrong.', 'error');
            }
        }
    }
</script>