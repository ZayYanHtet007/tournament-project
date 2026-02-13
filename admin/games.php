<?php
session_start();
require_once __DIR__ . '/../database/dbConfig.php';
require_once __DIR__ . '/sidebar.php';

// Access control: only main admin can manage games
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'main_admin') {
    header("Location: adminDashboard.php");
    exit;
}

$message = "";
$messageType = "";
$gameImageDirFs = __DIR__ . '/../images/games/';
$gameImageDirWeb = '../images/games/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_status'])) {
        $targetId = (int)($_POST['target_id'] ?? 0);
        $newStatusRaw = $_POST['new_status'] ?? '';
        $allowedStatuses = ['available', 'nonavailable'];

        // Backward-compatible normalization for older UI payloads
        if ($newStatusRaw === 'non available') {
            $newStatusRaw = 'nonavailable';
        }

        if ($targetId > 0 && in_array($newStatusRaw, $allowedStatuses, true)) {
            $updateStmt = $conn->prepare("UPDATE games SET game_status = ? WHERE game_id = ?");
            $updateStmt->bind_param("si", $newStatusRaw, $targetId);

            if ($updateStmt->execute()) {
                $message = "Game status updated to " . strtoupper($newStatusRaw) . ".";
                $messageType = "success";
            } else {
                $message = "Failed to update game status.";
                $messageType = "error";
            }
        } else {
            $message = "Invalid game status update request.";
            $messageType = "error";
        }
    } elseif (isset($_POST['create_game'])) {
        $name = trim($_POST['name'] ?? '');
        $genre = trim($_POST['genre'] ?? '');
        $gameStatus = trim($_POST['game_status'] ?? 'available');
        $imagePath = null;

        $allowedStatuses = ['available', 'nonavailable'];
        if (!in_array($gameStatus, $allowedStatuses, true)) {
            $gameStatus = 'available';
        }

        if ($name === '' || $genre === '') {
            $message = "Game name and genre are required.";
            $messageType = "error";
        } else {
            if (!empty($_FILES['image']['name']) && isset($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
                $uploadDir = $gameImageDirFs;
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];

                if (in_array($ext, $allowedExt, true)) {
                    $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($_FILES['image']['name'], PATHINFO_FILENAME));
                    $newFileName = 'game_' . time() . '_' . $safeName . '.' . $ext;
                    $targetPath = $uploadDir . $newFileName;

                    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                        $imagePath = $newFileName;
                    }
                }
            }

            $insertStmt = $conn->prepare("INSERT INTO games (name, image, genre, game_status) VALUES (?, ?, ?, ?)");
            $insertStmt->bind_param("ssss", $name, $imagePath, $genre, $gameStatus);

            if ($insertStmt->execute()) {
                $message = "Game created successfully.";
                $messageType = "success";
            } else {
                $message = "Failed to create game. Please check genre/status values.";
                $messageType = "error";
            }
        }
    }
}

$sql = "SELECT game_id, name, image, genre, game_status, created_at
        FROM games
        ORDER BY game_id DESC";
$games = $conn->query($sql);

$genreOptions = [];
$genreColumn = $conn->query("SHOW COLUMNS FROM games LIKE 'genre'");
if ($genreColumn && $genreColumn->num_rows > 0) {
    $col = $genreColumn->fetch_assoc();
    if (preg_match("/^enum\\((.*)\\)$/", $col['Type'], $matches)) {
        $parts = str_getcsv($matches[1], ',', "'", "\\");
        foreach ($parts as $p) {
            $genreOptions[] = $p;
        }
    }
}
?>

