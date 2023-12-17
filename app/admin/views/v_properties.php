<!-- Таблица свойств -->
<?php if (!$all_property_types) SysClass::return_to_main(200, '/admin/types_properties');?> 
<main>
    <div class="container-fluid px-4">
        <a href="/admin/edit_property/id" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= $lang['sys.add'] ?>" type="button"
           class="btn btn-info mx-1 float-end">
            <i class="fa fa-plus-circle"></i>&nbsp;<?= $lang['sys.add'] ?>
        </a>        
        <h1 class="mt-4"><?= $lang['sys.properties'] ?></h1>
        <div class="row">
            <div class="col">
                <?= $properties_table ?>
            </div>
        </div>
    </div>
</main>