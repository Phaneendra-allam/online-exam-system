
<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$me = require_login();

if ($me['role'] !== 'student') {
    redirect('admin/dashboard.php');
}

$pdo = db();

$classLevel = (int) ($me['class_level'] ?? 0);

$search = clean_string($_GET['q'] ?? '', 100);
$tag = clean_string($_GET['tag'] ?? '', 60);

$quizzes = [];
$tags = [];

if (in_array($classLevel, [6, 7, 8, 9, 10], true)) {

    $sql = "
        SELECT q.*,
        (SELECT COUNT(*)
         FROM questions
         WHERE quiz_id = q.id) AS qcount
        FROM quizzes q
        WHERE q.is_published = 1
        AND q.class_level = ?
    ";

    $args = [$classLevel];

    if ($search !== '') {
        $sql .= " AND q.title LIKE ?";
        $args[] = "%$search%";
    }

    if ($tag !== '') {
        $sql .= " AND q.tag = ?";
        $args[] = $tag;
    }

    $sql .= " ORDER BY q.paper_set ASC, q.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    $quizzes = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT DISTINCT tag
        FROM quizzes
        WHERE is_published = 1
        AND class_level = ?
        AND tag IS NOT NULL
        AND tag <> ''
        ORDER BY tag
    ");

    $stmt->execute([$classLevel]);
    $tags = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$pageTitle = 'My Class Exams';
$activeNav = 'exams';

include __DIR__ . '/includes/header.php';
?>

<div class="container py-4">

    <div class="d-flex flex-wrap justify-content-between align-items-end mb-4 gap-2">

        <div>
            <h2 class="mb-1">My Class Exams</h2>

            <?php if ($classLevel): ?>
                <p class="text-muted mb-0">
                    Class <?= $classLevel ?> ·
                    <?= count($quizzes) ?> papers available
                </p>
            <?php else: ?>
                <p class="text-danger mb-0">
                    Your class is not assigned. Please contact the administrator.
                </p>
            <?php endif; ?>
        </div>

        <?php if ($classLevel): ?>
            <form class="d-flex flex-wrap gap-2" method="get">

                <input
                    class="form-control"
                    type="search"
                    name="q"
                    value="<?= e($search) ?>"
                    placeholder="Search exams..."
                >

                <select
                    class="form-select"
                    name="tag"
                    onchange="this.form.submit()"
                >

                    <option value="">All subjects</option>

                    <?php foreach ($tags as $t): ?>
                        <option
                            value="<?= e($t) ?>"
                            <?= $tag === $t ? 'selected' : '' ?>
                        >
                            <?= e($t) ?>
                        </option>
                    <?php endforeach; ?>

                </select>

                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-search"></i>
                </button>

            </form>
        <?php endif; ?>

    </div>

    <?php if (!$classLevel): ?>

        <div class="card p-5 text-center">
            <i class="bi bi-person-exclamation"
               style="font-size:2.5rem"></i>

            <h5 class="mt-3">Class not assigned</h5>

            <p class="text-muted mb-0">
                Please contact your school administrator.
            </p>
        </div>

    <?php elseif (!$quizzes): ?>

        <div class="card p-5 text-center text-muted">

            <i class="bi bi-inbox" style="font-size:2.5rem"></i>

            <h5 class="mt-3">No exams available</h5>

            <p class="mb-0">
                No published papers are currently available
                for Class <?= $classLevel ?>.
            </p>

        </div>

    <?php else: ?>

        <div class="row g-3">

            <?php foreach ($quizzes as $q): ?>

                <div class="col-md-6 col-lg-4">

                    <div class="card exam-card h-100 p-4">

                        <div class="d-flex justify-content-between align-items-start mb-2">

                            <span class="exam-tag">
                                Class <?= (int)$q['class_level'] ?>
                            </span>

                            <small class="text-muted">
                                <i class="bi bi-stopwatch"></i>
                                <?= (int)$q['duration_minutes'] ?> min
                            </small>

                        </div>

                        <h5 class="mt-2">
                            <?= e($q['title']) ?>
                        </h5>

                        <p class="text-muted small">
                            <?= e($q['intro'] ?: 'Test your knowledge.') ?>
                        </p>

                        <div class="mb-2">
                            <strong>
                                Paper Set <?= (int)$q['paper_set'] ?>
                            </strong>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-auto">

                            <small class="text-muted">
                                <i class="bi bi-list-check"></i>
                                <?= (int)$q['qcount'] ?> questions
                            </small>

                            <a
                                href="<?= e(base_url('take-exam.php?quiz=' . (int)$q['id'])) ?>"
                                class="btn btn-primary btn-sm"
                            >
                                Start Exam
                                <i class="bi bi-arrow-right"></i>
                            </a>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
