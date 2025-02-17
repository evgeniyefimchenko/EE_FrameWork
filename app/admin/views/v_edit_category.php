<?php
use classes\system\SysClass;
use classes\helpers\ClassNotifications;
use classes\system\Plugins;

if (!count($allType)) {
   ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Необходимо создать хотя бы один тип категории!', 'status' => 'info']);
   SysClass::handleRedirect(200, '/admin/types_categories');
}
$countPages = count($categoryPages);
?>
<!-- Редактирование категории -->
<main>    
    <form id="edit_category" action="/admin/category_edit/id/<?= $categoryData['category_id'] ?>" method="POST" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="fake" value="1" />
        <input type="hidden" id="count_pages" value="<?=$countPages?>" />
        <div class="container-fluid px-4">
            <a href="/admin/category_edit/id" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= $lang['sys.add'] ?>" type="button"
               class="btn btn-info mx-1 float-end<?= empty($categoryData['category_id']) ? " d-none" : "" ?>">
                <i class="fa fa-plus-circle"></i>&nbsp;<?= $lang['sys.add'] ?>
            </a>
            <button type="submit" class="btn btn-primary float-end"><?=$lang['sys.save']?></button>
            <h1 class="mt-4"><?= !$categoryData ? $lang['sys.add'] : $lang['sys.edit'] ?></h1>
            <ol class="breadcrumb mb-4">
                <li>
                    <span id="category_id" data-id="<?= $categoryData['category_id'] ?>">id = <?php echo !$categoryData['category_id'] ? $lang['sys.not_assigned'] : $categoryData['category_id'] ?></span>
                    <input type="hidden" name="category_id" class="form-control" value="<?= $categoryData['category_id'] ? $categoryData['category_id'] : 0 ?>">
                </li>              
            </ol>
            <div class="row">
                <div class="col">
                    <ul class="nav nav-tabs" id="eeTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic-tab-pane" type="button" role="tab" 
                                    aria-controls="basic-tab-pane" aria-selected="true"><?= $lang['sys.basics'] ?></button>
                        </li>
                        <?php if ($categoryData['category_id']) { ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link<?=(!$countPages ? ' text-danger' : '')?>" id="pages-tab" data-bs-toggle="tab" data-bs-target="#pages-tab-pane"
                                    type="button" role="tab" aria-controls="pages-tab-pane" aria-selected="false"><?= $lang['sys.category_pages'] ?></button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link<?=(!count($categoriesTypeSetsData) ? ' text-danger' : '')?>" id="property_sets-tab" data-bs-toggle="tab" data-bs-target="#property_sets-tab-pane"
                                    type="button" role="tab" aria-controls="property_sets-tab-pane" aria-selected="false"><?= $lang['sys.property_sets'] ?></button>
                        </li>
                        <?php } ?>
                    </ul>
                    <div class="tab-content" id="eeTabContent">
                        <!-- Основное содержимое -->
                        <div class="tab-pane show active mt-3" id="basic-tab-pane" role="tabpanel" aria-labelledby="basic-tab">
                            <div class="row mb-3">
                                <div class="col-6 col-sm-3">
                                    <label for="title-input"><?= $lang['sys.title'] ?>:</label>
                                    <div role="group" class="input-group">
                                        <input type="text" id="title-input" name="title" class="form-control" placeholder="Введите название..." value="<?= $categoryData['title'] ?>">
                                        <span role="button" class="input-group-text btn-info" data-bs-toggle="tooltip" data-bs-placement="top" title="Название должно быть уникально в рамках одного типа">
                                            <i class="fas fa-question-circle"></i><!-- Иконка со знаком вопроса -->
                                        </span>
                                    </div>
                                </div>
                                <div class="col-6 col-sm-3">
                                    <label for="type_id-input">Тип:</label>
                                    <div role="group" class="input-group">
                                        <select id="type_id-input" name="type_id" class="form-control">
                                            <?= Plugins::showTypeCategogyForSelect($allType, $categoryData['type_id']); ?>
                                        </select>
                                        <?php                                        
                                        if (isset($categoryData['parent_id'])) {
                                            $text_to_type = 'При наличии категории родителя можно выбрать только дочерний тип категории родителя или её.';
                                        } else {
                                            $text_to_type = 'Все наборы свойств типа будут унаследованы дочерними категориями и страницами.';
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
                                            <?php echo Plugins::showCategogyForSelect($categories_tree, $categoryData['parent_id']); ?>
                                        </select>
                                        <input type="hidden" id="oldParentId" value="<?=$categoryData['parent_id']?>">
                                        <span title="<?=$categoryData['category_path_text']?>" data-bs-toggle="tooltip" data-bs-placement="top" role="button"
                                              class="input-group-text btn-primary">
                                            <i class="fas fa-tree" data-bs-toggle="modal" data-bs-target="#parents_modal"></i><!-- Иконка со знаком вопроса -->
                                        </span>
                                    </div>
                                    <?= Plugins::ee_generateModal('parents_modal', $lang['sys.categories'], Plugins::renderCategoryTree($fullCategoriesTree))?>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-6 col-sm-3">
                                        <label for="status-input">Статус:</label>
                                            <?php $statuses = [['id' => 'active', 'name' => $lang['sys.active']], ['id' => 'disabled', 'name' => $lang['sys.blocked']], ['id' => 'hidden', 'name' => $lang['sys.not_confirmed']]] ?>
                                        <select required id="status-input" name="status" class="form-control">
                                            <?php foreach ($statuses as $item) { ?>
                                                <option <?=$categoryData['status'] == $item['id'] ? 'selected ' : ''?>value="<?= $item['id'] ?>"><?= $item['name'] ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-12 col-sm-12">
                                        <label for="short_description-input"><?=$lang['sys.short_description']?>:</label>
                                        <textarea id="short_description-input" name="short_description" class="form-control"><?= $categoryData['short_description'] ?></textarea>
                                    </div>
                                </div>                                
                                <div class="row mb-3">
                                    <div class="col-12 col-sm-12">
                                        <label for="description-input"><?=$lang['sys.description']?>:</label>
                                        <textarea id="description-input" name="description" class="form-control"><?= $categoryData['description'] ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-2">
                                    <label for="registration-date-input"><?=$lang['sys.date_create']?>:</label>
                                    <input type="text" disabled id="registration-date-input" class="form-control" value="<?= $categoryData['created_at'] ?>">
                                </div>
                                <div class="col-2">
                                    <label for="update-date-input"><?=$lang['sys.date_update']?>:</label>
                                    <input type="text" disabled id="update-date-input" class="form-control" value="<?= $categoryData['updated_at'] ?>">
                                </div>
                            </div>
                        </div>
                        <!-- Содержимое для присоединённых сущностей -->
                        <div class="tab-pane fade mt-3" id="pages-tab-pane" role="tabpanel" aria-labelledby="pages-tab">
                            <div class="row">
                                <div class="col">
                                    <?php
                                        $html = '';
                                        foreach ($categoryPages as $page) {
                                            $html .= '<div class="card p-1">';
                                            $color_status = 'text-success';
                                            if ($page['status'] != 'active') {
                                                $color_status = 'text-danger';
                                            }
                                            $html .= '<div class="row align-items-center">';
                                            $html .= '<div class="col-auto">№ ' . $page['page_id'] . '</div>';
                                            $html .= '<div class="col"><a href="/admin/page_edit/id/' . $page['page_id'] . '" target="_BLANK">' . $page['title'] . '</a></div>';
                                            $html .= '</div>';
                                            $html .= '<div class="row align-items-center">';
                                            $html .= '<div class="col-auto">' . $lang['sys.status'] . ': <span class="' . $color_status . '">'
                                                    . $lang['sys.' . $page['status']] . '</span></div>';
                                            $html .= '</div>';
                                            $html .= '</div>';
                                        }
                                        echo $html;
                                    ?>                                  
                                </div>
                            </div>
                        </div>
                        <!-- Наборы свойств категории -->
                        <div class="tab-pane fade mt-3" id="property_sets-tab-pane" role="tabpanel" aria-labelledby="property_sets-tab">
                            <div class="row">
                                <div class="col">
                                    <div id="renderPropertiesSetsAccordion">
                                        <?=Plugins::renderPropertiesSetsAccordion($categoriesTypeSetsData, $categoryData['category_id']);?>
                                    </div>
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