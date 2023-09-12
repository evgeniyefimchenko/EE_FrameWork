<?php
if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}
?>

<!-- Таблица категорий -->
<main>
    <div class="container-fluid px-4">
        <h1 class="mt-4"><?= $lang['type_categories'] ?></h1>
        <ol class="breadcrumb mb-4">
            <li class="breadcrumb-item active">
                <a href="/admin/type_edit/id" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= $lang['sys.add'] ?>" type="button" class="btn btn-info d-none d-lg-block m-l-15">
                    <i class="fa fa-plus-circle"></i>&nbsp;<?= $lang['sys.add'] ?>
                </a>
            </li>
        </ol>
        <div class="row">
            <div class="col">
                <?= $types_table ?>
            </div>
        </div>
    </div>
</main>