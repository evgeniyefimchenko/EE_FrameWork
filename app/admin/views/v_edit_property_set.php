<?php
use classes\system\SysClass;
use classes\helpers\ClassNotifications;
use classes\system\Plugins;
?>
<!-- Редактирование набора свойства -->
<main>    
    <form id="edit_entity" method="POST" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="fake" value="1" />
        <div class="container-fluid px-4">
            <a href="/admin/edit_property_set/id" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= $lang['sys.add'] ?>" type="button"
               class="btn btn-info mx-1 float-end<?= empty($property_set_data['set_id']) ? " d-none" : "" ?>">
                <i class="fa fa-plus-circle"></i>&nbsp;<?= $lang['sys.add'] ?>
            </a>
            <button type="submit" class="btn btn-primary float-end"><?=$lang['sys.save']?></button>            
            <h1 class="mt-4"><?= !$property_set_data ? $lang['sys.add'] : $lang['sys.edit'] ?></h1>
            <ol class="breadcrumb mb-4">
                <li>
                    <span id="set_id" data-id="<?= $property_set_data['set_id'] ?>">id = <?php echo !$property_set_data['set_id'] ? $lang['sys.not_assigned'] : $property_set_data['set_id'] ?></span>
                    <input type="hidden" name="set_id" class="form-control" value="<?= $property_set_data['set_id'] ? $property_set_data['set_id'] : 0 ?>">
                </li>
            </ol>
            <div class="row">
                <div class="col">
                    <ul class="nav nav-tabs" id="eeTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic-tab-pane" type="button" role="tab" aria-controls="basic-tab-pane" aria-selected="true"><?= $lang['sys.basics'] ?></button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="properties-tab" data-bs-toggle="tab" data-bs-target="#properties-tab-pane" type="button" role="tab" aria-controls="properties-tab-pane" aria-selected="true"><?= $lang['sys.properties'] ?></button>
                        </li>
                    </ul>
                    <div class="tab-content" id="eeTabContent">
                        <!-- Основное содержимое -->
                        <div class="tab-pane fade show active mt-3" id="basic-tab-pane" role="tabpanel" aria-labelledby="basic-tab">
                            <div class="row mb-3">
                                <div class="col-4 col-sm-3">
                                    <label for="name-input"><?= $lang['sys.title'] ?>:</label>                                    
                                    <input required type="text" id="name-input" name="name" class="form-control" placeholder="Введите название..." value="<?= $property_set_data['name'] ?>">                                    
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="row mb-3">
                                    <div class="col-12 col-sm-12">
                                        <label for="description-input"><?= $lang['sys.description'] ?>:</label>
                                        <textarea id="description-input" name="description" class="form-control"><?= $property_set_data['description'] ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-3">
                                    <label for="registration-date-input"><?= $lang['sys.date_create'] ?>:</label>
                                    <input type="text" disabled  class="form-control" value="<?= $property_set_data['created_at'] ?>" id="registration-date-input">
                                </div>
                                <div class="col-3">
                                    <label for="update-date-input"><?= $lang['sys.date_update'] ?>:</label>
                                    <input type="text" disabled class="form-control" value="<?= $property_set_data['updated_at'] ?>" id="update-date-input">
                                </div>
                            </div>                            
                        </div>                       
                        <!-- Свойства -->
                        <div class="tab-pane fade mt-3" id="properties-tab-pane" role="tabpanel" aria-labelledby="properties-tab-pane">
                            <div class="row mb-3">
                                <div class="col-12">
                                    <h5><?=$lang['sys.list_properties_for_set']?></h5>
                                    <form id="add-properties-to-set-form">
                                        <?php foreach ($all_properties_data as $property): ?>
                                            <?php if ($property['status'] === 'active'): ?>
                                                <div class="form-check">
                                                    <input<?=$isExistCategoryTypeWithSet ? ' disabled' : ''?> class="form-check-input" type="checkbox" name="selected_properties[]"
                                                        <?php if (isset($property_set_data['properties'][$property['property_id']])) echo 'checked ';?>
                                                        value="<?php echo $property['property_id']; ?>" id="property-<?php echo $property['property_id']; ?>">
                                                    <label class="form-check-label" for="property-<?php echo $property['property_id']; ?>">
                                                        <?php echo htmlspecialchars($property['name']) . '(' . $lang['sys.' . $property['entity_type']] . ')'; ?>
                                                        <?php if (!empty($property['description'])): ?>
                                                            <br/><small class="text-muted">(<?php echo htmlspecialchars($property['description']); ?>)</small>
                                                        <?php endif; ?>
                                                    </label>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </form>
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