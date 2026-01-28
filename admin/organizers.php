<?php
require_once __DIR__ . '/../database/dbConfig.php';
require_once __DIR__ . '/sidebar.php';

$searchTerm = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$sql = "SELECT * FROM users WHERE is_organizer = 1 AND (username LIKE '%$searchTerm%' OR email LIKE '%$searchTerm%' OR user_id LIKE '%$searchTerm') ORDER BY user_id ASC;";
$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Query Failed: " . mysqli_error($conn));
}


$success_message = '';
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'approved') {
        $success_message = 'organizer approved successfully!';
    } elseif ($_GET['success'] === 'rejected') {
        $success_message = 'organizer rejected!';
    }
}

$organizer = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['calculated_approval'] = $row['organizer_status'];
    $organizer[] = $row;
}
//Sort
usort($organizer, function ($a, $b) {
    $order = ['pending' => 1, 'approved' => 2, 'rejected' => 3];
    $statusA = $order[$a['calculated_approval']] ?? 4;
    $statusB = $order[$b['calculated_approval']] ?? 4;

    if ($statusA == $statusB) {
        return $a['user_id'] <=> $b['user_id'];
    }
    return $statusA <=> $statusB;
});


?>

<div class="main-content">
    <div class="main-content-container">
        <div class="tournament-header-section">
            <h2>Organizer Management</h2>
            <p>View and manage all organizers.</p>
        </div>

        <div class="search-wrapper">
            <form method="GET" action="" class="search-form">
                <button type="submit" class="search-btn">
                    <i class="ph ph-magnifying-glass"></i>
                </button>

                <input type="text" name="search" placeholder="Search tournament, games or IDs..."
                    value="<?= htmlspecialchars($searchTerm) ?>">

                <?php if (!empty($searchTerm)): ?>
                    <a href="organizers.php" class="reset-btn">
                        <i class="ph ph-x-circle"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>


        <div class="glass-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Organizer ID</th>
                            <th>Organizer Name</th>
                            <th>Email</th>
                            <th>Approval Status</th>
                            <th>Approval</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($organizer as $row):
                            $approval_status = $row['organizer_status'];

                            $approvalClass = match ($approval_status) {
                                'approved' => 'approval-success',
                                'rejected' => 'approval-danger',
                                'pending' => 'approval-pending',
                                default => 'approval-pending'
                            };
                        ?>
                            <tr>
                                <td onclick="window.location='organizerDetail.php?id=<?= $row['user_id'] ?>'"><span>#<?= $row['user_id'] ?></span></td>
                                <td onclick="window.location='organizerDetail.php?id=<?= $row['user_id'] ?>'"><?= htmlspecialchars($row['username']) ?></td>
                                <td onclick="window.location='organizerDetail.php?id=<?= $row['user_id'] ?>'" class="fw-bold text"><?= htmlspecialchars($row['email']) ?></td>
                                <td><span class="custom-badge <?= $approvalClass ?>"><?= strtoupper($row['organizer_status']) ?></span></td>
                                <td>
                                    <?php

                                    $status = $row['organizer_status'];
                                    ?>

                                    <button type="button"
                                        onclick="handleApproval(<?= $row['user_id'] ?>, 'approve')"
                                        class="org-btn-approve"
                                        id="approveBtn"
                                        <?= ($status === 'approved') ? 'disabled' : '' ?>>
                                        Approve
                                    </button>

                                    <button type="button"
                                        onclick="handleApproval(<?= $row['user_id'] ?>, 'reject')"
                                        class="org-btn-reject"
                                        id="rejectBtn"
                                        <?= ($status === 'rejected') ? 'disabled' : '' ?>>
                                        Reject
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (mysqli_num_rows($result) == 0): ?>
                            <tr>
                                <td colspan="9" class="text-center">No player found matching your search.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    async function handleApproval(userId, action) {
        const result = await Swal.fire({
            title: `${action} organizer?`,
            text: `Are you sure you want to ${action} this organizer?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: action === 'approve' ? '#198754' : '#dc3545',
            confirmButtonText: 'Yes, Change it!'
        });

        if (result.isConfirmed) {
            try {
                const response = await fetch('organizerApproval.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },

                    body: `user_id=${userId}&action=${action}`
                });

                const data = await response.json();

                if (data.success) {
                    Swal.fire('Success!', data.message, 'success')
                        .then(() => location.reload());
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            } catch (e) {
                console.error(e);
                Swal.fire('Error!', 'Something went wrong.', 'error');
            }
        }
    }
</script>