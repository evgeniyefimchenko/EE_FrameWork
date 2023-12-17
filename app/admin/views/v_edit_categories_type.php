<?php
use classes\system\SysClass;
use classes\helpers\ClassNotifications;
use classes\system\Plugins;
?>
<!-- Редактирование типа категории -->
<main>
    <form id="type_edit" action="/admin/categories_type_edit/id/<?= $type_data['type_id'] ?>" method="POST">
        <input type="hidden" name="fake" value="1" />
        <div class="container-fluid px-4">
            <a href="/admin/categories_type_edit/id" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= $lang['sys.add'] ?>" type="button"
               class="btn btn-info mx-1 float-end<?= empty($type_data['type_id']) ? " d-none" : "" ?>">
                <i class="fa fa-plus-circle"></i>&nbsp;<?= $lang['sys.add'] ?>
            </a>            
            <h1 class="mt-4"><?= !$type_data ? 'Добавить тип категории' : 'Редактировать тип категории' ?></h1>
            <ol class="breadcrumb mb-4">
                <li class="breadcrumb-item active">
                    <span id="type_id" data-id="<?= $type_data['type_id'] ?>">id = <?php echo !$type_data ? 'Не присвоен' : $type_data['type_id'] ?></span>
                    <input type="hidden" name="type_id" class="form-control" value="<?= $type_data['type_id'] ? $type_data['type_id'] : 0 ?>">
                </li>
            </ol>
            <div class="row">
                <div class="col">
                    <ul class="nav nav-tabs" id="eeTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic-tab-pane" type="button" role="tab" aria-controls="basic-tab-pane" aria-selected="true"><?=$lang['sys.basics']?></button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="features-tab" data-bs-toggle="tab" data-bs-target="#features-tab-pane" type="button" role="tab" aria-controls="features-tab-pane"><?=$lang['sys.property_sets']?></button>
                        </li>                        
                    </ul>
                    <div class="tab-content" id="eeTabContent">
                        <!-- Основное содержимое -->
                        <div class="tab-pane fade show active mt-3" id="basic-tab-pane" role="tabpanel" aria-labelledby="basic-tab">
                            <div class="row mb-3">
                                <div class="col-6">
                                    <label for="name-input"><?=$lang['sys.title']?>:</label>
                                    <input required type="text" id="name-input" name="name" class="form-control" placeholder="Введите название..." value="<?= $type_data['name'] ?>">
                                </div>
                                <div class="col-3">
                                    <label for="type_id-input"><?=$lang['sys.parent']?></label>
                                    <div role="group" class="input-group">
                                        <select id="type_id-input" name="parent_type_id" class="form-control">
                                            <?=Plugins::show_type_categogy_for_select($all_type, $type_data['parent_type_id']); ?>
                                        </select>                                                                              
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col">
                                        <label for="description-input"><?=$lang['sys.description']?>:</label>
                                        <textarea required id="description-input" name="description" class="form-control"><?= $type_data['description'] ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-3">
                                    <label for="registration-date-input"><?=$lang['sys.date_create']?>:</label>
                                    <input type="text" disabled  class="form-control" value="<?= $type_data['created_at'] ?>">
                                </div>
                                <div class="col-3">
                                    <label for="update-date-input"><?=$lang['sys.date_update']?>:</label>
                                    <input type="text" disabled class="form-control" value="<?= $type_data['updated_at'] ?>">
                                </div>
                            </div>
                        </div>
                        <!-- Наборы свойств -->
                        <div class="tab-pane fade show mt-3" id="features-tab-pane" role="tabpanel" aria-labelledby="features-tab">
                            <?php
                            $html = '';
                            foreach ($property_sets_data['data'] as $property_set) {
                                $html .= '<div class="accordion my-3" id="accordion-' . $property_set['set_id'] . '">';
                                $html .= '<div class="card">';
                                $html .= '<div class="card-header" id="heading-' . $property_set['set_id'] . '">';
                                $html .= '<h2 class="mb-0">';
                                $html .= '<input type="checkbox" id="checkbox-' . $property_set['set_id'] . '" name="property_set[]"'
                                        . 'value="' . $property_set['set_id'] . '" class="form-check-input me-2"'
                                        . (in_array($property_set['set_id'], $categories_type_sets_data) ? "checked" : "") . '>';
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
                            echo $html;
                            ?>
                        </div>                        
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Сохранить</button>
        </div>		
    </form>
</main>