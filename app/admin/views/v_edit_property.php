<?php
use classes\system\SysClass;
use classes\system\Plugins;
?>
<!-- Редактирование свойства -->
<?php if (!$all_property_types) SysClass::return_to_main(200, '/admin/types_properties');?> 
<main>    
    <form id="edit_entity" action="/admin/edit_property/id/<?= $property_data['property_id'] ?>" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="fake" value="1" />
        <div class="container-fluid px-4">
            <a href="/admin/edit_property/id" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= $lang['sys.add'] ?>" type="button"
               class="btn btn-info mx-1 float-end<?= empty($property_data['property_id']) ? " d-none" : "" ?>">
                <i class="fa fa-plus-circle"></i>&nbsp;<?= $lang['sys.add'] ?>
            </a>
            <h1 class="mt-4"><?= !$property_data['property_id'] ? $lang['sys.add'] : $lang['sys.edit'] ?></h1>
            <ol class="breadcrumb mb-4">
                <li>
                    <span id="property_id" data-id="<?= $property_data['property_id'] ?>">id = <?php echo !$property_data['property_id'] ? $lang['sys.not_assigned'] : $property_data['property_id'] ?></span>
                    <input type="hidden" name="property_id" class="form-control" value="<?= $property_data['property_id'] ? $property_data['property_id'] : 0 ?>">
                </li>              
            </ol>
            <div class="row">
                <div class="col-16">
                    <ul class="nav nav-tabs" id="eeTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic-tab-pane"
                                    type="button" role="tab" aria-controls="basic-tab-pane" aria-selected="true"><?= $lang['sys.basics'] ?></button>
                        </li>
                    </ul>
                    <div class="tab-content" id="eeTabContent">
                        <!-- Основное содержимое -->
                        <div class="tab-pane fade show active mt-3" id="basic-tab-pane" role="tabpanel" aria-labelledby="basic-tab">
                            <div class="row mb-3">
                                <div class="col-4 col-sm-3">
                                    <label for="name-input"><?=$lang['sys.title']?>:</label>                                    
                                    <input required type="text" id="name-input" name="name" class="form-control"
                                           value="<?= $property_data['name'] ?>">                                    
                                </div>
                                <div class="col-4 col-sm-3">
                                    <label for="type_id-input"><?=$lang['sys.type'] . ' ' . $lang['sys.properties']?>:</label>
                                    <div role="group" class="input-group">
                                        <select type="text" id="type_id-input" name="type_id" class="form-control">
                                            <option><?=$lang['sys.empty']?></option>
                                            <?php foreach ($all_property_types as $item) { ?>
                                                <option <?=($property_data['type_id'] == $item['type_id'] ? 'selected ' : '')?>value="<?=$item['type_id']?>">
                                                <?=$item['name']?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-4 col-sm-3">
                                    <label for="status-input"><?=$lang['sys.status'] . ' ' . $lang['sys.properties']?>:</label>
                                    <div role="group" class="input-group">
                                        <select type="text" id="status-input" name="status" class="form-control">
                                            <?php foreach ($all_status as $key => $value) { ?>
                                                <option <?=($property_data['status'] == $key ? 'selected ' : '')?>value="<?= $key ?>"><?= $value ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-1 col-sm-1">
                                    <label><?=$lang['sys.sort']?>:</label>
                                    <div role="group" class="input-group">
                                        <input required type="text" name="sort" class="form-control"
                                               value="<?= $property_data['sort'] ?>">                                     
                                    </div>
                                </div>
                            </div>
                            <?php
                                if (1 == 2) { // Убрал для упрощения структуры
                            ?>
                            <div class="row mb-3">
                                <div class="col-3 col-sm-3">
                                    <label for="is_multiple-input"><?=$lang['sys.multiple_choice']?>:</label>
                                    <input type="checkbox" id="is_multiple" name="is_multiple" <?= ($property_data['is_multiple'] ? 'checked' : '') ?>/>
                                </div>
                                <div class="col-3 col-sm-3">                                    
                                    <label for="is_required-input"><?=$lang['sys.required']?>:</label>
                                    <input type="checkbox" id="is_required" name="is_required" <?= ($property_data['is_required'] ? 'checked' : '') ?>/>
                                </div>
                            </div>
                            <?php } ?>
                            <div class="row mb-3">
                                <div class="col-12 col-sm-12 card">
                                    <div class="card-body border-primary" id="fields_contents">
                                        <h5 class="card-title"><?=$lang['sys.fields'] . '(' . $lang['sys.field'] . ')'?></h5>
                                        <?php echo Plugins::renderPropertyHtmlFields($property_data['fields'], $property_data['default_values']); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="row mb-3">
                                    <div class="col-12 col-sm-12">
                                        <label for="description-input"><?=$lang['sys.description']?>:</label>
                                        <textarea id="description-input" name="description" class="form-control"><?= $property_data['description'] ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-2">
                                    <label for="registration-date-input"><?=$lang['sys.date_create']?>:</label>
                                    <input type="text" disabled id="registration-date-input" class="form-control" value="<?= $property_data['created_at'] ?>">
                                </div>
                                <div class="col-2">
                                    <label for="update-date-input"><?=$lang['sys.date_update']?>:</label>
                                    <input type="text" disabled id="update-date-input" class="form-control" value="<?= $property_data['updated_at'] ?>">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary"><?= $lang['sys.save'] ?></button>
                        </div>                       
                    </div>
                </div>
            </div>            
        </div>		
    </form>
</main>