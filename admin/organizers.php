<?php
require_once __DIR__ . '/../database/dbConfig.php';
require_once __DIR__ . '/sidebar.php';

// 1. Define Search Term FIRST (so it can be used in pagination counts)
$searchTerm = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// 2. Setup Pagination Variables
$limit = 10; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// 3. Get Total Count for the specific search
$count_sql = "SELECT COUNT(*) AS total FROM users 
              WHERE is_organizer = 1 
              AND (username LIKE '%$searchTerm%' 
              OR email LIKE '%$searchTerm%' 
              OR user_id LIKE '%$searchTerm%')";
$count_result = mysqli_query($conn, $count_sql);
$total_rows = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_rows / $limit);

// 4. Fetch Paginated Data using LIMIT and OFFSET
$sql = "SELECT * FROM users 
        WHERE is_organizer = 1 
        AND (username LIKE '%$searchTerm%' 
        OR email LIKE '%$searchTerm%' 
        OR user_id LIKE '%$searchTerm%') 
        ORDER BY CASE WHEN organizer_status = 'pending' THEN 1 ELSE 2 END ASC, user_id ASC
        LIMIT $limit OFFSET $offset";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Query Failed: " . mysqli_error($conn));
}

// 5. Process Results
$organizer = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['calculated_approval'] = $row['organizer_status'];
    $organizer[] = $row;
}

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

                <input type="text" name="search" placeholder="Search name, email or IDs..."
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
                                    <button type="button"
                                        onclick="handleApproval(<?= $row['user_id'] ?>, 'approve')"
                                        class="org-btn-approve"
                                        <?= ($approval_status === 'approved') ? 'disabled' : '' ?>>
                                        Approve
                                    </button>

                                    <button type="button"
                                        onclick="handleApproval(<?= $row['user_id'] ?>, 'reject')"
                                        class="org-btn-reject"
                                        <?= ($approval_status === 'rejected') ? 'disabled' : '' ?>>
                                        Reject
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($organizer)): ?>
                            <tr>
                                <td colspan="5" class="text-center">No organizers found matching your search.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($searchTerm) ?>" class="pg-link"><i class="fas fa-chevron-left"></i> PREV</a>
            <?php endif; ?>

            <?php
            $start_loop = max(1, $page - 2);
            $end_loop = min($total_pages, $page + 2);

            for ($i = $start_loop; $i <= $end_loop; $i++): ?>
                <a href="?page=<?= $i ?>&search=<?= urlencode($searchTerm) ?>" class="pg-link <?= ($i == $page) ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($searchTerm) ?>" class="pg-link">NEXT <i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
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