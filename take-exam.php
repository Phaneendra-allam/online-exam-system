<?php
/**
 * Renders an exam with timer and MCQ options.
 * Only students assigned to the exam's class can access it.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$me = require_login();

if ($me['role'] !== 'student') {
    redirect('admin/dashboard.php');
}

// Check student class
$classLevel = (int) ($me['class_level'] ?? 0);

if (!in_array($classLevel, [6, 7, 8, 9, 10], true)) {
    flash('error', 'Your class is not assigned. Contact the administrator.');
    redirect('exams.php');
}

// Get exam ID
$quizId = (int) ($_GET['quiz'] ?? 0);

if ($quizId <= 0) {
    flash('error', 'Invalid exam.');
    redirect('exams.php');
}

$pdo = db();

// Fetch exam belonging to the student's class
$stmt = $pdo->prepare("
    SELECT *
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
    SELECT id, text, marks
    FROM questions
    WHERE quiz_id = ?
    ORDER BY position, id
");

$qStmt->execute([$quizId]);
$questions = $qStmt->fetchAll();

if (!$questions) {
    flash('error', 'This exam has no questions yet.');
    redirect('exams.php');
}

// Fetch options
$ids = array_column($questions, 'id');
$placeholders = implode(',', array_fill(0, count($ids), '?'));

$oStmt = $pdo->prepare("
    SELECT id, question_id, text
    FROM options
    WHERE question_id IN ($placeholders)
    ORDER BY question_id, position, id
");

$oStmt->execute($ids);

$byQ = [];

foreach ($oStmt->fetchAll() as $o) {
    $byQ[(int) $o['question_id']][] = $o;
}

// Record exam start time
start_session();

$_SESSION['exam_started_at_' . $quizId] = time();

$pageTitle = 'Exam · ' . $quiz['title'];

include __DIR__ . '/includes/header.php';
?>

<div class="container py-4">

    <!-- Exam heading -->
    <div class="d-flex flex-wrap justify-content-between align-items-end mb-3 gap-2">

        <div>

            <span class="exam-tag">
                Class <?= (int)$quiz['class_level'] ?>
            </span>

            <span class="exam-tag">
                Paper Set <?= (int)$quiz['paper_set'] ?>
            </span>

            <h3 class="mt-2 mb-1">
                <?= e($quiz['title']) ?>
            </h3>

            <?php if ($quiz['intro']): ?>
                <p class="text-muted mb-0">
                    <?= e($quiz['intro']) ?>
                </p>
            <?php endif; ?>

        </div>

        <!-- Timer -->
        <div
            id="examTimer"
            class="exam-timer text-center"
            data-seconds="<?= (int)$quiz['duration_minutes'] * 60 ?>"
        >

            <small class="text-uppercase fw-bold d-block">
                Time left
            </small>

            <span class="fs-3 fw-bold" data-display>
                --:--
            </span>

        </div>

    </div>

    <!-- Exam form -->
    <form
        id="examForm"
        method="post"
        action="<?= e(base_url('submit-exam.php')) ?>"
    >

        <?= csrf_field() ?>

        <input
            type="hidden"
            name="quiz_id"
            value="<?= (int)$quiz['id'] ?>"
        >

        <div class="row g-3">

            <!-- Questions -->
            <div class="col-lg-9">

                <?php foreach ($questions as $i => $q):

                    $opts = $byQ[(int)$q['id']] ?? [];

                ?>

                    <div
                        class="card question-card p-4 mb-3"
                        id="q-<?= (int)$q['id'] ?>"
                    >

                        <div class="d-flex justify-content-between align-items-start mb-2">

                            <span class="text-muted small">

                                Question <?= $i + 1 ?>
                                of <?= count($questions) ?>

                                ·

                                <?= (int)$q['marks'] ?>
                                mark<?= $q['marks'] > 1 ? 's' : '' ?>

                            </span>

                        </div>

                        <h5 class="mb-3">
                            <?= e($q['text']) ?>
                        </h5>

                        <?php foreach ($opts as $o): ?>

                            <label class="option-label">

                                <input
                                    type="radio"
                                    name="ans[<?= (int)$q['id'] ?>]"
                                    value="<?= (int)$o['id'] ?>"
                                >

                                <span>
                                    <?= e($o['text']) ?>
                                </span>

                            </label>

                        <?php endforeach; ?>

                    </div>

                <?php endforeach; ?>

                <!-- Submit and cancel -->
                <div class="d-flex justify-content-between align-items-center mt-3">

                    <a
                        href="<?= e(base_url('exams.php')) ?>"
                        class="btn btn-link text-muted"
                        data-confirm="Leave the exam? Your answers will not be saved."
                    >
                        &larr; Cancel
                    </a>

                    <button
                        type="submit"
                        class="btn btn-primary btn-lg"
                    >

                        <i class="bi bi-check-circle"></i>
                        Submit Exam

                    </button>

                </div>

            </div>

            <!-- Question navigation -->
            <div class="col-lg-3 d-none d-lg-block">

                <div
                    class="card p-3 sticky-top"
                    style="top:80px;"
                >

                    <h6 class="mb-3">

                        <i class="bi bi-grid-3x3-gap"></i>
                        Question Palette

                    </h6>

                    <div class="d-flex flex-wrap gap-1">

                        <?php foreach ($questions as $i => $q): ?>

                            <button
                                type="button"
                                class="btn btn-outline-secondary btn-sm"
                                style="width:38px"
                                data-jump-question="<?= (int)$q['id'] ?>"
                            >
                                <?= $i + 1 ?>
                            </button>

                        <?php endforeach; ?>

                    </div>

                </div>

            </div>

        </div>

    </form>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
