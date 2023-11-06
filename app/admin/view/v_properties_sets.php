<?php
if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}
?>
<!-- Таблица наборов свойств -->
<main>
    <div class="container-fluid px-4">
        <a href="/admin/edit_property_sets/id" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= $lang['sys.add'] ?>" type="button"
           class="btn btn-info m-l-15 float-end">
            <i class="fa fa-plus-circle"></i>&nbsp;<?= $lang['sys.add'] ?>
        </a>        
        <h1 class="mt-4"><?= $lang['sys.sets'] ?></h1>
        <div class="row">
            <div class="col">
                <?= $properties_property_sets_table ?>
            </div>
        </div>
    </div>
</main>
