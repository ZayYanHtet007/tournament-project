<?php
session_start();
require_once __DIR__ . '/../database/dbConfig.php';
require_once __DIR__ . '/sidebar.php';

// Access Control
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'main_admin') {
    header("Location: adminDashboard.php");
    exit;
}

$message = "";
$messageType = "";

// Handle Status Toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentAdminId = $_SESSION['admin_id'];
    $confirmPwd = $_POST['confirm_identity_password'] ?? '';

    // Verify identity of the Main Admin before making changes
    $stmt = $conn->prepare("SELECT password FROM admins WHERE admin_id = ?");
    $stmt->bind_param("i", $currentAdminId);
    $stmt->execute();
    $adminData = $stmt->get_result()->fetch_assoc();

    if (password_verify($confirmPwd, $adminData['password'])) {
        if (isset($_POST['toggle_status'])) {
            $targetId = intval($_POST['target_id']);
            $newStatus = $_POST['new_status'];
            
            // Update statement to switch status
            $update = $conn->prepare("UPDATE admins SET status = ? WHERE admin_id = ?");
            $update->bind_param("si", $newStatus, $targetId);
            
            if ($update->execute()) {
                $message = "Admin status updated to " . ucfirst($newStatus) . ".";
                $messageType = "success";
            } else {
                $message = "Error updating status.";
                $messageType = "error";
            }
        }
    } else {
        $message = "Incorrect password. Verification failed.";
        $messageType = "error";
    }
}

// Fetch Admins
$search = $_GET['search'] ?? '';
$query = "SELECT * FROM admins WHERE username LIKE ? OR email LIKE ? ORDER BY admin_id DESC";
$stmt = $conn->prepare($query);
$searchTerm = "%$search%";
$stmt->bind_param("ss", $searchTerm, $searchTerm);
$stmt->execute();
$admins = $stmt->get_result();
?>

<div class="main-content">
    <div class="main-content-container">
        <div class="tournament-header-section">
            <h2>Manage Admins</h2>
            <p>Switch admin status between Active and Inactive.</p>
        </div>

        <div class="search-wrapper">
            <form method="GET" action="" class="search-form">
                <button type="submit" class="search-btn">
                    <i class="ph ph-magnifying-glass"></i>
                </button>
                <input type="text" name="search" placeholder="Search admins..." value="<?= htmlspecialchars($search) ?>">
                <?php if (!empty($searchTerm)): ?>
                    <a href="manageAdmin.php" class="reset-btn">
                        <i class="ph ph-x-circle"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <div class="alert-content">
                    <span><?= htmlspecialchars($message) ?></span>
                    <button type="button" class="alert-close" onclick="this.parentElement.parentElement.style.display='none'">
                        ×
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <div class="glass-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Admin ID</th>
                            <th>Admin Name</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $admins->fetch_assoc()): ?>
                            <?php 
                                // UI Status Badge Logic
                                $statusClass = ($row['status'] === 'active') ? 'approval-success' : 'approval-danger';
                            ?>
                            <tr>
                                <td><span>#<?= $row['admin_id'] ?></span></td>
                                <td><?= htmlspecialchars($row['username']) ?></td>
                                <td><?= htmlspecialchars($row['role']) ?></td>
                                <td>
                                    <span class="custom-badge <?= $statusClass ?>">
                                        <?= strtoupper(htmlspecialchars($row['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <?php if ($row['admin_id'] != $_SESSION['admin_id']): ?>
                                            <?php if ($row['status'] === 'active'): ?>
                                                <!-- When active: Inactive button is enabled, Active button is disabled -->
                                                <button class="btn-icon status-deactivate" 
                                                        onclick="openActionModal(<?= $row['admin_id'] ?>, 'inactive')" 
                                                        title="Set Inactive">
                                                    <i class="ph ph-user-minus"></i> Inactive
                                                </button>
                                                <button class="btn-icon status-activate" disabled title="Already Active">
                                                    <i class="ph ph-user-plus"></i> Active
                                                </button>
                                            <?php else: ?>
                                                <!-- When inactive: Active button is enabled, Inactive button is disabled -->
                                                <button class="btn-icon status-activate" 
                                                        onclick="openActionModal(<?= $row['admin_id'] ?>, 'active')" 
                                                        title="Set Active">
                                                    <i class="ph ph-user-plus"></i> Active
                                                </button>
                                                <button class="btn-icon status-deactivate" disabled title="Already Inactive">
                                                    <i class="ph ph-user-minus"></i> Inactive
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size: 0.8rem;">Current User</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="actionModal" class="modal-overlay" style="display:none;">
    <div class="glass-card modal-content">
        <h3>Confirm Identity</h3>
        <p id="modalDesc"></p>
        <form method="POST">
            <input type="hidden" name="target_id" id="modalTargetId">
            <input type="hidden" name="new_status" id="modalNewStatus">
            <input type="hidden" name="toggle_status" value="1">

            <input type="password" name="confirm_identity_password" id="confirmPwd" class="glass-input" placeholder="Enter Your Password" required style="width:100%; margin-bottom:15px; background: rgba(255,255,255,0.05); color: white; border: 1px solid rgba(255,255,255,0.2); padding: 10px; border-radius: 5px;">
            
            <div class="modal-btns">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Status</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openActionModal(id, nextStatus) {
        document.getElementById('modalTargetId').value = id;
        document.getElementById('modalNewStatus').value = nextStatus;
        
        const actionText = nextStatus === 'active' ? 'ACTIVATE' : 'DEACTIVATE';
        document.getElementById('modalDesc').innerText = `You are about to ${actionText} this admin account. Please enter your password to confirm:`;

        document.getElementById('actionModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('actionModal').style.display = 'none';
        document.getElementById('confirmPwd').value = '';
    }
    
    
    document.addEventListener('DOMContentLoaded', function() {
        
        clearTimeout(window.alertTimeout);
    });
</script>