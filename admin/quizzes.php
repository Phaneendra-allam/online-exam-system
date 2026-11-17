
<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$admin = require_admin();
$pdo = db();

$action = $_GET['action'] ?? 'list';
$id = (int) ($_GET['id'] ?? 0);
$errors = [];

// ---------- CREATE / EDIT ----------
if ($action === 'new' || $action === 'edit') {

    $editing = null;

    if ($action === 'edit') {
        $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE id=? LIMIT 1");
        $stmt->execute([$id]);
        $editing = $stmt->fetch();

        if (!$editing) {
            flash('error', 'Quiz not found.');
            redirect('admin/quizzes.php');
        }
    }

    $vals = array_merge([
        'title' => '',
        'tag' => '',
        'intro' => '',
        'class_level' => '',
        'paper_set' => '',
        'duration_minutes' => 30,
        'passing_percent' => 50,
        'is_published' => 1
    ], $editing ?: []);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        verify_csrf();

        $vals['title'] = clean_string($_POST['title'] ?? '', 150);
        $vals['tag'] = clean_string($_POST['tag'] ?? '', 60);
        $vals['intro'] = clean_string($_POST['intro'] ?? '', 1000);

        $vals['class_level'] = (int) ($_POST['class_level'] ?? 0);
        $vals['paper_set'] = (int) ($_POST['paper_set'] ?? 0);

        $vals['duration_minutes'] = max(
            1,
            (int) ($_POST['duration_minutes'] ?? 30)
        );

        $vals['passing_percent'] = max(
            0,
            min(100, (int) ($_POST['passing_percent'] ?? 50))
        );

        $vals['is_published'] = isset($_POST['is_published']) ? 1 : 0;

        // Validation
        if (mb_strlen($vals['title']) < 3) {
            $errors[] = 'Title must be at least 3 characters.';
        }

        if (!in_array($vals['class_level'], [6, 7, 8, 9, 10], true)) {
            $errors[] = 'Please select a valid class.';
        }

        if (!in_array($vals['paper_set'], [1, 2, 3, 4], true)) {
            $errors[] = 'Please select a valid paper set.';
        }

        // Save quiz
        if (!$errors) {

            if ($editing) {

                $stmt = $pdo->prepare("
                    UPDATE quizzes
                    SET title=?,
                        tag=?,
                        intro=?,
                        class_level=?,
                        paper_set=?,
                        duration_minutes=?,
                        passing_percent=?,
                        is_published=?
                    WHERE id=?
                ");

                $stmt->execute([
                    $vals['title'],
                    $vals['tag'] ?: null,
                    $vals['intro'] ?: null,
                    $vals['class_level'],
                    $vals['paper_set'],
                    $vals['duration_minutes'],
                    $vals['passing_percent'],
                    $vals['is_published'],
                    $editing['id']
                ]);

                flash('success', 'Quiz updated.');
                redirect('admin/quizzes.php');

            } else {

                $stmt = $pdo->prepare("
                    INSERT INTO quizzes
                    (
                        title,
                        tag,
                        intro,
                        class_level,
                        paper_set,
                        duration_minutes,
                        passing_percent,
                        is_published,
                        created_by
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $vals['title'],
                    $vals['tag'] ?: null,
                    $vals['intro'] ?: null,
                    $vals['class_level'],
                    $vals['paper_set'],
                    $vals['duration_minutes'],
                    $vals['passing_percent'],
                    $vals['is_published'],
                    $admin['id']
                ]);

                $newId = (int) $pdo->lastInsertId();

                flash('success', 'Quiz created. Add questions now.');

                redirect('admin/questions.php?quiz=' . $newId);
            }
        }
    }

    $pageTitle = $editing ? 'Edit quiz' : 'New quiz';

    include __DIR__ . '/../includes/header.php';

    $adminActive = 'quizzes';
    include __DIR__ . '/_nav.php';
?>

<div class="container">

    <h3 class="mb-3">
        <?= $editing ? 'Edit quiz' : 'Create new exam paper' ?>
    </h3>

    <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger">
            <?= e($err) ?>
        </div>
    <?php endforeach; ?>

    <div class="card p-4">

        <form method="post">

            <?= csrf_field() ?>

            <div class="row g-3">

                <!-- Exam title -->
                <div class="col-md-8">
                    <label class="form-label">Exam Title</label>

                    <input
                        type="text"
                        class="form-control"
                        name="title"
                        required
                        value="<?= e($vals['title']) ?>"
                        placeholder="Example: Class 6 General Examination"
                    >
                </div>

                <!-- Subject / tag -->
                <div class="col-md-4">
                    <label class="form-label">Subject / Tag</label>

                    <input
                        type="text"
                        class="form-control"
                        name="tag"
                        value="<?= e($vals['tag']) ?>"
                        placeholder="Combined Subjects"
                    >
                </div>

                <!-- Class -->
                <div class="col-md-6">

                    <label class="form-label">Select Class</label>

                    <select
                        name="class_level"
                        class="form-select"
                        required
                    >

                        <option value="">Select Class</option>

                        <?php foreach ([6, 7, 8, 9, 10] as $class): ?>

                            <option
                                value="<?= $class ?>"
                                <?= (string)$vals['class_level'] === (string)$class ? 'selected' : '' ?>
                            >
                                Class <?= $class ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <!-- Paper set -->
                <div class="col-md-6">

                    <label class="form-label">Paper Set</label>

                    <select
                        name="paper_set"
                        class="form-select"
                        required
                    >

                        <option value="">Select Paper Set</option>

                        <?php foreach ([1, 2, 3, 4] as $set): ?>

                            <option
                                value="<?= $set ?>"
                                <?= (string)$vals['paper_set'] === (string)$set ? 'selected' : '' ?>
                            >
                                Set <?= $set ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <!-- Introduction -->
                <div class="col-12">

                    <label class="form-label">Exam Description</label>

                    <textarea
                        class="form-control"
                        name="intro"
                        rows="3"
                        data-autogrow
                    ><?= e($vals['intro']) ?></textarea>

                </div>

                <!-- Duration -->
                <div class="col-md-4">

                    <label class="form-label">
                        Duration (minutes)
                    </label>

                    <input
                        type="number"
                        class="form-control"
                        name="duration_minutes"
                        min="1"
                        required
                        value="<?= (int)$vals['duration_minutes'] ?>"
                    >

                </div>

                <!-- Passing percentage -->
                <div class="col-md-4">

                    <label class="form-label">Passing Percentage</label>

                    <input
                        type="number"
                        class="form-control"
                        name="passing_percent"
                        min="0"
                        max="100"
                        required
                        value="<?= (int)$vals['passing_percent'] ?>"
                    >

                </div>

                <!-- Published -->
                <div class="col-md-4 d-flex align-items-end">

                    <div class="form-check mb-2">

                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="is_published"
                            id="pubChk"
                            <?= !empty($vals['is_published']) ? 'checked' : '' ?>
                        >

                        <label
                            class="form-check-label"
                            for="pubChk"
                        >
                            Publish Exam
                        </label>

                    </div>

                </div>

            </div>

            <div class="d-flex gap-2 mt-4">

                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-save"></i>
                    Save Exam
                </button>

                <a
                    class="btn btn-link"
                    href="<?= e(base_url('admin/quizzes.php')) ?>"
                >
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>

<?php
include __DIR__ . '/../includes/footer.php';
return;
}

