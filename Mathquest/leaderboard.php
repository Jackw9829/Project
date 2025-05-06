<?php
session_start();
require_once 'config/db_connect.php';
$page_title = 'Leaderboard';

// Auto-recalculate leaderboard scores
try {
    // Clear and repopulate leaderboard
    $pdo->exec("TRUNCATE TABLE leaderboard");
    $pdo->exec("INSERT INTO leaderboard (user_id, total_score, total_quizzes_completed)
              SELECT 
                user_id, 
                SUM(CASE WHEN completed_at IS NOT NULL THEN score ELSE 0 END) as total_score,
                COUNT(CASE WHEN completed_at IS NOT NULL THEN 1 END) as total_quizzes_completed
              FROM quiz_attempts 
              GROUP BY user_id");
} catch(PDOException $e) {
    error_log("Error recalculating scores: " . $e->getMessage());
}

include 'includes/header.php';
?>

<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Tiny5', sans-serif;
        min-height: 100vh;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        background: #ffffff;
        position: relative;
        padding-top: 60px;
    }

    main {
        flex: 1;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        position: relative;
        z-index: 1;
        padding: 3rem 1rem;
        min-height: calc(100vh - 120px);
    }

    .container {
        max-width: 1200px;
        width: 100%;
        margin: 0 auto;
        position: relative;
        z-index: 1;
        padding: 0 15px;
    }

    .content-container {
        background: rgba(255, 255, 255, 0.95);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        backdrop-filter: blur(5px);
        border: 1px solid rgba(0, 0, 0, 0.05);
        border-radius: 20px;
        padding: 2.5rem;
        margin-top: 2rem;
        margin-bottom: 2rem;
        overflow-x: auto;
    }

    .leaderboard-header {
        text-align: center;
        margin-bottom: 2rem;
    }

    .leaderboard-header h1 {
        font-size: 2.5rem;
        color: #2c3e50;
        font-weight: 600;
        margin-bottom: 1rem;
    }

    .leaderboard-container {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .leaderboard-content {
        min-width: 100%;
    }

    .leaderboard-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1rem;
        background: rgba(255, 255, 255, 0.8);
        border-radius: 12px;
        overflow: hidden;
    }

    .leaderboard-table th {
        background: rgba(255, 192, 203, 0.2);
        color: #2c3e50;
        padding: 1rem;
        text-align: left;
        font-weight: 600;
        font-size: 1.1rem;
    }

    .leaderboard-table td {
        padding: 1rem;
        color: #2c3e50;
        border-bottom: 1px solid rgba(255, 192, 203, 0.2);
        font-size: 1rem;
    }

    .leaderboard-table tbody tr {
        transition: all 0.3s ease;
    }

    .leaderboard-table tbody tr:hover {
        background: rgba(255, 192, 203, 0.1);
        transform: translateX(5px);
    }

    .rank {
        font-weight: 600;
        color: #2c3e50;
        text-align: center;
    }

    .top-3 {
        font-weight: 700;
    }

    /* Gold Medal - 1st Place */
    tr.rank-1 {
        background: linear-gradient(to right, rgba(255, 215, 0, 0.1), transparent);
    }
    .rank-1 {
        color: #FFD700 !important;
        text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
        font-size: 1.1em;
        font-weight: bold;
    }
    tr.rank-1:hover {
        background: linear-gradient(to right, rgba(255, 215, 0, 0.2), transparent);
    }

    /* Silver Medal - 2nd Place */
    tr.rank-2 {
        background: linear-gradient(to right, rgba(192, 192, 192, 0.1), transparent);
    }
    .rank-2 {
        color: #C0C0C0 !important;
        text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
        font-size: 1.05em;
        font-weight: bold;
    }
    tr.rank-2:hover {
        background: linear-gradient(to right, rgba(192, 192, 192, 0.2), transparent);
    }

    /* Bronze Medal - 3rd Place */
    tr.rank-3 {
        background: linear-gradient(to right, rgba(205, 127, 50, 0.1), transparent);
    }
    .rank-3 {
        color: #CD7F32 !important;
        text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
        font-size: 1.02em;
        font-weight: bold;
    }
    tr.rank-3:hover {
        background: linear-gradient(to right, rgba(205, 127, 50, 0.2), transparent);
    }

    /* Medal icons */
    .rank-1 .rank::after,
    .rank-2 .rank::after,
    .rank-3 .rank::after {
        margin-left: 5px;
        font-size: 1.2em;
    }
    .rank-1 .rank::after { content: "🥇"; }
    .rank-2 .rank::after { content: "🥈"; }
    .rank-3 .rank::after { content: "🥉"; }

    .score {
        font-weight: 600;
        color: #2c3e50;
    }

    @media screen and (max-width: 768px) {
        main {
            padding: 2rem 1rem;
        }

        .container {
            padding: 0 10px;
        }

        .content-container {
            padding: 1.5rem 1rem;
            margin-top: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .leaderboard-header h1 {
            font-size: 1.8rem;
            margin-bottom: 1rem;
        }

        .leaderboard-table th,
        .leaderboard-table td {
            padding: 0.75rem 0.5rem;
            font-size: 0.9rem;
        }

        .leaderboard-table th:last-child,
        .leaderboard-table td:last-child {
            padding-right: 1rem;
        }

        .leaderboard-table th:first-child,
        .leaderboard-table td:first-child {
            padding-left: 1rem;
        }

        /* Hide less important columns on mobile */
        .leaderboard-table th:nth-child(4),
        .leaderboard-table td:nth-child(4) {
            display: none;
        }
    }

    @media screen and (max-width: 480px) {
        main {
            padding: 1.5rem 0.5rem;
        }

        .container {
            padding: 0 5px;
        }

        .content-container {
            padding: 1rem 0.5rem;
            margin-top: 1rem;
            margin-bottom: 1rem;
        }

        .leaderboard-header h1 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .leaderboard-table th,
        .leaderboard-table td {
            padding: 0.5rem 0.25rem;
            font-size: 0.8rem;
        }

        /* Adjust column widths for better mobile display */
        .leaderboard-table th:nth-child(1),
        .leaderboard-table td:nth-child(1) {
            width: 15%;
            min-width: 40px;
        }

        .leaderboard-table th:nth-child(2),
        .leaderboard-table td:nth-child(2) {
            width: 40%;
            min-width: 100px;
        }

        .leaderboard-table th:nth-child(3),
        .leaderboard-table td:nth-child(3) {
            width: 20%;
            min-width: 60px;
        }

        .leaderboard-table th:nth-child(5),
        .leaderboard-table td:nth-child(5) {
            width: 25%;
            min-width: 70px;
        }
    }
</style>

<main>
    <div id="particles-js" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1;"></div>
    <div class="container">
        <div class="content-container">
            <div class="leaderboard-header">
                <h1>Leaderboard</h1>
            </div>

            <div class="leaderboard-container">
                <?php if (isset($error)): ?>
                    <div class="error-message"><?php echo $error; ?></div>
                <?php else: ?>
                    <div class="leaderboard-content">
                        <table class="leaderboard-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Student Name</th>
                                    <th>Total Points</th>
                                    <th>Quizzes Completed</th>
                                    <th>Total Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Get leaderboard data
                                $query = "SELECT 
                                            u.user_id,
                                            u.name, 
                                            COALESCE(l.total_score, 0) as total_score,
                                            COALESCE(l.total_quizzes_completed, 0) as total_quizzes_completed,
                                            COALESCE(l.total_score, 0) as total_score
                                        FROM users u
                                        LEFT JOIN leaderboard l ON u.user_id = l.user_id 
                                        WHERE u.user_type = 'student'
                                        GROUP BY u.user_id, u.name
                                        ORDER BY total_score DESC, u.name ASC";
                                $result = $pdo->query($query);
                                $rank = 1;
                                
                                while ($row = $result->fetch()): 
                                    $rankClass = '';
                                    if ($rank === 1) $rankClass = 'rank-1';
                                    else if ($rank === 2) $rankClass = 'rank-2';
                                    else if ($rank === 3) $rankClass = 'rank-3';
                                    ?>
                                    <tr class="<?php echo $rankClass; ?>">
                                        <td class="rank <?php echo $rankClass; ?>"><?php echo $rank++; ?></td>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td class="score"><?php echo number_format($row['total_score']); ?></td>
                                        <td><?php echo number_format($row['total_quizzes_completed']); ?></td>
                                        <td><?php echo number_format($row['total_score']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
<script>
    particlesJS.load('particles-js', 'particles.json', function() {
        console.log('particles.js loaded');
    });
</script>

<?php include 'includes/footer.php'; ?>