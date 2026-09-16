<?php
/**
 * أسئلة التحليل — تسجيل إجابة وإرجاع السؤال التالي (بلا إعادة تحميل).
 * لا تعليقات ولا نسب للطفل: القيمة تُسجَّل في quiz_history وتظهر للأدمن فقط.
 * POST: question_id, option (1..3)  →  {ok, done, next:{id,question,options[]}}
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['child_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok' => false]); exit; }
$stmt = $pdo->prepare("SELECT * FROM children WHERE id = ?");
$stmt->execute([$_SESSION['child_id']]);
$child = $stmt->fetch();
if (!$child || !needs_assessment($child)) { echo json_encode(['ok' => false, 'done' => true]); exit; }

$qSet = $_SESSION['assess_qids'] ?? [];
$answered = $_SESSION['assess_answered'] ?? [];
if (($_SESSION['assess_child_id'] ?? 0) != $child['id'] || !$qSet) { echo json_encode(['ok' => false, 'reload' => true]); exit; }

$qid = (int)($_POST['question_id'] ?? 0);
$opt = (int)($_POST['option'] ?? 0);
if (in_array($qid, $qSet) && !in_array($qid, $answered) && in_array($opt, [1, 2, 3])) {
    $q = $pdo->prepare("SELECT * FROM quiz_questions WHERE id = ?");
    $q->execute([$qid]);
    if ($question = $q->fetch()) {
        $pdo->prepare("INSERT INTO quiz_history (child_id, axis, value) VALUES (?,?,?)")
            ->execute([$child['id'], $question['axis'], (int)$question["option_{$opt}_value"]]);
        $_SESSION['assess_answered'][] = $qid;
        $answered[] = $qid;
    }
}

$remaining = array_values(array_diff($qSet, $answered));
if (!$remaining) {
    $pdo->prepare("UPDATE children SET last_assessment_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$child['id']]);
    unset($_SESSION['assess_qids'], $_SESSION['assess_answered'], $_SESSION['assess_child_id']);
    echo json_encode(['ok' => true, 'done' => true]);
    exit;
}
$st = $pdo->prepare("SELECT id, question, option_1, option_2, option_3 FROM quiz_questions WHERE id = ?");
$st->execute([$remaining[0]]);
$n = $st->fetch();
echo json_encode(['ok' => true, 'done' => false, 'next' => [
    'id' => (int)$n['id'], 'question' => $n['question'],
    'options' => [$n['option_1'], $n['option_2'], $n['option_3']],
]], JSON_UNESCAPED_UNICODE);
