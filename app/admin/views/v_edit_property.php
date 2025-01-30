<!-- Редактирование свойства -->
<?php if (!$allPropertyTypes) \classes\system\SysClass::handleRedirect(200, '/admin/types_properties');?> 
<main>    
    <form id="edit_entity" action="/admin/edit_property/id/<?= $propertyData['property_id'] ?>" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="fake" value="1" />
        <div class="container-fluid px-4">
            <a href="/admin/edit_property/id" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= $lang['sys.add'] ?>" type="button"
               class="btn btn-info mx-1 float-end<?= empty($propertyData['property_id']) ? " d-none" : "" ?>">
                <i class="fa fa-plus-circle"></i>&nbsp;<?= $lang['sys.add'] ?>
            </a>
            <button type="submit" class="btn btn-primary float-end"><?=$lang['sys.save']?></button>
            <h1 class="mt-4"><?= !$propertyData['property_id'] ? $lang['sys.add'] : $lang['sys.edit'] ?></h1>
            <ol class="breadcrumb mb-4">
                <li>
                    <span id="property_id" data-id="<?= $propertyData['property_id'] ?>">id = <?php echo !$propertyData['property_id'] ? $lang['sys.not_assigned'] : $propertyData['property_id'] ?></span>
                    <input type="hidden" name="property_id" class="form-control" value="<?= $propertyData['property_id'] ? $propertyData['property_id'] : 0 ?>">
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
                                <div class="col-3 col-sm-3">
                                    <label for="name-input"><?=$lang['sys.title']?>:</label>                                    
                                    <input required type="text" id="name-input" name="name" class="form-control"
                                           value="<?= $propertyData['name'] ?>">                                    
                                </div>
                                <div class="col-3 col-sm-3">
                                    <label for="type_id-input"><?=$lang['sys.type']?>:</label>
                                    <div role="group" class="input-group">
                                        <?php if (!$isExistSetsWithProperty) { ?>
                                        <select type="text" id="type_id-input" name="type_id" class="form-control">
                                            <option><?=$lang['sys.empty']?></option>
                                            <?php foreach ($allPropertyTypes as $item) { ?>
                                                <option <?=($propertyData['type_id'] == $item['type_id'] ? 'selected ' : '')?>value="<?=$item['type_id']?>">
                                                <?=$item['name']?></option>
                                            <?php } ?>
                                        </select>
                                        <?php } else { ?>
                                            <input type="hidden" name="type_id" value="<?=$propertyData['type_id']?>">
                                            <input type="text" class="form-control" value="<?=$allPropertyTypes[$propertyData['type_id']]['name']?>" disabled/>
                                        <?php } ?>
                                    </div>
                                </div>
                                <div class="col-3 col-sm-3">
                                    <label for="status-input"><?=$lang['sys.status']?>:</label>
                                    <div role="group" class="input-group">
                                        <select type="text" id="status-input" name="status" class="form-control">
                                            <?php foreach ($allStatus as $key => $value) { ?>
                                                <option <?=($propertyData['status'] == $key ? 'selected ' : '')?>value="<?= $key ?>"><?= $value ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-3 col-sm-3">
                                    <label for="status-input"><?=$lang['sys.entities']?>:</label>
                                    <div role="group" class="input-group">
                                        <?php if (!$isExistSetsWithProperty) { ?>
                                        <select type="text" id="entity_type-input" name="entity_type" class="form-control">
                                            <?php foreach ($allEntityType as $key => $value) { ?>
                                                <option <?=($propertyData['entity_type'] == $key ? 'selected ' : '')?>value="<?= $key ?>"><?= $value ?></option>
                                            <?php } ?>
                                        </select>
                                        <?php } else { ?>
                                            <input type="hidden" name="entity_type" value="<?=$propertyData['entity_type']?>">
                                            <input type="text" class="form-control" value="<?=$allEntityType[$propertyData['entity_type']]?>" disabled/>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-1 col-sm-1">
                                    <label><?=$lang['sys.sort']?>:</label>
                                    <div role="group" class="input-group">
                                        <input required type="text" name="sort" class="form-control"
                                               value="<?= $propertyData['sort'] ?>">                                     
                                    </div>
                                </div>
                            </div>
                            <?php
                                if (1 == 2) { // TODO Убрал для упрощения понимания структуры
                            ?>
                            <div class="row mb-3">
                                <div class="col-3 col-sm-3">
                                    <label for="is_multiple-input"><?=$lang['sys.multiple_choice']?>:</label>
                                    <input type="checkbox" id="is_multiple" name="is_multiple" <?= ($propertyData['is_multiple'] ? 'checked' : '') ?>/>
                                </div>
                                <div class="col-3 col-sm-3">                                    
                                    <label for="is_required-input"><?=$lang['sys.required']?>:</label>
                                    <input type="checkbox" id="is_required" name="is_required" <?= ($propertyData['is_required'] ? 'checked' : '') ?>/>
                                </div>
                            </div>
                            <?php } ?>
                            <div class="row mb-3">
                                <div class="col-12 col-sm-12 card">
                                    <div class="card-body border-primary" id="fields_contents">
                                        <h5 class="card-title"><?=$lang['sys.fields'] . '(' . $lang['sys.field'] . ')'?></h5>
                                        <?php echo \classes\system\Plugins::renderPropertyHtmlFields($propertyData['fields'], $propertyData['default_values']); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="row mb-3">
                                    <div class="col-12 col-sm-12">
                                        <label for="description-input"><?=$lang['sys.description']?>:</label>
                                        <textarea id="description-input" name="description" class="form-control"><?= $propertyData['description'] ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-2">
                                    <label for="registration-date-input"><?=$lang['sys.date_create']?>:</label>
                                    <input type="text" disabled id="registration-date-input" class="form-control" value="<?= $propertyData['created_at'] ?>">
                                </div>
                                <div class="col-2">
                                    <label for="update-date-input"><?=$lang['sys.date_update']?>:</label>
                                    <input type="text" disabled id="update-date-input" class="form-control" value="<?= $propertyData['updated_at'] ?>">
                                </div>
                            </div>
                        </div>                       
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <button type="submit" class="btn btn-primary my-3"><?=$lang['sys.save']?></button>
                </div>                    
            </div>
        </div>		
    </form>
</main>