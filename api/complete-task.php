<?php
/**
 * إنجاز مهمة من باكج اليوم بلا إعادة تحميل — يحفظ الإنجاز والنقاط ويعيد
 * بقية الباكج (قصة السطر + شخصية التراث + اللعبة) ليقودها الرفيق على الصفحة نفسها.
 * البقاء في الصفحة يحافظ على «تفاعل المستخدم» فيُسمح بتشغيل فيديو يوتيوب تلقائياً.
 * POST: task_id → {ok, points, story_line, pair_line, figure, game, all_done}
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['child_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok' => false]); exit; }
$stmt = $pdo->prepare("SELECT * FROM children WHERE id = ?");
$stmt->execute([$_SESSION['child_id']]);
$child = $stmt->fetch();
if (!$child) { echo json_encode(['ok' => false]); exit; }

$progress = ensure_daily_progress($pdo, (int)$child['id']);
$pool = daily_task_pool($pdo, $child, $progress);
$completed = array_map('intval', json_decode_safe($progress['completed_task_ids'], []));
$taskId = (int)($_POST['task_id'] ?? 0);

if (!in_array($taskId, $pool, true) || in_array($taskId, $completed, true)) {
    echo json_encode(['ok' => false, 'msg' => 'المهمة ليست في باكج اليوم أو أُنجزت من قبل']); exit;
}
$t = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
$t->execute([$taskId]);
$task = $t->fetch();
if (!$task) { echo json_encode(['ok' => false]); exit; }

$completed[] = $taskId;
$pdo->prepare("UPDATE daily_progress SET completed_task_ids = ? WHERE id = ?")->execute([json_encode($completed), $progress['id']]);
$pdo->prepare("UPDATE children SET points = points + ? WHERE id = ?")->execute([(int)$task['points'], $child['id']]);

$figure = figure_for_task($pdo, $task);
$companion = active_character($pdo, $child);

echo json_encode([
    'ok'         => true,
    'points'     => (int)$task['points'],
    'story_line' => (string)$task['story_line'],
    'pair_line'  => companion_pair_line($companion, (string)$task['category']),
    'figure'     => $figure ? [
        'name' => $figure['name'], 'title' => $figure['title'], 'description' => $figure['description'],
        'story_line' => $figure['story_line'], 'youtube_id' => $figure['youtube_id'] ?: null,
    ] : null,
    'game'       => ['type' => $task['game_type'] ?: 'catch', 'title' => $task['game_title'] ?: $task['title'], 'category' => (string)$task['category']],
    'done_count' => count($completed),
    'all_done'   => count($completed) >= count($pool),
], JSON_UNESCAPED_UNICODE);
