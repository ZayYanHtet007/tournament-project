<?php


require_once __DIR__ . '/../database/dbConfig.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/approval_storage.php';

$tournament_id = isset($_GET['id']) ? intval($_GET['id']) : 1;


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve'])) {
        if (saveApproval($tournament_id, 'approved')) {

            header("Location: tournamentsDetail.php?id=" . $tournament_id . "&success=approved");
            exit();
        }
    } elseif (isset($_POST['reject'])) {
        if (saveApproval($tournament_id, 'rejected')) {
            header("Location: tournamentsDetail.php?id=" . $tournament_id . "&success=rejected");
            exit();
        }
    }
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


$approval_status = getApprovalStatus($tournament_id);


$success_message = '';
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'approved') {
        $success_message = 'Tournament approved successfully!';
    } elseif ($_GET['success'] === 'rejected') {
        $success_message = 'Tournament rejected!';
    }
}


$sql = "SELECT * FROM tournaments WHERE tournament_id = ?";
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
                    <label>Game Type</label>
                    <div class="t-d-card">
                        <?php
                        $game_type = $tournament['game_type'] ?? '';
                        echo $display_data['game_type'][$game_type] ?? ucfirst($game_type) ?? 'N/A';
                        ?>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <label>Match Type</label>
                    <div class="t-d-card">
                        <?php
                        $match_type = $tournament['match_type'] ?? '';
                        echo $display_data['match_type'][$match_type] ?? ucfirst($match_type) ?? 'N/A';
                        ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <label>Tournament Format</label>
                    <div class="t-d-card">
                        <?php
                        $format = $tournament['format'] ?? 'single_elimination';
                        echo $display_data['format'][$format] ?? ucfirst(str_replace('_', ' ', $format)) ?? 'Single Elimination';
                        ?>
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
                <button type="button" onclick="approveTournament()" class="t-d-btn"
                    id="approveBtn" <?php echo $approval_status == 'approved' ? 'disabled' : ''; ?>>
                    Approve
                </button>

                <button type="button" onclick="rejectTournament()" class="t-d-btn"
                    id="rejectBtn" <?php echo $approval_status == 'rejected' ? 'disabled' : ''; ?>>
                    Reject
                </button>

                <a href="tournaments.php" class="t-d-btn" style="margin-left: 300px; color: white; text-decoration: none; text-align: center;">
                    Back
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Global state
    let isProcessing = false;
    const tournamentId = <?php echo $tournament_id; ?>;


    async function handleApproval(action) {
        if (isProcessing) return;
        isProcessing = true;

        const isApprove = action === 'approve';
        const newStatus = isApprove ? 'approved' : 'rejected';


        updateUI(newStatus);

        Swal.fire({
            title: `${isApprove ? 'Approve' : 'Reject'} Tournament`,
            text: `Are you sure you want to ${action} this tournament?`,
            icon: isApprove ? 'question' : 'warning',
            showCancelButton: true,
            confirmButtonColor: isApprove ? '#3085d6' : '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: `Yes, ${action} it!`,
            cancelButtonText: 'Cancel',
            allowOutsideClick: false
        }).then(async (result) => {
            if (!result.isConfirmed) {
                revertUI('<?php echo $approval_status; ?>');
                isProcessing = false;
                return;
            }


            Swal.fire({
                title: 'Processing...',
                text: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            try {

                const response = await fetch('update_approval.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `tournament_id=${tournamentId}&action=${action}`
                });

                const data = await response.json();
                Swal.close();

                if (data.success) {

                    showSuccessMessage(data.message);


                    localStorage.setItem(`tournament_${tournamentId}_status`, newStatus);
                    localStorage.setItem(`tournament_${tournamentId}_updated`, Date.now());


                    broadcastUpdate(tournamentId, newStatus);

                } else {

                    revertUI('<?php echo $approval_status; ?>');
                    Swal.fire('Error!', data.message, 'error');
                }

            } catch (error) {
                Swal.close();
                revertUI('<?php echo $approval_status; ?>');
                Swal.fire('Error!', 'Network error. Please try again.', 'error');
                console.error('Error:', error);
            } finally {
                isProcessing = false;
            }
        });
    }


    function updateUI(newStatus) {
        const approveBtn = document.getElementById('approveBtn');
        const rejectBtn = document.getElementById('rejectBtn');




        if (approveBtn) approveBtn.disabled = newStatus === 'approved';
        if (rejectBtn) rejectBtn.disabled = newStatus === 'rejected';
    }

    function revertUI(originalStatus) {
        updateUI(originalStatus);
    }

    function showSuccessMessage(message) {

        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: message,
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true
        });
    }


    function broadcastUpdate(tournamentId, status) {
        if ('BroadcastChannel' in window) {
            try {
                const channel = new BroadcastChannel('tournament_approvals');
                channel.postMessage({
                    type: 'status_update',
                    tournamentId: tournamentId,
                    status: status,
                    timestamp: Date.now()
                });
            } catch (e) {
                console.log('BroadcastChannel not supported');
            }
        }
    }


    if ('BroadcastChannel' in window) {
        const channel = new BroadcastChannel('tournament_approvals');
        channel.onmessage = (event) => {
            if (event.data.type === 'status_update' &&
                event.data.tournamentId === tournamentId) {
                updateUI(event.data.status);
            }
        };
    }

    function checkLocalStorage() {
        const storedStatus = localStorage.getItem(`tournament_${tournamentId}_status`);
        const storedTime = localStorage.getItem(`tournament_${tournamentId}_updated`);

        if (storedStatus && storedTime) {
            const updateAge = Date.now() - parseInt(storedTime);

            if (updateAge < 60 * 60 * 1000) {
                updateUI(storedStatus);
            }
        }
    }


    document.addEventListener('DOMContentLoaded', function() {
        checkLocalStorage();
    });


    function approveTournament() {
        handleApproval('approve');
    }

    function rejectTournament() {
        handleApproval('reject');
    }
</script>