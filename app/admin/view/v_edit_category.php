<?php
if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}
?>
<!-- Редактирование категории -->
<?php if (!$all_type) SysClass::return_to_main(200, '/admin/type_categories');?> 
<main>    
    <form id="edit_category" action="/admin/category_edit/id/<?= $category_data['category_id'] ?>" method="POST">
        <input type="hidden" name="fake" value="1" />
        <div class="container-fluid px-4">
            <a href="/admin/category_edit/id" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= $lang['sys.add'] ?>" type="button"
               class="btn btn-info m-l-15 float-end<?= empty($category_data['category_id']) ? " d-none" : "" ?>">
                <i class="fa fa-plus-circle"></i>&nbsp;<?= $lang['sys.add'] ?>
            </a>
            <h1 class="mt-4"><?= !$category_data ? 'Добавить категорию' : 'Редактировать категорию' ?></h1>
            <ol class="breadcrumb mb-4">
                <li>
                    <span id="category_id" data-id="<?= $category_data['category_id'] ?>">id = <?php echo !$category_data['category_id'] ? 'Не присвоен' : $category_data['category_id'] ?></span>
                    <input type="hidden" name="category_id" class="form-control" value="<?= $category_data['category_id'] ? $category_data['category_id'] : 0 ?>">
                </li>              
            </ol>
            <div class="row">
                <div class="col">
                    <ul class="nav nav-tabs" id="eeTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic-tab-pane" type="button" role="tab" aria-controls="basic-tab-pane" aria-selected="true">Основное</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="entities-tab" data-bs-toggle="tab" data-bs-target="#entities-tab-pane" type="button" role="tab" aria-controls="entities-tab-pane" aria-selected="false">Сущности категории</button>
                        </li>
                    </ul>
                    <div class="tab-content" id="eeTabContent">
                        <!-- Основное содержимое -->
                        <div class="tab-pane fade show active mt-3" id="basic-tab-pane" role="tabpanel" aria-labelledby="basic-tab">
                            <div class="row mb-3">
                                <div class="col-6 col-sm-3">
                                    <label for="name-input"><?=$lang['sys.title']?>:</label>
                                    <div role="group" class="input-group">
                                        <input type="text" id="title-input" name="title" class="form-control" placeholder="Введите название..." value="<?= $category_data['title'] ?>">
                                        <span role="button" class="input-group-text btn-info" data-bs-toggle="tooltip" data-bs-placement="top" title="Название должно быть уникальность в рамках одного типа">
                                            <i class="fas fa-question-circle"></i><!-- Иконка со знаком вопроса -->
                                        </span>
                                    </div>
                                </div>
                                <div class="col-6 col-sm-3">
                                    <label for="type_id-input">Тип:</label>
                                    <div role="group" class="input-group">
                                        <select <?= $category_data['parent_id'] || $category_data['type_id'] ? "disabled " : "" ?>id="type_id-input" name="type_id" class="form-control">
                                            <?php foreach ($all_type as $type) {?>
                                                <option <?= $category_data['type_id'] == $type['type_id'] ? "selected " : "" ?>value="<?= $type['type_id'] ?>"><?= $type['name'] ?></option>
                                            <?php } ?>
                                        </select>
                                        <?php
                                            if ($category_data['type_id']) {
                                                echo '<input type="hidden" name="type_id" value="' . $category_data['type_id'] . '">';
                                            }
                                        ?>
                                        <span role="button" class="input-group-text btn-danger" data-bs-toggle="tooltip" data-bs-placement="top" title="Тип задаётся один раз и не может отличаться от родительской категории!">
                                            <i class="fas fa-question-circle"></i><!-- Иконка со знаком вопроса -->
                                        </span>                                        
                                    </div>
                                </div>
                                <div class="col-6 col-sm-3">
                                    <label for="parent_id-input"><?=$lang['parent']?>:</label>
                                    <div role="group" class="input-group">
                                        <select type="text" id="parent_id-input" name="parent_id" class="form-control">
                                            <?php foreach ($parents as $item) {
                                                $prefix = str_repeat('-', $item['level'] * 2); // Умножаем уровень на 2, чтобы создать отступ
                                                echo '<option ' . ($category_data['parent_id'] == $item['category_id'] ? "selected " : "") . 'value="' . $item['category_id'] . '">' .
                                                        $prefix . ' ' . htmlspecialchars($item['title']) . ' (' . ($all_type[$item['type_id']]['name'] ? $all_type[$item['type_id']]['name'] : 'Нет') . ')</option>';
                                            } ?>                                        
                                        </select>
                                        <span role="button" class="input-group-text btn-primary" data-bs-toggle="tooltip" data-bs-placement="top" title="Отдельное окно">
                                            <i class="fas fa-tree"></i><!-- Иконка со знаком вопроса -->
                                        </span>                                        
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-6 col-sm-3">
                                        <label for="status-input">Статус:</label>
                                            <?php $statuses = [['id' => 'active', 'name' => $lang['sys.active']], ['id' => 'disabled', 'name' => $lang['sys.blocked']], ['id' => 'hidden', 'name' => $lang['sys.not_confirmed']]] ?>
                                        <select required id="status-input" name="status" class="form-control">
                                            <?php foreach ($statuses as $item) { ?>
                                                <option <?$category_data['status'] == $item['id'] ? 'selected ' : ''?>value="<?= $item['id'] ?>"><?= $item['name'] ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-12 col-sm-12">
                                        <label for="description-input"><?=$lang['sys.description']?>:</label>
                                        <textarea id="description-input" name="description" class="form-control"><?= $category_data['description'] ?></textarea>
                                    </div>
                                </div>							
                                <div class="row mb-3">
                                    <div class="col-12 col-sm-12">
                                        <label for="short_description-input"><?=$lang['sys.short_description']?>:</label>
                                        <textarea id="short_description-input" name="short_description" class="form-control"><?= $category_data['short_description'] ?></textarea>
                                    </div>
                                </div>							
                            </div>
                            <div class="row mb-3">
                                <div class="col-2">
                                    <label for="registration-date-input"><?=$lang['date_create']?>:</label>
                                    <input type="text" disabled  class="form-control" value="<?= $category_data['created_at'] ?>">
                                </div>
                                <div class="col-2">
                                    <label for="update-date-input"><?=$lang['date_update']?>:</label>
                                    <input type="text" disabled class="form-control" value="<?= $category_data['updated_at'] ?>">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Сохранить</button>
                        </div>
                        <!-- Содержимое для присоединённых сущностей -->
                        <div class="tab-pane fade mt-3" id="entities-tab-pane" role="tabpanel" aria-labelledby="entities-tab">
                            <div class="row">
                                <div class="col">
                                    <?= $category_entitys ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>            
        </div>		
    </form>
</main>