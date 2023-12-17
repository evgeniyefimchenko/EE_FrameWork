<?php
use classes\system\SysClass;
use classes\system\Constants;
use classes\helpers\ClassNotifications;
use classes\system\Plugins;
?>
<!-- Редактирование типа свойств сущности -->
<main>    
    <form id="edit_entity" action="/admin/type_properties_edit/id/<?= $property_type_data['type_id'] ?>" method="POST">
        <input type="hidden" name="fake" value="1" />
        <div class="container-fluid px-4">
            <a href="/admin/type_properties_edit/id" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= $lang['sys.add'] ?>" type="button"
               class="btn btn-info mx-1 float-end<?= empty($property_type_data['type_id']) ? " d-none" : "" ?>">
                <i class="fa fa-plus-circle"></i>&nbsp;<?= $lang['sys.add'] ?>
            </a>
            <h1 class="mt-4"><?= !$property_type_data ? 'Добавить Тип Свойств' : 'Редактировать Тип Свойств' ?></h1>
            <ol class="breadcrumb mb-4">
                <li>
                    <span id="type_id" data-id="<?= $property_type_data['type_id'] ?>">id = <?php echo!$property_type_data['type_id'] ? 'Не присвоен' : $property_type_data['type_id'] ?></span>
                    <input type="hidden" name="type_id" class="form-control" value="<?= $property_type_data['type_id'] ? $property_type_data['type_id'] : 0 ?>">
                </li>              
            </ol>
            <div class="row">
                <div class="col">
                    <ul class="nav nav-tabs" id="eeTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic-tab-pane" type="button" role="tab" aria-controls="basic-tab-pane" aria-selected="true"><?= $lang['sys.basics'] ?></button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="basic-tab" data-bs-toggle="tab" data-bs-target="#fields-tab-pane" type="button" role="tab" aria-controls="fields-tab-pane" aria-selected="true">Поля</button>
                        </li>
                    </ul>
                    <div class="tab-content" id="eeTabContent">
                        <!-- Основное содержимое -->
                        <div class="tab-pane show active mt-3" id="basic-tab-pane" role="tabpanel" aria-labelledby="basic-tab">
                            <div class="row mb-3">
                                <div class="col-6 col-sm-3">
                                    <label for="name-input"><?= $lang['sys.title'] ?>:</label>                                    
                                    <input type="text" id="name-input" name="name" class="form-control" placeholder="Введите название..." value="<?= $property_type_data['name'] ?>">                                    
                                </div>
                                <div class="col-6 col-sm-3">
                                    <label for="type_id-input"><?= $lang['sys.status'] ?>:</label>
                                    <div role="group" class="input-group">
                                        <select type="text" id="status-input" name="status" class="form-control">
                                            <?php foreach ($all_status as $key => $value) { ?>
                                                <option <?=($property_type_data['status'] == $key ? 'selected ' : '')?>value="<?= $key ?>"><?= $value ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="row mb-3">
                                    <div class="col-12 col-sm-12">
                                        <label for="description-input"><?= $lang['sys.description'] ?>:</label>
                                        <textarea id="description-input" name="description" class="form-control"><?= $property_type_data['description'] ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-2">
                                    <label for="registration-date-input"><?= $lang['sys.date_create'] ?>:</label>
                                    <input type="text" disabled  class="form-control" value="<?= $property_type_data['created_at'] ?>">
                                </div>
                                <div class="col-2">
                                    <label for="update-date-input"><?= $lang['sys.date_update'] ?>:</label>
                                    <input type="text" disabled class="form-control" value="<?= $property_type_data['updated_at'] ?>">
                                </div>
                            </div>
                        </div>
                        <!-- Поля типа свойства -->
                        <div class="tab-pane fade mt-3" id="fields-tab-pane" role="tabpanel" aria-labelledby="fields-tab-pane">
                            <div class="row mb-3" id="fields-container">
                                <div class="col-5 d-flex align-items-center">
                                    <label class="w-25 form-label">Тип поля:</label>
                                    <select required class="form-select me-2" name="fields[]">
                                        <option disabled selected>Выберите тип поля...</option>
                                        <?php foreach (Constants::ALL_TYPE_PROPERTY_TYPES_FIELDS as $k => $v) { ?>
                                            <option value="<?=$k?>"><?=$v?></option>
                                        <?php } ?>
                                        <!-- Добавьте дополнительные типы полей по мере необходимости в класс Constants -->
                                    </select>
                                    <button class="btn btn-primary add-field-btn" type="button"><i class="fa fa-plus-circle"></i></button>
                                </div>
                            </div>
                            <?php
                                $property_type_data['fields'] = is_array($property_type_data['fields']) ? $property_type_data['fields'] : [];
                                foreach ($property_type_data['fields'] as $item) { ?>
                                    <div class="row mb-3" id="fields-container">
                                        <div class="col-5 d-flex align-items-center">
                                            <label class="w-25 form-label">Тип поля:</label>
                                            <select required class="form-select me-2" name="fields[]">
                                                <option disabled>Выберите тип поля...</option>
                                                <?php foreach (Constants::ALL_TYPE_PROPERTY_TYPES_FIELDS as $k => $v) { ?>
                                                    <option <?= $item == $k ? 'selected ' : ''?>value="<?=$k?>"><?=$v?></option>
                                                <?php } ?>
                                            </select>
                                            <button class="btn btn-danger remove-field-btn" type="button"><i class="fa fa-minus-circle"></i></button>
                                        </div>
                                    </div>                                                
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Сохранить</button>             
        </div>		
    </form>
</main>