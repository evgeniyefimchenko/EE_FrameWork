<?php
if (!defined('ENV_SITE')) exit(header("Location: http://" . $_SERVER['HTTP_HOST'], true, 301));

// ==================================================================
// БЛОК 1: Рендеринг только таблицы (для AJAX-запросов)
// ==================================================================
if (empty($is_full_page_load)) {

    // Если флаг не установлен (или false), выводим только таблицу и выходим
    ?>
    <table class="table table-hover">
        <thead>
            <tr>
                <th><?= $lang['sys.category'] ?? 'Категория' ?></th>
                <th><?= $lang['sys.filters_count'] ?? 'Кол-во фильтров' ?></th>
                <th><?= $lang['sys.last_recalculation'] ?? 'Последний пересчет' ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($existingFilters)): ?>
                <?php foreach ($existingFilters as $filter): ?>
                    <tr>
                        <td><?= htmlspecialchars($filter['entity_name']) ?> (ID: <?= (int)$filter['entity_id'] ?>)</td>
                        <td>
                            <button type="button" class="btn btn-primary btn-sm view-filter-details" 
                                    data-bs-toggle="modal" data-bs-target="#filterDetailModal" 
                                    data-entity-id="<?= (int)$filter['entity_id'] ?>" 
                                    data-entity-name="<?= htmlspecialchars($filter['entity_name']) ?>">
                                <?= (int)$filter['filters_count'] ?>
                            </button>
                        </td>
                        <td><?= htmlspecialchars($filter['last_recalculation']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3" class="text-center"><?= $lang['sys.no_filters_generated'] ?? 'Фильтры еще не были сгенерированы.' ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
    return; // Важно: прекращаем выполнение скрипта здесь
}

// ==================================================================
// БЛОК 2: Рендеринг всей страницы (при обычной загрузке)
// ==================================================================
?>
<main>
    <div class="container-fluid px-4">
        
        <h1 class="mt-4"><?= $page_title ?? '' ?></h1>
        <ol class="breadcrumb mb-4">
            <li class="breadcrumb-item active"><?= $lang['sys.filters_management'] ?? 'Управление фильтрами' ?></li>
        </ol>

        <div class="row">
            <div class="col-lg-5">
                <div class="card card-primary card-outline mb-4">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-cogs"></i> <?= $lang['sys.recalculation_control'] ?? 'Управление пересчетом' ?></h3></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="category-select" class="form-label"><?= $lang['sys.select_root_category'] ?? 'Выберите корневую категорию' ?>:</label>
                            <select id="category-select" class="form-select">
                                <option value="0" selected="selected">-- <?= $lang['sys.not_selected'] ?? 'Не выбрано' ?> --</option>
                                <?php if (!empty($categories)): ?>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= (int)$category['category_id'] ?>">
                                            <?= htmlspecialchars($category['title']) ?> (ID: <?= (int)$category['category_id'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="mt-3">
                            <button id="regenerate-btn" class="btn btn-primary"><i class="fas fa-sync-alt"></i> <?= $lang['sys.recalculate_selected'] ?? 'Пересчитать для выбранной' ?></button>
                            <button id="regenerate-all-btn" class="btn btn-warning ms-2"><i class="fas fa-globe"></i> <?= $lang['sys.recalculate_all_root'] ?? 'Пересчитать для всех' ?></button>
                        </div>
                    </div>
                    <div class="card-footer">
                        <label for="log-output" class="form-label"><?= $lang['sys.execution_log'] ?? 'Лог выполнения' ?>:</label>
                        <pre id="log-output" class="bg-dark p-2 text-white rounded" style="min-height: 150px; font-family: 'Courier New', monospace; font-size: 0.9em;"></pre>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card card-success card-outline mb-4">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-tasks"></i> <?= $lang['sys.existing_filters'] ?? 'Существующие фильтры' ?></h3></div>
                    <div class="card-body p-0">
                        <div id="filters-table-container">
                            <?php // Здесь будет выведен HTML таблицы, который мы сгенерировали бы в БЛОКЕ 1 ?>
                            <?php $is_full_page_load = false; include __FILE__; $is_full_page_load = true; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<div class="modal fade" id="filterDetailModal" tabindex="-1" aria-labelledby="filterDetailModalLabel" aria-hidden="true"></div>