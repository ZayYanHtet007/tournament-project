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

        <?php if ($total_pages > 1): ?>
            <nav class="pb-pagination" aria-label="Organizers pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($searchTerm) ?>" class="pb-page-btn">Prev</a>
                <?php endif; ?>

                <?php
                $start_loop = max(1, $page - 2);
                $end_loop = min($total_pages, $page + 2);
                for ($i = $start_loop; $i <= $end_loop; $i++):
                ?>
                    <a href="?page=<?= $i ?>&search=<?= urlencode($searchTerm) ?>" class="pb-page-btn <?= ($i == $page) ? 'is-active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($searchTerm) ?>" class="pb-page-btn">Next</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>
</div>

<style>
    .organizer-reject-backdrop.swal2-backdrop-show {
        background: radial-gradient(circle at 25% 20%, rgba(99, 102, 241, 0.2), transparent 45%), rgba(2, 6, 23, 0.55);
        backdrop-filter: blur(7px);
    }

    .organizer-reject-popup {
        background: linear-gradient(145deg, rgba(30, 41, 59, 0.82), rgba(15, 23, 42, 0.78)) !important;
        border: 1px solid rgba(148, 163, 184, 0.26) !important;
        border-radius: 18px !important;
        box-shadow: 0 24px 55px rgba(2, 6, 23, 0.6) !important;
        color: #e2e8f0 !important;
    }

    .organizer-reject-title {
        color: #f8fafc !important;
        font-weight: 800 !important;
        letter-spacing: 0.02em !important;
    }

    .organizer-reject-cancel,
    .organizer-action-confirm {
        border-radius: 10px !important;
        padding: 10px 18px !important;
        font-weight: 700 !important;
        border: 1px solid transparent !important;
        transition: all 0.2s ease !important;
    }

    .organizer-action-confirm {
        background: linear-gradient(135deg, #4f46e5, #4338ca) !important;
        border-color: rgba(129, 140, 248, 0.5) !important;
        color: #fff !important;
    }

    .organizer-reject-cancel {
        background: rgba(148, 163, 184, 0.18) !important;
        border-color: rgba(148, 163, 184, 0.3) !important;
        color: #f8fafc !important;
    }

    .organizer-reject-actions {
        gap: 16px !important;
        margin-top: 22px !important;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    async function handleApproval(userId, action) {
        const result = await Swal.fire({
            title: `Confirm ${action}?`,
            icon: 'question',
            background: 'transparent',
            color: '#f8fafc',
            showCancelButton: true,
            confirmButtonText: 'Yes, proceed',
            customClass: {
                container: 'organizer-reject-backdrop',
                popup: 'organizer-reject-popup',
                title: 'organizer-reject-title',
                actions: 'organizer-reject-actions',
                confirmButton: 'organizer-action-confirm',
                cancelButton: 'organizer-reject-cancel'
            },
            buttonsStyling: false
        });

        if (result.isConfirmed) {
            try {
                const response = await fetch('organizerApproval.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `user_id=${userId}&action=${action}&reason=`
                });

                const data = await response.json();

                if (data.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: data.message || 'Organizer approval updated.',
                        icon: 'success',
                        background: 'transparent',
                        color: '#f8fafc',
                        customClass: {
                            container: 'organizer-reject-backdrop',
                            popup: 'organizer-reject-popup',
                            title: 'organizer-reject-title',
                            confirmButton: 'organizer-action-confirm'
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
                            container: 'organizer-reject-backdrop',
                            popup: 'organizer-reject-popup',
                            title: 'organizer-reject-title',
                            confirmButton: 'organizer-action-confirm'
                        },
                        buttonsStyling: false
                    });
                }
            } catch (e) {
                console.error(e);
                Swal.fire({
                    title: 'Error!',
                    text: 'Something went wrong.',
                    icon: 'error',
                    background: 'transparent',
                    color: '#f8fafc',
                    customClass: {
                        container: 'organizer-reject-backdrop',
                        popup: 'organizer-reject-popup',
                        title: 'organizer-reject-title',
                        confirmButton: 'organizer-action-confirm'
                    },
                    buttonsStyling: false
                });
            }
        }
    }
</script>
