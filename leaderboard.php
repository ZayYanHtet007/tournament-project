<?php
include('partial/header.php');

$sql = "SELECT 
            t.title AS tournament_name, 
            tm.team_name, 
            tr.created_at 
        FROM tournament_results tr
        JOIN tournaments t ON tr.tournament_id = t.tournament_id
        JOIN teams tm ON tr.winner_team_id = tm.team_id
        ORDER BY tr.created_at DESC";

$result = $conn->query($sql);
?>

<style>
    body {
        background: #0b0e14;
        color: #fff;
        font-family: Segoe UI;
    }

    .page {
        padding: 80px 8%;
    }

    h1 {
        color: #ff4655;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        background: #11141d;
    }

    th,
    td {
        padding: 15px;
        border-bottom: 1px solid #222;
        text-align: left;
    }

    th {
        background: #ff4655;
    }
</style>

<section class="page">
    <h1>Tournament Rankings</h1>

    <table>
        <thead>
            <tr>
                <th>Tournament</th>
                <th>Winner Team</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['tournament_name']) ?></td>
                        <td><?= htmlspecialchars($row['team_name']) ?></td>
                        <td><?= date('Y-m-d', strtotime($row['created_at'])) ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3">No tournament results found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>

<?php
$conn->close();
include('partial/footer.php');
?>