<?php
use classes\system\SysClass;
use classes\system\Plugins;
?>
<!-- Редактирование страницы -->
<?php if (!$allType) SysClass::handleRedirect(200, '/admin/type_categories');?> 
<main>    
    <form id="edit_page" action="/admin/page_edit/id/<?= $pageData['page_id'] ?>" method="POST" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="fake" value="1" />
        <div class="container-fluid px-4">
            <a href="/admin/page_edit/id" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= $lang['sys.add'] ?>" type="button"
               class="btn btn-info mx-1 float-end<?= empty($pageData['page_id']) ? " d-none" : "" ?>">
                <i class="fa fa-plus-circle"></i>&nbsp;<?= $lang['sys.add'] ?>
            </a>
            <button type="submit" class="btn btn-primary float-end"><?=$lang['sys.save']?></button>
            <h1 class="mt-4"><?= !$pageData ? $lang['sys.add'] : $lang['sys.edit'] ?></h1>
            <ol class="breadcrumb mb-4">
                <li>
                    <span id="page_id" data-id="<?= $pageData['page_id'] ?>">id = <?php echo !$pageData['page_id'] ? $lang['sys.not_assigned'] : $pageData['page_id'] ?>
                    </span>
                    <input type="hidden" name="page_id" class="form-control" value="<?= $pageData['page_id'] ? $pageData['page_id'] : 0 ?>">
                </li>              
            </ol>
            <div class="row">
                <div class="col">
                    <ul class="nav nav-tabs" id="ee_Tab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic-tab-pane" type="button" role="tab"
                                    aria-controls="basic-tab-pane" aria-selected="true"><?=$lang['sys.basics']?></button>
                        </li>
                        <?php if ($pageData['page_id']) { ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="features-tab" data-bs-toggle="tab" data-bs-target="#features-tab-pane" type="button" role="tab"
                                    aria-controls="features-tab-pane"><?=$lang['sys.properties']?></button>
                        </li>
                        <?php } ?>
                    </ul>
                    <div class="tab-content" id="ee_TabContent">
                        <!-- Основное содержимое -->
                        <div class="tab-pane fade show active mt-3" id="basic-tab-pane" role="tabpanel" aria-labelledby="basic-tab">
                            <div class="row mb-3">
                                <div class="col-6 col-sm-3">
                                    <label for="title-input"><?= $lang['sys.title'] ?>:</label>                                    
                                    <input type="text" id="title-input" name="title" class="form-control" placeholder="<?=$lang['sys.enter_title']?>..." value="<?= $pageData['title'] ?>">                                    
                                </div>
                                <div class="col-6 col-sm-3">
                                    <label for="category_id-input"><?= $lang['sys.category'] . ' ' . $lang['sys.parent'] ?>:</label>
                                    <div role="group" class="input-group">
                                        <select required <?=$pageData['parent_page_id'] ? "readonly " : ""?>type="text" id="category_id-input"
                                                name="category_id" class="form-control">
                                            <?php echo !$pageData['parent_page_id'] ? Plugins::showCategogyForSelect($allCategories, $pageData['category_id']) :
                                                '<option value="' . $pageData['category_id'] . '">' . $pageData['category_title'] . '</option>'?>
                                        </select>
                                        <?php if (!$pageData['parent_page_id']) { ?>
                                        <span title="<?=$lang['sys.separate_window']?>" data-bs-toggle="tooltip"
                                              data-bs-placement="top" role="button" class="input-group-text btn-primary">
                                            <i class="fas fa-tree" data-bs-toggle="modal" data-bs-target="#categories_modal"></i><!-- Иконка со знаком вопроса -->
                                        </span>
                                            <?= Plugins::ee_generateModal('categories_modal', $lang['sys.categories'], Plugins::renderCategoryTree($allCategories))?>
                                        <?php } ?>
                                    </div>
                                </div>
                                <div class="col-6 col-sm-3">
                                    <label for="parent_page_id-input"><?= $lang['sys.page'] . ' ' . $lang['sys.parent'] ?>:</label>
                                    <div role="group" class="input-group">
                                        <select type="text" id="parent_page_id-input" name="parent_page_id" class="form-control">
                                            <option value="0"><?=$lang['sys.no']?></option>
                                            <?php foreach ($allPages as $item) {
                                                echo '<option ' . ($pageData['parent_page_id'] == $item['page_id'] ? "selected " : "") . 'value="' . $item['page_id'] . '">' . $item['title'] . '</option>';
                                            } ?>
                                        </select>
                                        <span role="button" class="input-group-text btn-primary" data-bs-toggle="tooltip" data-bs-placement="top" title="<?=$lang['sys.separate_window']?>">
                                            <i class="fas fa-tree"></i><!-- Иконка со знаком вопроса -->
                                        </span>                                        
                                    </div>
                                </div>
                                <div class="col-6 col-sm-3">
                                    <label for="type_id-input"><?= $lang['sys.type'] ?>:</label>
                                    <div role="group" class="input-group">
                                        <input type="text" disabled id="type_id-input" name="type_id" class="form-control" placeholder="Введите название..." value="<?= $pageData['type_name'] ?>">
                                        <span role="button" class="input-group-text btn-primary" data-bs-toggle="tooltip" data-bs-placement="top" title="Определяется из категории">
                                            <i class="fas fa-question-circle"></i><!-- Иконка со знаком вопроса -->
                                        </span>
                                    </div>
                                </div>                                
                                <div class="row mb-3">
                                    <div class="col-6 col-sm-3">
                                        <label for="status-input">Статус:</label>                                            
                                        <select required id="status-input" name="status" class="form-control">
                                            <?php foreach ($allStatus as $key => $value) { ?>
                                                <option <?=($pageData['status'] == $key ? 'selected ' : '')?>value="<?= $key ?>"><?= $value ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-12 col-sm-12">
                                        <label for="short_description-input"><?= $lang['sys.short_description'] ?>:</label>
                                        <textarea id="short_description-input" name="short_description" class="form-control"><?= $pageData['short_description'] ?></textarea>
                                    </div>
                                </div>                                
                                <div class="row mb-3">
                                    <div class="col-12 col-sm-12">
                                        <label for="description-input"><?= $lang['sys.description'] ?>:</label>
                                        <textarea id="description-input" name="description" class="form-control "><?= $pageData['description'] ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-2">
                                    <label><?= $lang['sys.date_create'] ?>:</label>
                                    <input type="text" disabled  class="form-control" value="<?= $pageData['created_at'] ?>">
                                </div>
                                <div class="col-2">
                                    <label><?= $lang['sys.date_update'] ?>:</label>
                                    <input type="text" disabled class="form-control" value="<?= $pageData['updated_at'] ?>">
                                </div>
                            </div>
                        </div>
                        <!-- Свойства -->
                        <div class="tab-pane fade show mt-3" id="features-tab-pane" role="tabpanel" aria-labelledby="features-tab">
                            <div class="row">
                                <div class="col">
                                    <div id="renderPropertiesSetsAccordion">
                                        <?=Plugins::renderPropertiesSetsAccordion($allProperties, $pageData['page_id'], 'page')?>
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