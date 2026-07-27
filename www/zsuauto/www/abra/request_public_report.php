<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../delivered_car_reports_lib.php';

if (!rh_is_admin()) {
    header('Location: /ABRA');
    exit;
}

if (!dcr_tables_ready($dbcnx)) {
    http_response_code(503);
    die('Спочатку виконайте міграцію migrations/2026-07-27_delivered_car_reports.sql');
}

$requestId = (int)($_GET['request_id'] ?? $_POST['request_id'] ?? 0);
$request = rh_request($dbcnx, $requestId);
if (!$request) {
    http_response_code(404);
    die('Request not found');
}

$report = dcr_get_or_create_report($dbcnx, $request, rh_admin_id());
$reportId = (int)$report['id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && rh_post_too_large()) {
    $error = 'Загальний розмір завантаження перевищує налаштування сервера post_max_size.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rh_check_csrf();
    $todo = (string)($_POST['todo'] ?? 'save');

    if ($todo === 'delete_photo') {
        $photoId = (int)($_POST['photo_id'] ?? 0);
        $st = $dbcnx->prepare('SELECT * FROM zs_delivered_car_report_photos WHERE id=? AND report_id=? LIMIT 1');
        $st->bind_param('ii', $photoId, $reportId);
        $st->execute();
        $photo = $st->get_result()->fetch_assoc();
        if ($photo) {
            $photosCount = count(dcr_photos($dbcnx, $reportId));
            $dbcnx->begin_transaction();
            $st = $dbcnx->prepare('DELETE FROM zs_delivered_car_report_photos WHERE id=? AND report_id=?');
            $st->bind_param('ii', $photoId, $reportId);
            $st->execute();
            if ((int)$report['is_published'] === 1 && $photosCount <= 1) {
                $st = $dbcnx->prepare('UPDATE zs_delivered_car_reports SET is_published=0, published_at=NULL, updated_at=NOW() WHERE id=?');
                $st->bind_param('i', $reportId);
                $st->execute();
            }
            $dbcnx->commit();
            $path = dirname(__DIR__, 2) . '/storage/' . $photo['relative_path'] . '/' . $photo['stored_name'];
            if (is_file($path)) {
                unlink($path);
            }
        }
        header('Location: request_public_report.php?request_id=' . $requestId . '&saved=1');
        exit;
    }

    $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 255);
    $reportText = trim((string)($_POST['report_text'] ?? ''));
    $isPublished = !empty($_POST['is_published']) ? 1 : 0;
    $uploads = rh_non_empty_uploads('report_photos');
    $photosBefore = dcr_photos($dbcnx, $reportId);

    try {
        $deliveredAt = dcr_validate_delivered_at($_POST['delivered_at'] ?? '');
        if ($title === '') {
            throw new Exception('Вкажіть заголовок звіту');
        }
        if ($reportText === '') {
            throw new Exception('Додайте короткий опис виконаної роботи');
        }
        if ($isPublished && count($photosBefore) + count($uploads) === 0) {
            throw new Exception('Для публікації додайте хоча б одну фотографію');
        }

        $savedPaths = array();
        $transactionStarted = false;
        $dbcnx->begin_transaction();
        $transactionStarted = true;
        $publishedAtSql = $isPublished ? 'COALESCE(published_at, NOW())' : 'NULL';
        $sql = 'UPDATE zs_delivered_car_reports SET title=?, report_text=?, delivered_at=NULLIF(?, \'\'), is_published=?, published_at=' . $publishedAtSql . ', updated_at=NOW() WHERE id=?';
        $st = $dbcnx->prepare($sql);
        $st->bind_param('sssii', $title, $reportText, $deliveredAt, $isPublished, $reportId);
        $st->execute();

        $sortOrder = count($photosBefore);
        foreach ($uploads as $file) {
            list($mime, $ext) = dcr_validate_image_upload($file);
            $relative = dcr_storage_relative($reportId);
            $directory = dirname(__DIR__, 2) . '/storage/' . $relative;
            if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
                throw new Exception('Не вдалося створити каталог для фотографій');
            }

            $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
            $target = $directory . '/' . $storedName;
            if (!move_uploaded_file($file['tmp_name'], $target)) {
                throw new Exception('Не вдалося зберегти фотографію');
            }
            $savedPaths[] = $target;

            $originalName = mb_substr(basename((string)$file['name']), 0, 255);
            $fileSize = (int)$file['size'];
            $st = $dbcnx->prepare('INSERT INTO zs_delivered_car_report_photos (report_id, stored_name, original_name, relative_path, mime_type, file_size, sort_order, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
            $st->bind_param('issssii', $reportId, $storedName, $originalName, $relative, $mime, $fileSize, $sortOrder);
            $st->execute();
            $sortOrder++;
        }

        $dbcnx->commit();
        $transactionStarted = false;
        header('Location: request_public_report.php?request_id=' . $requestId . '&saved=1');
        exit;
    } catch (Exception $e) {
        if (!empty($transactionStarted)) {
            try {
                $dbcnx->rollback();
            } catch (Exception $ignored) {
            }
        }
        if (!empty($savedPaths)) {
            foreach ($savedPaths as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
        $error = $e->getMessage();
    }
}

$report = dcr_report($dbcnx, $reportId);
$photos = dcr_photos($dbcnx, $reportId);
$csrf = rh_csrf();
$publicUrl = dcr_public_url((int)$report['id']);
?>
<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Звіт для сайту — заявка #<?php echo (int)$request['id']; ?></title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .report-photo { width: 180px; height: 130px; object-fit: cover; }
        .photo-card { display: inline-block; vertical-align: top; margin: 0 12px 18px 0; }
        .new-photo-preview img { width: 120px; height: 90px; object-fit: cover; margin: 6px; border-radius: 6px; }
    </style>
</head>
<body>
<main class="container py-4">
    <a href="viewRequestMsg.php" class="btn btn-outline-secondary mb-3">← До списку заявок</a>
    <h1>Звіт для вебсторінки</h1>
    <p class="text-muted">Заявка #<?php echo (int)$request['id']; ?> · <?php echo rh_h($request['lotImgDir']); ?></p>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo rh_h($error); ?></div>
    <?php elseif (!empty($_GET['saved'])): ?>
        <div class="alert alert-success">Звіт збережено.</div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-body">
            <h5 class="card-title">Дані заявки</h5>
            <p><strong>Ім'я:</strong> <?php echo rh_h($request['name']); ?><br>
                <strong>Телефон:</strong> <?php echo rh_h($request['phone']); ?><br>
                <strong>Дата:</strong> <?php echo rh_h($request['date']); ?><br>
                <strong>Примітка:</strong> <?php echo nl2br(rh_h($request['remark'])); ?></p>
        </div>
    </div>

    <form method="post" enctype="multipart/form-data" class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Публічний звіт</h5>
            <input type="hidden" name="csrf" value="<?php echo rh_h($csrf); ?>">
            <input type="hidden" name="todo" value="save">
            <input type="hidden" name="request_id" value="<?php echo (int)$requestId; ?>">

            <div class="mb-3">
                <label class="form-label" for="title">Заголовок</label>
                <input class="form-control" id="title" name="title" maxlength="255" required value="<?php echo rh_h($report['title']); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="delivered_at">Дата передачі авто</label>
                <input class="form-control" id="delivered_at" name="delivered_at" type="date" value="<?php echo rh_h($report['delivered_at']); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="report_text">Короткий опис виконаної роботи</label>
                <textarea class="form-control" id="report_text" name="report_text" rows="7" required placeholder="Наприклад: знайшли авто, перевірили технічний стан, виконали ремонт, доставили та передали підрозділу."><?php echo rh_h($report['report_text']); ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label" for="reportPhotos">Фотографії</label>
                <input class="form-control" id="reportPhotos" name="report_photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple>
                <div class="form-text">JPG, PNG або WEBP, до <?php echo rh_format_size(DCR_IMAGE_MAX_SIZE); ?> на файл. Загальний розмір форми має вкладатися у post_max_size сервера.</div>
                <div id="newPhotoPreview" class="new-photo-preview mt-2"></div>
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" id="is_published" name="is_published" type="checkbox" value="1"<?php echo (int)$report['is_published'] === 1 ? ' checked' : ''; ?>>
                <label class="form-check-label" for="is_published">Опублікувати у розділі «Передані авто»</label>
            </div>
            <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Зберегти</button>
            <a class="btn btn-outline-success" href="<?php echo rh_h($publicUrl); ?>" target="_blank"><i class="bi bi-box-arrow-up-right"></i> Перегляд</a>
            <?php if ((int)$report['is_published'] === 1): ?>
                <span class="badge bg-success ms-2">Опубліковано</span>
            <?php else: ?>
                <span class="badge bg-secondary ms-2">Чернетка</span>
            <?php endif; ?>
        </div>
    </form>

    <h2>Завантажені фотографії</h2>
    <?php if (!$photos): ?>
        <div class="alert alert-info">Фотографій ще немає.</div>
    <?php endif; ?>
    <div>
        <?php foreach ($photos as $photo): ?>
            <div class="photo-card">
                <a href="<?php echo rh_h(dcr_photo_url((int)$photo['id'])); ?>" target="_blank">
                    <img class="report-photo img-thumbnail" src="<?php echo rh_h(dcr_photo_url((int)$photo['id'])); ?>" alt="Фото звіту">
                </a>
                <form method="post" class="mt-1" onsubmit="return confirm('Видалити фотографію?');">
                    <input type="hidden" name="csrf" value="<?php echo rh_h($csrf); ?>">
                    <input type="hidden" name="todo" value="delete_photo">
                    <input type="hidden" name="request_id" value="<?php echo (int)$requestId; ?>">
                    <input type="hidden" name="photo_id" value="<?php echo (int)$photo['id']; ?>">
                    <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-trash"></i> Видалити</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</main>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    var input = document.getElementById('reportPhotos');
    var preview = document.getElementById('newPhotoPreview');
    input.addEventListener('change', function () {
        preview.innerHTML = '';
        Array.prototype.forEach.call(input.files, function (file) {
            if (file.type.indexOf('image/') !== 0) return;
            var img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.onload = function () { URL.revokeObjectURL(img.src); };
            preview.appendChild(img);
        });
    });
})();
</script>
</body>
</html>
