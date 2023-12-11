<!-- Таблица категорий -->
<main>
    <div class="container-fluid px-4">
        <a href="/admin/type_properties_edit/id" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= $lang['sys.add'] ?>" type="button"
           class="btn btn-info m-l-15 float-end">
            <i class="fa fa-plus-circle"></i>&nbsp;<?= $lang['sys.add'] ?>
        </a>        
        <h1 class="mt-4">Типы свойств</h1>
        <div class="row">
            <div class="col">
                <?= $types_properties_table ?>
            </div>
        </div>
    </div>
</main>