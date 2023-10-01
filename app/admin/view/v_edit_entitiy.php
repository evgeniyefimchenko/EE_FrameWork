<?php
if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}
?>
<!-- Редактирование сущности -->
<?php if (!$all_type) SysClass::return_to_main(200, '/admin/type_categories');?> 
<main>    
    <form id="edit_entitiy" action="/admin/entitiy_edit/id/<?= $entitiy_data['entity_id'] ?>" method="POST">
        <input type="hidden" name="fake" value="1" />
        <div class="container-fluid px-4">
            <a href="/admin/entitiy_edit/id" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= $lang['sys.add'] ?>" type="button"
               class="btn btn-info m-l-15 float-end<?= empty($entitiy_data['entity_id']) ? " d-none" : "" ?>">
                <i class="fa fa-plus-circle"></i>&nbsp;<?= $lang['sys.add'] ?>
            </a>
            <h1 class="mt-4"><?= !$entitiy_data ? 'Добавить Сущность' : 'Редактировать Сущность' ?></h1>
            <ol class="breadcrumb mb-4">
                <li>
                    <span id="entity_id" data-id="<?= $entitiy_data['entity_id'] ?>">id = <?php echo !$entitiy_data['entity_id'] ? 'Не присвоен' : $entitiy_data['entity_id'] ?></span>
                    <input type="hidden" name="entity_id" class="form-control" value="<?= $entitiy_data['entity_id'] ? $entitiy_data['entity_id'] : 0 ?>">
                </li>              
            </ol>
            <div class="row">
                <div class="col">
                    <ul class="nav nav-tabs" id="eeTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic-tab-pane" type="button" role="tab" aria-controls="basic-tab-pane" aria-selected="true">Основное</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="features-tab" data-bs-toggle="tab" data-bs-target="#features-tab-pane" type="button" role="tab" aria-controls="features-tab-pane"><?=$lang['features']?></button>
                        </li>
                    </ul>
                    <div class="tab-content" id="eeTabContent">
                        <!-- Основное содержимое -->
                        <div class="tab-pane fade show active mt-3" id="basic-tab-pane" role="tabpanel" aria-labelledby="basic-tab">
                            <div class="row mb-3">
                                <div class="col-6 col-sm-3">
                                    <label for="title-input"><?=$lang['sys.title']?>:</label>                                    
                                    <input type="text" id="title-input" name="title" class="form-control" placeholder="Введите название..." value="<?= $entitiy_data['title'] ?>">                                    
                                </div>
                                <div class="col-6 col-sm-3">
                                    <label for="category_id-input"><?=$lang['sys.category'] . ' ' . $lang['sys.parent']?>:</label>
                                    <div role="group" class="input-group">
                                        <select <?=$entitiy_data['parent_entity_id'] ? "disabled " : ""?>type="text" id="category_id-input" name="category_id" class="form-control">
                                            <?php foreach ($all_categories as $item) {
                                                $prefix = str_repeat('-', $item['level'] * 2); // Умножаем уровень на 2, чтобы создать отступ
                                                echo '<option ' . ($entitiy_data['category_id'] == $item['category_id'] ? "selected " : "") . 'value="' . $item['category_id'] . '">' .
                                                        $prefix . ' ' . htmlspecialchars($item['title']) . ' (' . ($all_type[$item['type_id']]['name'] ? $all_type[$item['type_id']]['name'] : 'Нет') . ')</option>';
                                            } ?>                                        
                                        </select>
                                        <?php if (!$entitiy_data['parent_entity_id']) { ?>
                                            <span role="button" class="input-group-text btn-primary" data-bs-toggle="tooltip" data-bs-placement="top" title="Отдельное окно">
                                                <i class="fas fa-tree"></i><!-- Иконка со знаком вопроса -->
                                            </span> 
                                        <?php } ?>
                                    </div>
                                </div>
                                <div class="col-6 col-sm-3">
                                    <label for="parent_entity_id-input"><?=$lang['sys.entitiy']. ' ' . $lang['sys.parent']?>:</label>
                                    <div role="group" class="input-group">
                                        <select type="text" id="parent_entity_id-input" name="parent_entity_id" class="form-control">
                                            <?php foreach ($all_entities as $item) {
                                                echo '<option ' . ($entitiy_data['parent_entity_id'] == $item['entity_id'] ? "selected " : "") . 'value="' . $item['entity_id'] . '">' . $item['title'] . '</option>';
                                            } ?>
                                        </select>
                                        <span role="button" class="input-group-text btn-primary" data-bs-toggle="tooltip" data-bs-placement="top" title="Отдельное окно">
                                            <i class="fas fa-tree"></i><!-- Иконка со знаком вопроса -->
                                        </span>                                        
                                    </div>
                                </div>
                                <div class="col-6 col-sm-3">
                                    <label for="type_id-input"><?=$lang['sys.type']?>:</label>
                                    <div role="group" class="input-group">
                                        <input type="text" disabled id="type_id-input" name="type_id" class="form-control" placeholder="Введите название..." value="<?= $entitiy_data['type_name'] ?>">
                                        <span role="button" class="input-group-text btn-primary" data-bs-toggle="tooltip" data-bs-placement="top" title="Определяется из категории">
                                            <i class="fas fa-question-circle"></i><!-- Иконка со знаком вопроса -->
                                        </span>
                                    </div>
                                </div>                                
                                <div class="row mb-3">
                                    <div class="col-6 col-sm-3">
                                        <label for="status-input">Статус:</label>
                                            <?php $statuses = [['id' => 'active', 'name' => $lang['sys.active']], ['id' => 'disabled', 'name' => $lang['sys.blocked']], ['id' => 'hidden', 'name' => $lang['sys.not_confirmed']]] ?>
                                        <select required id="status-input" name="status" class="form-control">
                                            <?php foreach ($statuses as $item) { ?>
                                                <option <?$entitiy_data['status'] == $item['id'] ? 'selected ' : ''?>value="<?= $item['id'] ?>"><?= $item['name'] ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-12 col-sm-12">
                                        <label for="description-input"><?=$lang['sys.description']?>:</label>
                                        <textarea id="description-input" name="description" class="form-control"><?= $entitiy_data['description'] ?></textarea>
                                    </div>
                                </div>							
                                <div class="row mb-3">
                                    <div class="col-12 col-sm-12">
                                        <label for="short_description-input"><?=$lang['sys.short_description']?>:</label>
                                        <textarea id="short_description-input" name="short_description" class="form-control"><?= $entitiy_data['short_description'] ?></textarea>
                                    </div>
                                </div>							
                            </div>
                            <div class="row mb-3">
                                <div class="col-2">
                                    <label for="registration-date-input"><?=$lang['sys.date_create']?>:</label>
                                    <input type="text" disabled  class="form-control" value="<?= $entitiy_data['created_at'] ?>">
                                </div>
                                <div class="col-2">
                                    <label for="update-date-input"><?=$lang['sys.date_update']?>:</label>
                                    <input type="text" disabled class="form-control" value="<?= $entitiy_data['updated_at'] ?>">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Сохранить</button>
                        </div>
                        <!-- Свойства -->
                        <div class="tab-pane fade show mt-3" id="features-tab-pane" role="tabpanel" aria-labelledby="features-tab">
                            features
                        </div>                        
                    </div>
                </div>
            </div>            
        </div>		
    </form>
</main>