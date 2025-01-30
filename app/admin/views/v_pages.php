<!-- Таблица сущностей -->
<main>
    <div class="container-fluid px-4">
        <a href="/admin/page_edit/id" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= $lang['sys.add'] ?>" type="button"
           class="btn btn-info mx-1 float-end">
            <i class="fa fa-plus-circle"></i>&nbsp;<?= $lang['sys.add'] ?>
        </a>        
        <h1 class="mt-4"><?= $lang['sys.pages'] ?></h1>
        <div class="row">
            <div class="col">
                <?= $pagesTable ?>
            </div>
        </div>
    </div>
</main>