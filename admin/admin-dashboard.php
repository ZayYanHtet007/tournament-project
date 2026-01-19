<?php
session_start();
require_once "../database/dbConfig.php";

if (!isset($_SESSION['admin_id'])) {
  header("Location: admin-login.php");
  exit;
}

// Fetch latest 20 notifications
$stmt = $conn->prepare("SELECT * FROM admin_notifications WHERE admin_id=? ORDER BY created_at DESC LIMIT 20");
$stmt->bind_param("i", $_SESSION['admin_id']);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Count unread from DB
$unreadCount = array_sum(array_map(fn($n) => $n['is_read'] == 0 ? 1 : 0, $notifications));
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background: #f1f5f9;
      margin: 0;
    }

    header {
      background: #1e293b;
      color: #fff;
      padding: 15px;
      text-align: center;
      font-size: 1.5rem;
      position: relative;
    }

    #notif-icon {
      position: absolute;
      right: 20px;
      top: 15px;
      cursor: pointer;
    }

    #notif-count {
      position: absolute;
      top: -8px;
      right: -8px;
      background: red;
      color: white;
      font-size: 0.8rem;
      padding: 2px 6px;
      border-radius: 50%;
    }

    #notif-dropdown {
      display: none;
      position: absolute;
      right: 20px;
      top: 60px;
      width: 300px;
      background: #fff;
      border: 1px solid #ccc;
      border-radius: 5px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
      z-index: 1000;
      max-height: 400px;
      overflow-y: auto;
    }

    .notification {
      padding: 10px;
      border-bottom: 1px solid #eee;
      cursor: pointer;
    }

    .notification.unread {
      background: #facc15;
      color: #000;
    }

    .notification.read {
      background: #f1f5f9;
      color: #333;
    }

    .notification:last-child {
      border-bottom: none;
    }
  </style>
</head>

<body>

  <header>
    Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
    <span id="notif-icon">
      <i class="fa-solid fa-bell"></i>
      <span id="notif-count"><?php echo $unreadCount; ?></span>
    </span>
  </header>

  <div id="notif-dropdown">
    <?php if (empty($notifications)): ?>
      <div class="notification read">No notifications</div>
    <?php else: ?>
      <?php foreach ($notifications as $n): ?>
        <div class="notification <?php echo $n['is_read'] ? 'read' : 'unread'; ?>" data-id="<?php echo $n['notification_id']; ?>">
          <strong><?php echo htmlspecialchars($n['title']); ?></strong><br>
          <?php echo htmlspecialchars($n['message']); ?><br>
          <small><?php echo $n['created_at']; ?></small>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <script>
    const ws = new WebSocket("ws://localhost:5000");

    // --- Real-time notifications ---
    ws.onmessage = (event) => {
      const data = JSON.parse(event.data);
      const dropdown = document.getElementById("notif-dropdown");

      // Create new notification div
      const div = document.createElement("div");
      div.className = "notification unread";
      div.dataset.id = data.id;
      div.innerHTML = `<strong>${data.title}</strong><br>${data.message}<br><small>${data.created_at}</small>`;
      dropdown.prepend(div);

      // Update unread count dynamically
      const countElem = document.getElementById("notif-count");
      const currentCount = parseInt(countElem.innerText) || 0;
      countElem.innerText = currentCount + 1;
    };

    // --- Toggle dropdown ---
    const notifIcon = document.getElementById("notif-icon");
    const notifDropdown = document.getElementById("notif-dropdown");
    notifIcon.addEventListener("click", () => {
      notifDropdown.style.display = notifDropdown.style.display === "block" ? "none" : "block";
    });
    document.addEventListener("click", (e) => {
      if (!notifIcon.contains(e.target) && !notifDropdown.contains(e.target)) {
        notifDropdown.style.display = "none";
      }
    });

    // --- Mark notification as read ---
    notifDropdown.addEventListener("click", (e) => {
      const div = e.target.closest(".notification.unread");
      if (!div) return;

      const id = div.dataset.id;
      fetch("mark-read.php?id=" + id)
        .then(res => {
          if (res.ok) {
            div.classList.remove("unread");
            div.classList.add("read");

            // Update unread count
            const countElem = document.getElementById("notif-count");
            const currentCount = parseInt(countElem.innerText) || 0;
            countElem.innerText = Math.max(0, currentCount - 1);
          }
        });
    });
  </script>

</body>

</html>