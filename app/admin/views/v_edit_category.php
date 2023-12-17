<?php
use classes\system\SysClass;
use classes\helpers\ClassNotifications;
use classes\system\Plugins;

if (!count($all_type)) {
   ClassNotifications::add_notification_user($this->logged_in, ['text' => 'Необходимо создать хотя бы один тип категории!', 'status' => 'info']);
   SysClass::return_to_main(200, '/admin/types_categories');
}
?>
<!-- Редактирование категории -->
<main>    
    <form id="edit_category" action="/admin/category_edit/id/<?= $category_data['category_id'] ?>" method="POST">
        <input type="hidden" name="fake" value="1" />
        <div class="container-fluid px-4">
            <a href="/admin/category_edit/id" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= $lang['sys.add'] ?>" type="button"
               class="btn btn-info mx-1 float-end<?= empty($category_data['category_id']) ? " d-none" : "" ?>">
                <i class="fa fa-plus-circle"></i>&nbsp;<?= $lang['sys.add'] ?>
            </a>
            <h1 class="mt-4"><?= !$category_data ? 'Добавить категорию' : 'Редактировать категорию' ?></h1>
            <ol class="breadcrumb mb-4">
                <li>
                    <span id="category_id" data-id="<?= $category_data['category_id'] ?>">id = <?php echo !$category_data['category_id'] ? $lang['sys.not_assigned'] : $category_data['category_id'] ?></span>
                    <input type="hidden" name="category_id" class="form-control" value="<?= $category_data['category_id'] ? $category_data['category_id'] : 0 ?>">
                </li>              
            </ol>
            <div class="row">
                <div class="col">
                    <ul class="nav nav-tabs" id="eeTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic-tab-pane" type="button" role="tab" aria-controls="basic-tab-pane" aria-selected="true"><?=$lang['sys.basics']?></button>
                        </li>
                        <?php if ($category_data['category_id']) { ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="entities-tab" data-bs-toggle="tab" data-bs-target="#entities-tab-pane" type="button" role="tab" aria-controls="entities-tab-pane" aria-selected="false"><?=$lang['sys.category_entities']?></button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="property_sets-tab" data-bs-toggle="tab" data-bs-target="#property_sets-tab-pane" type="button" role="tab" aria-controls="property_sets-tab-pane" aria-selected="false"><?=$lang['sys.property_sets']?></button>
                        </li>
                        <?php } ?>
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
                                        <select id="type_id-input" name="type_id" class="form-control">
                                            <?=Plugins::show_type_categogy_for_select($all_type, $category_data['type_id']); ?>
                                        </select>
                                        <?php
                                        if (isset($category_data['parent_id'])) {
                                            $text_to_type = 'При наличии категории родителя можно выбрать только дочерний тип категории родителя.';
                                        } else {
                                            $text_to_type = 'Все наборы свойств типа будут унаследованы дочерними категориями.';
                                        }
                                        ?>                                        
                                        <span role="button" class="input-group-text btn-danger" data-bs-toggle="tooltip" data-bs-placement="top" title="<?=$text_to_type?>">
                                            <i class="fas fa-question-circle"></i><!-- Иконка со знаком вопроса -->
                                        </span>                                        
                                    </div>
                                </div>
                                <div class="col-6 col-sm-3 mb-3">
                                    <label for="parent_id-input"><?=$lang['sys.parent']?>:</label>
                                    <div role="group" class="input-group">
                                        <select id="parent_id-input" name="parent_id" class="form-control">
                                            <?php echo Plugins::show_categogy_for_select($categories_tree, $category_data['parent_id']); ?>
                                        </select>                                        
                                        <span title="<?=$category_data['category_path_text']?>" data-bs-toggle="tooltip" data-bs-placement="top" role="button" class="input-group-text btn-primary">
                                            <i class="fas fa-tree" data-bs-toggle="modal" data-bs-target="#parents_modal"></i><!-- Иконка со знаком вопроса -->
                                        </span>                                        
                                    </div>
                                    <?= Plugins::ee_generateModal('parents_modal', $lang['sys.categories'], Plugins::renderCategoryTree($full_categories_tree))?>
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
                                    <label for="registration-date-input"><?=$lang['sys.date_create']?>:</label>
                                    <input type="text" disabled  class="form-control" value="<?= $category_data['created_at'] ?>">
                                </div>
                                <div class="col-2">
                                    <label for="update-date-input"><?=$lang['sys.date_update']?>:</label>
                                    <input type="text" disabled class="form-control" value="<?= $category_data['updated_at'] ?>">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Сохранить</button>
                        </div>
                        <!-- Содержимое для присоединённых сущностей -->
                        <div class="tab-pane fade mt-3" id="entities-tab-pane" role="tabpanel" aria-labelledby="entities-tab">
                            <div class="row">
                                <div class="col">
                                    <?php
                                        $html = '';
                                        foreach ($category_entities as $entity) {
                                            $html .= '<div class="card">';
                                            $color_status = 'text-success';
                                            if ($entity['status'] != 'active') {
                                                $color_status = 'text-danger';
                                            }
                                            $html .= '<div class="row align-items-center">';
                                            $html .= '<div class="col-auto">№ ' . $entity['entity_id'] . '</div>';
                                            $html .= '<div class="col"><a href="/admin/entity_edit/id/' . $entity['entity_id'] . '" target="_BLANK">' . $entity['title'] . '</a></div>';
                                            $html .= '</div>';
                                            $html .= '<div class="row align-items-center">';
                                            $html .= '<div class="col-auto">' . $lang['sys.status'] . ': <span class="' . $color_status . '">'
                                                    . $lang['sys.' . $entity['status']] . '</span></div>';
                                            $html .= '</div>';
                                            $html .= '</div>';
                                        }
                                        echo $html;
                                    ?>                                  
                                </div>
                            </div>
                        </div>
                        <!-- Наборы свойств категории(с унаследованными) -->
                        <div class="tab-pane fade mt-3" id="property_sets-tab-pane" role="tabpanel" aria-labelledby="property_sets-tab">
                            <div class="row">
                                <div class="col">
                                    <?php
                                    $html = '';
                                    foreach ($categories_type_sets_data as $cat_name => $cats_set) {
                                        $html .= '<h5>' . $cat_name . '</h5>';
                                        foreach ($cats_set as $property_set) {
                                            $html .= '<div class="accordion my-3" id="accordion-' . $property_set['set_id'] . '">';
                                            $html .= '<div class="card">';
                                            $html .= '<div class="card-header" id="heading-' . $property_set['set_id'] . '">';
                                            $html .= '<h2 class="mb-0">';
                                            $html .= '<button class="btn btn-link" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-' . $property_set['set_id'] . '" aria-expanded="true" aria-controls="collapse-' . $property_set['set_id'] . '">';
                                            $html .= $property_set['name'];
                                            $html .= '</button>';
                                            $html .= '</h2>';
                                            $html .= '</div>';
                                            $html .= '<div id="collapse-' . $property_set['set_id'] . '" class="collapse" aria-labelledby="heading-' . $property_set['set_id'] . '" data-bs-parent="#accordion-' . $property_set['set_id'] . '">';
                                            $html .= '<div class="card-body">';
                                            $html .= '<h5>' . $lang['sys.description'] . '</h5>' . '<p>' . ($property_set['description'] ? $property_set['description'] : '---') . '</p>';
                                            $html .= '<h6>' . $lang['sys.properties'] . '</h6>';
                                            if (!count($property_set['properties'])) {
                                                $html .= '---';
                                            }
                                            foreach ($property_set['properties'] as $property) {
                                                $html .= $property . '<br/>';
                                            }
                                            $html .= '</div>';
                                            $html .= '</div>';
                                            $html .= '</div>';
                                            $html .= '</div>';
                                        }
                                    }
                                    echo $html;
                                    ?>                                    
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>            
        </div>		
    </form>
</main>