<div class="main-content">
    <div class="main-content-container">
        <div class="tournament-header-section">
            <h2>Game Management</h2>
            <p>Manage game catalog and availability status.</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <div class="alert-content">
                    <span><?= htmlspecialchars($message) ?></span>
                    <button type="button" class="alert-close" onclick="this.parentElement.parentElement.style.display='none'">×</button>
                </div>
            </div>
        <?php endif; ?>

        <div class="action-bar-container">
            <button type="button" id="openCreateGameModal" class="btn-create-game">
                <i class="ph ph-plus-circle"></i> Create Game
            </button>
        </div>

        <div class="glass-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Game ID</th>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Genre</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $games->fetch_assoc()): ?>
                            <?php
                            $isAvailable = ($row['game_status'] === 'available');
                            $statusClass = $isAvailable ? 'approval-success' : 'approval-danger';
                            ?>
                            <tr>
                                <td><span>#<?= (int)$row['game_id'] ?></span></td>
                                <td>
                                    <?php if (!empty($row['image'])): ?>
                                        <img src="<?= htmlspecialchars($gameImageDirWeb . $row['image']) ?>"
                                             alt="<?= htmlspecialchars($row['name']) ?>"
                                             style="width: 56px; height: 56px; object-fit: cover; border-radius: 10px; border: 1px solid var(--border-light);">
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size:0.8rem;">No Image</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= htmlspecialchars($row['genre']) ?></td>
                                <td>
                                    <span class="custom-badge <?= $statusClass ?>">
                                        <?= strtoupper(htmlspecialchars($row['game_status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <?php if ($isAvailable): ?>
                                            <button class="btn-icon status-deactivate"
                                                    onclick="openStatusModal(<?= (int)$row['game_id'] ?>, 'nonavailable')"
                                                    title="Set Non Available">
                                                <i class="ph ph-x-circle"></i> Non Available
                                            </button>
                                            <button class="btn-icon status-activate" disabled title="Already Available">
                                                <i class="ph ph-check-circle"></i> Available
                                            </button>
                                        <?php else: ?>
                                            <button class="btn-icon status-activate"
                                                    onclick="openStatusModal(<?= (int)$row['game_id'] ?>, 'available')"
                                                    title="Set Available">
                                                <i class="ph ph-check-circle"></i> Available
                                            </button>
                                            <button class="btn-icon status-deactivate" disabled title="Already Non Available">
                                                <i class="ph ph-x-circle"></i> Non Available
                                            </button>
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

<div id="statusModal" class="modal-overlay" style="display:none;">
    <div class="glass-card modal-content">
        <h3>Update Game Status</h3>
        <p id="statusModalDesc"></p>
        <form method="POST">
            <input type="hidden" name="target_id" id="statusModalTargetId">
            <input type="hidden" name="new_status" id="statusModalNewStatus">
            <input type="hidden" name="toggle_status" value="1">
            <div class="modal-btns">
                <button type="button" class="btn btn-secondary" onclick="closeStatusModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Status</button>
            </div>
        </form>
    </div>
</div>

<div id="createGameModal" class="modal-overlay" style="display:none;">
    <div class="glass-card modal-content">
        <h3>Create New Game</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="create_game" value="1">
            <div class="mb-3">
                <label class="form-label">Game Name</label>
                <input type="text" class="form-control" name="name" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Genre</label>
                <?php if (!empty($genreOptions)): ?>
                    <select class="form-control" name="genre" required>
                        <option value="">Select genre</option>
                        <?php foreach ($genreOptions as $opt): ?>
                            <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input type="text" class="form-control" name="genre" placeholder="Enter genre" required>
                <?php endif; ?>
            </div>
            <div class="mb-3">
                <label class="form-label">Image</label>
                <input type="file" class="form-control" name="image" accept=".jpg,.jpeg,.png,.webp">
            </div>
            <div class="mb-3">
                <label class="form-label">Status</label>
                <select class="form-control" name="game_status" required>
                    <option value="available">Available</option>
                    <option value="nonavailable">Non Available</option>
                </select>
            </div>
            <div class="modal-btns">
                <button type="button" class="btn btn-secondary" onclick="closeCreateGameModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Game</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openStatusModal(id, nextStatus) {
        document.getElementById('statusModalTargetId').value = id;
        document.getElementById('statusModalNewStatus').value = nextStatus;
        const actionText = nextStatus === 'available' ? 'AVAILABLE' : 'NON AVAILABLE';
        document.getElementById('statusModalDesc').innerText =
            `You are about to set this game status to ${actionText}.`;
        document.getElementById('statusModal').style.display = 'flex';
    }

    function closeStatusModal() {
        document.getElementById('statusModal').style.display = 'none';
    }

    function closeCreateGameModal() {
        document.getElementById('createGameModal').style.display = 'none';
    }

    document.addEventListener('DOMContentLoaded', function() {
        const openBtn = document.getElementById('openCreateGameModal');
        if (openBtn) {
            openBtn.addEventListener('click', function() {
                document.getElementById('createGameModal').style.display = 'flex';
            });
        }
    });
</script>
