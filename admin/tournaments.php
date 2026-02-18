<?php
require_once __DIR__ . '/../database/dbConfig.php';
require_once __DIR__ . '/sidebar.php';

$message = "";
$messageType = "";

// --- 1. SET PAGINATION VARIABLES ---
$limit = 10; // Tournaments per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Handle Fee Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_fee_btn'])) {
    $new_fee = floatval($_POST['new_fee']);

    // 1. Get the current logged-in admin's ID from the session
    $admin_id = $_SESSION['admin_id'] ?? null;

    if ($admin_id) {
        // 2. Updated SQL: You must include admin_id because of your NOT NULL constraint
        // We use a specific ID if your table has a primary key, or update the existing row
        $update_sql = "UPDATE creation_fee 
                       SET tournament_create_value = '$new_fee', 
                           admin_id = '$admin_id'";

        if (mysqli_query($conn, $update_sql)) {
            $message = "Tournament fee updated successfully to $" . number_format($new_fee, 2);
            $messageType = "success";
        } else {
            $message = "Error updating fee: " . mysqli_error($conn);
            $messageType = "error";
        }
    } else {
        $message = "Error: You must be logged in as an admin to change the fee.";
        $messageType = "error";
    }
}

        // Get Current Fee
        // --- 3. GET CURRENT FEE AND ADMIN INFO ---
        $current_fee = 0;
        $last_updated_by = "System";

        // JOIN with admins table to see who made the last change
        $fee_sql = "SELECT cf.tournament_create_value, a.username 
            FROM creation_fee cf 
            LEFT JOIN admins a ON cf.admin_id = a.admin_id 
            LIMIT 1";
        $fee_result = mysqli_query($conn, $fee_sql);

        if ($fee_result && mysqli_num_rows($fee_result) > 0) {
            $fee_row = mysqli_fetch_assoc($fee_result);
            $current_fee = $fee_row['tournament_create_value'];
            $last_updated_by = $fee_row['username'] ?? "Unknown Admin";
        }

        $searchTerm = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// --- DATE RANGE FILTER ---
$startDateFromRaw = $_GET['start_from'] ?? '';
$startDateToRaw = $_GET['start_to'] ?? '';

$startDateFrom = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDateFromRaw)) ? $startDateFromRaw : '';
$startDateTo = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDateToRaw)) ? $startDateToRaw : '';

$dateCondition = '';
if ($startDateFrom && $startDateTo) {
    $dateCondition = " AND DATE(t.start_date) BETWEEN '$startDateFrom' AND '$startDateTo'";
} elseif ($startDateFrom) {
    $dateCondition = " AND DATE(t.start_date) >= '$startDateFrom'";
} elseif ($startDateTo) {
    $dateCondition = " AND DATE(t.start_date) <= '$startDateTo'";
}

// --- STATUS FILTER ---
$statusRaw = $_GET['status'] ?? '';
$allowedStatuses = ['upcoming', 'ongoing', 'completed'];
$status = in_array($statusRaw, $allowedStatuses, true) ? $statusRaw : '';
$statusCondition = $status ? " AND t.status = '$status'" : '';

// --- 2. GET TOTAL COUNT FOR PAGINATION ---
$count_sql = "SELECT COUNT(*) AS total FROM tournaments t 
              INNER JOIN games g ON t.game_id = g.game_id 
              WHERE (t.title LIKE '%$searchTerm%' OR g.name LIKE '%$searchTerm%' OR t.tournament_id LIKE '%$searchTerm%')
              $dateCondition
              $statusCondition";
$count_result = mysqli_query($conn, $count_sql);
$total_rows = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_rows / $limit);

// --- 3. FETCH PAGINATED DATA ---
// Added LIMIT and OFFSET to the query
$sql = "SELECT t.*, g.name AS game_name 
        FROM tournaments t
        INNER JOIN games g ON t.game_id = g.game_id 
        WHERE (t.title LIKE '%$searchTerm%' 
           OR g.name LIKE '%$searchTerm%' 
           OR t.tournament_id LIKE '%$searchTerm%')
        $dateCondition
        $statusCondition
        ORDER BY CASE WHEN t.admin_status = 'pending' THEN 1 ELSE 2 END ASC, t.tournament_id ASC
        LIMIT $limit OFFSET $offset";

$result = mysqli_query($conn, $sql);
$tournaments = [];
while ($row = mysqli_fetch_assoc($result)) {
    $tournaments[] = $row;
}



?>

