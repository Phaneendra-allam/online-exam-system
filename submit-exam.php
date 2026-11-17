
<?php
/**
 * Handles exam submissions, validates class access,
 * grades answers and saves the attempt.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$me = require_login();

if ($me['role'] !== 'student') {
    redirect('admin/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('exams.php');
}

verify_csrf();

// Validate student class
$classLevel = (int) ($me['class_level'] ?? 0);

if (!in_array($classLevel, [6, 7, 8, 9, 10], true)) {
    flash('error', 'Your class is not assigned. Contact the administrator.');
    redirect('exams.php');
}

// Get submitted data
$quizId = (int) ($_POST['quiz_id'] ?? 0);

$answers = is_array($_POST['ans'] ?? null)
    ? $_POST['ans']
    : [];

if ($quizId <= 0) {
    flash('error', 'Invalid submission.');
    redirect('exams.php');
}

$pdo = db();

// Verify exam belongs to student's class
$stmt = $pdo->prepare("
    SELECT id, duration_minutes
    FROM quizzes
    WHERE id = ?
    AND is_published = 1
    AND class_level = ?
    LIMIT 1
");

$stmt->execute([$quizId, $classLevel]);

$quiz = $stmt->fetch();

if (!$quiz) {
    flash('error', 'This exam is not available for your class.');
    redirect('exams.php');
}

// Fetch questions
$qStmt = $pdo->prepare("
    SELECT id, marks
    FROM questions
    WHERE quiz_id = ?
");

$qStmt->execute([$quizId]);

$questions = $qStmt->fetchAll();

if (!$questions) {
    flash('error', 'Exam has no questions.');
    redirect('exams.php');
}

// Fetch valid options and correct answers
$qIds = array_column($questions, 'id');

$placeholders = implode(
    ',',
    array_fill(0, count($qIds), '?')
);

$oStmt = $pdo->prepare("
    SELECT id, question_id, is_correct
    FROM options
    WHERE question_id IN ($placeholders)
");

$oStmt->execute($qIds);

$validOpts = [];

foreach ($oStmt->fetchAll() as $o) {

    $qid = (int) $o['question_id'];
    $oid = (int) $o['id'];

    $validOpts[$qid][$oid] = (bool) $o['is_correct'];
}

// Calculate timing
start_session();

$startedAt = $_SESSION['exam_started_at_' . $quizId] ?? null;

$now = time();

$timeTaken = $startedAt
    ? max(0, $now - (int) $startedAt)
    : 0;

$cap = (int) $quiz['duration_minutes'] * 60 + 5;

if ($timeTaken > $cap) {
    $timeTaken = $cap;
}

unset($_SESSION['exam_started_at_' . $quizId]);

// Grade answers
$score = 0;
$totalMarks = 0;
$correct = 0;
$wrong = 0;
$unanswered = 0;

$grading = [];

foreach ($questions as $q) {

    $qid = (int) $q['id'];
    $marks = (int) $q['marks'];

    $totalMarks += $marks;

    $picked = isset($answers[$qid])
        ? (int) $answers[$qid]
        : 0;

    // Invalid or unanswered option
    if (
        $picked <= 0 ||
        !isset($validOpts[$qid][$picked])
    ) {
        $grading[$qid] = [null, false];
        $unanswered++;
        continue;
    }

    // Correct answer
    if ($validOpts[$qid][$picked]) {

        $grading[$qid] = [$picked, true];

        $score += $marks;
        $correct++;

    } else {

        $grading[$qid] = [$picked, false];

        $wrong++;
    }
}

// Save attempt and answers
$pdo->beginTransaction();

try {

    $ins = $pdo->prepare("
        INSERT INTO attempts
        (
            user_id,
            quiz_id,
            score,
            total_marks,
            correct_count,
            wrong_count,
            unanswered_count,
            time_taken_sec,
            started_at,
            submitted_at
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?), NOW())
    ");

    $ins->execute([
        $me['id'],
        $quizId,
        $score,
        $totalMarks,
        $correct,
        $wrong,
        $unanswered,
        $timeTaken,
        $startedAt ?: $now - $timeTaken
    ]);

    $attemptId = (int) $pdo->lastInsertId();

    // Save individual answers
    $insAns = $pdo->prepare("
        INSERT INTO attempt_answers
        (
            attempt_id,
            question_id,
            selected_option_id,
            is_correct
        )
        VALUES (?, ?, ?, ?)
    ");

    foreach ($grading as $qid => [$pickedOpt, $isCorrect]) {

        $insAns->execute([
            $attemptId,
            $qid,
            $pickedOpt,
            $isCorrect ? 1 : 0
        ]);
    }

    $pdo->commit();

} catch (Throwable $e) {

    $pdo->rollBack();

    flash(
        'error',
        'Could not save your submission. Please try again.'
    );

    error_log('submit-exam: ' . $e->getMessage());

    redirect('exams.php');
}

flash('success', 'Exam submitted!');

redirect('result.php?id=' . $attemptId);