// ---------- DELETE ----------
if ($action === 'delete' && $id > 0) {

    verify_csrf();

    $pdo->prepare("DELETE FROM quizzes WHERE id=?")->execute([$id]);

    flash('success', 'Quiz deleted.');

    redirect('admin/quizzes.php');
}

// ---------- LIST ----------
$rows = $pdo->query("
    SELECT q.*,

        (SELECT COUNT(*)
         FROM questions
         WHERE quiz_id=q.id) AS qcount,

        (SELECT COUNT(*)
         FROM attempts
         WHERE quiz_id=q.id) AS acount

    FROM quizzes q
    ORDER BY q.created_at DESC
")->fetchAll();

$pageTitle = 'Admin · Quizzes';

include __DIR__ . '/../includes/header.php';

$adminActive = 'quizzes';
include __DIR__ . '/_nav.php';
?>

<div class="container">

    <div class="d-flex justify-content-between align-items-center mb-3">

        <h3 class="mb-0">Exam Papers</h3>

        <a
            class="btn btn-primary"
            href="<?= e(base_url('admin/quizzes.php?action=new')) ?>"
        >
            <i class="bi bi-plus-lg"></i>
            Create Exam
        </a>

    </div>

    <div class="card p-3">

        <?php if (!$rows): ?>

            <p class="text-muted text-center my-4">
                No exams created yet.
            </p>

        <?php else: ?>

            <div class="table-responsive">

                <table class="table align-middle">

                    <thead>

                        <tr>
                            <th>Exam Title</th>
                            <th>Class</th>
                            <th>Paper Set</th>
                            <th>Questions</th>
                            <th>Attempts</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($rows as $r): ?>

                            <tr>

                                <td>
                                    <strong><?= e($r['title']) ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        <?= e($r['intro'] ?: '—') ?>
                                    </small>
                                </td>

                                <td>
                                    <?= $r['class_level'] !== null
                                        ? 'Class ' . (int)$r['class_level']
                                        : 'Unassigned' ?>
                                </td>

                                <td>
                                    <?= $r['paper_set'] !== null
                                        ? 'Set ' . (int)$r['paper_set']
                                        : '—' ?>
                                </td>

                                <td class="text-center">
                                    <?= (int)$r['qcount'] ?>
                                </td>

                                <td class="text-center">
                                    <?= (int)$r['acount'] ?>
                                </td>

                                <td class="text-center">
                                    <?= (int)$r['duration_minutes'] ?> min
                                </td>

                                <td class="text-center">

                                    <?php if ($r['is_published']): ?>

                                        <span class="badge bg-success">
                                            Published
                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-secondary">
                                            Draft
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td class="text-end">

                                    <a
                                        class="btn btn-sm btn-outline-secondary"
                                        href="<?= e(base_url('admin/questions.php?quiz=' . (int)$r['id'])) ?>"
                                        title="Manage questions"
                                    >
                                        <i class="bi bi-list-check"></i>
                                    </a>

                                    <a
                                        class="btn btn-sm btn-outline-primary"
                                        href="<?= e(base_url('admin/quizzes.php?action=edit&id=' . (int)$r['id'])) ?>"
                                        title="Edit exam"
                                    >
                                        <i class="bi bi-pencil"></i>
                                    </a>

                                    <a
                                        class="btn btn-sm btn-outline-danger"
                                        href="<?= e(base_url('admin/quizzes.php?action=delete&id=' . (int)$r['id'] . '&csrf_token=' . urlencode(csrf_token()))) ?>"
                                        data-confirm="Delete this quiz and all its questions and attempts? This cannot be undone."
                                        title="Delete exam"
                                    >
                                        <i class="bi bi-trash"></i>
                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
