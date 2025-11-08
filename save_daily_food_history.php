<?php
/*  save_daily_food_history.php
 *  -------------------------------------------------
 *  GET  ?date=YYYY-MM-DD   →  today’s log + totals
 *  GET  ?recent=1          →  last 7 days (all rows)
 *  DELETE  body: id=NN     →  delete one entry
 *  -------------------------------------------------
 *  Uses your existing `food_log` table
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success'=>false,'message'=>'Not logged in']);
    exit;
}
$user_id = (int)$_SESSION['user_id'];

$method = $_SERVER['REQUEST_METHOD'];
header('Content-Type: application/json');

$conn = new mysqli('localhost','root','','rawfit');
if ($conn->connect_error) {
    echo json_encode(['success'=>false,'message'=>'DB error']);
    exit;
}
$conn->set_charset('utf8mb4');

/* ------------------------------------------------- */
/*  1. DELETE ONE ROW                                 */
/* ------------------------------------------------- */
if ($method === 'DELETE') {
    parse_str(file_get_contents('php://input'), $post);
    $id = (int)($post['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success'=>false,'message'=>'Invalid ID']);
        exit;
    }
    $stmt = $conn->prepare("DELETE FROM food_log WHERE id=? AND user_id=?");
    $stmt->bind_param('ii',$id,$user_id);
    $stmt->execute();
    echo json_encode(['success'=>true]);
    $stmt->close(); $conn->close(); exit;
}

/* ------------------------------------------------- */
/*  2. GET RECENT (last 7 days)                       */
/* ------------------------------------------------- */
if (isset($_GET['recent'])) {
    $sql = "SELECT id, food_name,
                   1 AS serving_size, 'serving' AS serving_unit,
                   calories, protein, carbs, fats, log_date
            FROM food_log
            WHERE user_id = ? 
              AND log_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            ORDER BY log_date DESC, id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i',$user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    echo json_encode($rows);
    $stmt->close(); $conn->close(); exit;
}

/* ------------------------------------------------- */
/*  3. GET LOG FOR A SPECIFIC DATE                    */
/* ------------------------------------------------- */
$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

$sqlLog = "SELECT id, food_name,
                  1 AS serving_size, 'serving' AS serving_unit,
                  calories, protein, carbs, fats
           FROM food_log
           WHERE user_id = ? AND log_date = ?
           ORDER BY id ASC";

$stmtLog = $conn->prepare($sqlLog);
$stmtLog->bind_param('is',$user_id,$date);
$stmtLog->execute();
$resLog = $stmtLog->get_result();
$logs = [];
while ($r = $resLog->fetch_assoc()) $logs[] = $r;
$stmtLog->close();

/*  totals for the selected day */
$sqlTot = "SELECT 
              COALESCE(SUM(calories),0) AS total_calories,
              COALESCE(SUM(protein),0)  AS total_protein,
              COALESCE(SUM(carbs),0)    AS total_carbs,
              COALESCE(SUM(fats),0)     AS total_fats
           FROM food_log
           WHERE user_id = ? AND log_date = ?";
$stmtTot = $conn->prepare($sqlTot);
$stmtTot->bind_param('is',$user_id,$date);
$stmtTot->execute();
$tot = $stmtTot->get_result()->fetch_assoc();
$stmtTot->close();
$conn->close();

echo json_encode([
    'logs'   => $logs,
    'totals' => [
        'total_calories' => (int)$tot['total_calories'],
        'total_protein'  => (int)$tot['total_protein'],
        'total_carbs'    => (int)$tot['total_carbs'],
        'total_fats'     => (int)$tot['total_fats']
    ]
]);
?>