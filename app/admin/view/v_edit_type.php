<?php
if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}
?>
<!-- Редактирование типа категории -->
<main>
    <form id="type_edit" action="/admin/type_edit/id/<?= $type_data['type_id'] ?>" method="POST">
        <input type="hidden" name="fake" value="1" />
        <div class="container-fluid px-4">
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
                            <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic-tab-pane" type="button" role="tab" aria-controls="basic-tab-pane" aria-selected="true">Основное</button>
                        </li>
                    </ul>
                    <div class="tab-content" id="eeTabContent">
                        <!-- Основное содержимое -->
                        <div class="tab-pane fade show active mt-3" id="basic-tab-pane" role="tabpanel" aria-labelledby="basic-tab">
                            <div class="row mb-3">
                                <div class="col">
                                    <label for="name-input">Название:</label>
                                    <input required type="text" id="name-input" name="name" class="form-control" placeholder="Введите название..." value="<?= $type_data['name'] ?>">
                                </div>
                                <div class="row mb-3">
                                    <div class="col">
                                        <label for="description-input">Описание:</label>
                                        <textarea required id="description-input" name="description" class="form-control"><?= $type_data['description'] ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col">
                                    <label for="registration-date-input">Дата регистрации:</label>
                                    <input type="text" disabled  class="form-control" value="<?= $type_data['created_at'] ?>">
                                </div>
                                <div class="col">
                                    <label for="update-date-input">Дата обновления:</label>
                                    <input type="text" disabled class="form-control" value="<?= $type_data['updated_at'] ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Сохранить</button>
        </div>		
    </form>
</main>