<div class="main-content">
    <div class="main-content-container">
        <div class="tournament-header-section">
            <h2>Tournament Management</h2>
            <p>View and manage all active and upcoming tournaments.</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <div class="alert-content">
                    <span><?= htmlspecialchars($message) ?></span>
                    <button type="button" class="alert-close" onclick="this.parentElement.parentElement.style.display='none'">×</button>
                </div>
            </div>
        <?php endif; ?>



        <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

        <div class="action-bar-container">
            <div class="search-wrapper">
                <form method="GET" action="" class="search-form">
                    <button type="submit" class="search-btn"><i class="ph ph-magnifying-glass"></i></button>
                    <input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($searchTerm) ?>">
                    <input type="hidden" name="start_from" value="<?= htmlspecialchars($startDateFrom) ?>">
                    <input type="hidden" name="start_to" value="<?= htmlspecialchars($startDateTo) ?>">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">

                    <?php if (!empty($searchTerm)): ?>
                        <a href="tournaments.php" class="reset-btn"><i class="ph ph-x-circle"></i></a>
                    <?php endif; ?>
                </form>
            </div>

            <form method="GET" action="" id="filterForm" class="date-filter-form">
                <input type="hidden" name="search" value="<?= htmlspecialchars($searchTerm) ?>">
                <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">

                <input type="hidden" name="start_from" id="start_from" value="<?= htmlspecialchars($startDateFrom) ?>">
                <input type="hidden" name="start_to" id="start_to" value="<?= htmlspecialchars($startDateTo) ?>">

                <div class="date-filter-wrapper">
                    <input type="text"
                        id="dateRange"
                        class="date-range-input-clean"
                        readonly
                        placeholder="Select Date Range"
                        value="<?= ($startDateFrom && $startDateTo) ? htmlspecialchars($startDateFrom . ' - ' . $startDateTo) : '' ?>">
                    <i class="ph ph-calendar calendar-icon"></i>
                </div>
            </form>

            <form method="GET" action="" class="date-filter-form">
                <input type="hidden" name="search" value="<?= htmlspecialchars($searchTerm) ?>">
                <input type="hidden" name="start_from" value="<?= htmlspecialchars($startDateFrom) ?>">
                <input type="hidden" name="start_to" value="<?= htmlspecialchars($startDateTo) ?>">

                <div class="status-filter">
                    
                    <select name="status" onchange="this.form.submit()">
                        <option value="">All</option>
                        <option value="upcoming" <?= $status === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                        <option value="ongoing" <?= $status === 'ongoing' ? 'selected' : '' ?>>Ongoing</option>
                        <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>
            </form>

            <button id="openFeeModal" class="btn-create-fee"><i class="ph ph-currency-dollar"></i> Set Create Fee</button>
        </div>

        <div class="glass-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tournament Title</th>
                            <th>Game</th>
                            <th>Participants</th>
                            <th>Entry Fee</th>
                            <th>Start Date</th>
                            <th>Status</th>
                            <th>Approval</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($tournaments)): ?>
                            <?php foreach ($tournaments as $row):
                                $statusClass = "status-" . $row['status'];
                                $approvalClass = "approval-" . ($row['admin_status'] == 'approved' ? 'success' : ($row['admin_status'] == 'rejected' ? 'danger' : 'pending'));
                            ?>
                                <tr onclick="window.location='tournamentsDetail.php?id=<?= $row['tournament_id'] ?>'" style="cursor: pointer;">
                                    <td><span>#<?= $row['tournament_id'] ?></span></td>
                                    <td><?= htmlspecialchars($row['title']) ?></td>
                                    <td><?= htmlspecialchars($row['game_name']) ?></td>
                                    <td><?= number_format($row['max_participants']) ?> Players</td>
                                    <td class="fee-text">$<?= number_format($row['fee'], 2) ?></td>
                                    <td><?= date('d M Y', strtotime($row['start_date'])) ?></td>
                                    <td><span class="custom-badge <?= $statusClass ?>"><?= strtoupper($row['status']) ?></span></td>
                                    <td><span class="custom-badge <?= $approvalClass ?>"><?= strtoupper($row['admin_status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 18px 12px;">
                                    <?= !empty($searchTerm) ? 'Tournament not found' : 'No tournaments available' ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="feeModal" class="custom-modal-overlay">
            <div class="custom-modal-box">
                <span class="close-modal">&times;</span>
                <div class="modal-header">
                    <p>Set the cost required to create a tournament.</p>
                </div>

                <div class="current-fee-display">
                    <span class="label">Original Fee</span>
                    <span class="amount">$<?php echo number_format($current_fee, 2); ?></span>
                </div>

                <form method="POST" action="tournaments.php">
                    <div class="input-group">
                        <label>New Fee Amount ($)</label>
                        <input type="number" name="new_fee" step="0.01" min="0" placeholder="Enter new fee" required>
                    </div>
                    <button type="submit" name="update_fee_btn" class="btn-save-fee">Update</button>
                </form>
            </div>
        </div>

        <?php if ($total_pages > 1): ?>
            <nav class="pb-pagination" aria-label="Tournaments pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($searchTerm) ?>&start_from=<?= urlencode($startDateFrom) ?>&start_to=<?= urlencode($startDateTo) ?>&status=<?= urlencode($status) ?>" class="pb-page-btn">Prev</a>
                <?php endif; ?>

                <?php
                $start_loop = max(1, $page - 2);
                $end_loop = min($total_pages, $page + 2);
                for ($i = $start_loop; $i <= $end_loop; $i++):
                ?>
                    <a href="?page=<?= $i ?>&search=<?= urlencode($searchTerm) ?>&start_from=<?= urlencode($startDateFrom) ?>&start_to=<?= urlencode($startDateTo) ?>&status=<?= urlencode($status) ?>" class="pb-page-btn <?= ($i == $page) ? 'is-active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($searchTerm) ?>&start_from=<?= urlencode($startDateFrom) ?>&start_to=<?= urlencode($startDateTo) ?>&status=<?= urlencode($status) ?>" class="pb-page-btn">Next</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById("feeModal");
        const openFeeModalBtn = document.getElementById("openFeeModal");
        const closeModalBtn = document.querySelector(".close-modal");

        if (openFeeModalBtn && modal) {
            openFeeModalBtn.addEventListener("click", function() {
                modal.style.display = "flex";
            });
        }

        if (closeModalBtn && modal) {
            closeModalBtn.addEventListener("click", function() {
                modal.style.display = "none";
            });
        }

        window.addEventListener("click", function(event) {
            if (modal && event.target === modal) {
                modal.style.display = "none";
            }
        });

        const filterForm = document.getElementById("filterForm");
        const dateRangeInput = document.getElementById("dateRange");
        const startFromInput = document.getElementById("start_from");
        const startToInput = document.getElementById("start_to");

        if (!filterForm || !dateRangeInput || !startFromInput || !startToInput) {
            return;
        }

        
        const updateHiddenFields = (selectedDates, instance) => {
            if (selectedDates.length === 2) {
                const fromDate = instance.formatDate(selectedDates[0], "Y-m-d");
                const toDate = instance.formatDate(selectedDates[1], "Y-m-d");
                startFromInput.value = fromDate;
                startToInput.value = toDate;
            }
        };

        const flatpickrInstance = flatpickr(dateRangeInput, {
            mode: "range",
            dateFormat: "Y-m-d",
            closeOnSelect: false, 
            defaultDate: [
                startFromInput.value || null,
                startToInput.value || null
            ].filter(Boolean),

            onReady: function(selectedDates, dateStr, instance) {
                
                const footer = document.createElement("div");
                footer.className = "flatpickr-footer";

                // Restart/Reset Button
                const restartBtn = document.createElement("button");
                restartBtn.type = "button";
                restartBtn.className = "fp-btn fp-btn-restart";
                restartBtn.textContent = "Restart";

                // Apply Button
                const applyBtn = document.createElement("button");
                applyBtn.type = "button";
                applyBtn.className = "fp-btn fp-btn-apply";
                applyBtn.textContent = "Apply";

                
                restartBtn.addEventListener("click", function() {
                    instance.clear();
                    startFromInput.value = "";
                    startToInput.value = "";
                    dateRangeInput.value = "";
                    instance.close();
                    filterForm.submit();
                });

                
                applyBtn.addEventListener("click", function() {
                    if (instance.selectedDates.length === 2) {
                        updateHiddenFields(instance.selectedDates, instance);
                        instance.close(); 
                        filterForm.submit(); 
                    } else if (instance.selectedDates.length === 0) {
                        
                        instance.close();
                    } else {
                       
                        alert("Please select an end date.");
                    }
                });

                footer.appendChild(restartBtn);
                footer.appendChild(applyBtn);
                instance.calendarContainer.appendChild(footer);
            }
        });

        
        document.querySelector(".date-filter-wrapper").addEventListener("click", function() {
            flatpickrInstance.open();
        });
    });
</script>
