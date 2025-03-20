<?php if (!defined('ENV_SITE')) exit(header("Location: http://" . $_SERVER['HTTP_HOST'], true, 301)); ?>
<!-- Редактирование роли пользователей -->
<main>
    <form  id="edit_users_role" action="/admin/users_role_edit/id" method="POST">
        <input type="hidden" name="fake" value="1" />
        <input type="hidden" name="role_id" value="<?=$users_role_data['role_id']?>" />
        <div class="container-fluid px-4">
            <h1 class="mt-4"><?= $users_role_data['role_id'] ? $lang['sys.add'] : $lang['sys.edit'] ?></h1>
            <ol class="breadcrumb mb-4">
                <li class="breadcrumb-item active">
                    <span <?= $userData['user_role'] > 2 ? 'style="display:none;"' : '' ?> id="role_id" data-id="<?= $users_role_data['role_id'] ?>">
                        id = <?php echo !$users_role_data['role_id'] ? 'Не присвоен' : $users_role_data['role_id'] ?></span>
                </li>
            </ol>
            <div class="row">
                <div class="col-md-4">
                    <label for="name-input"><?= $lang['sys.name'] ?>:</label>
                    <input type="text" id="name-input" name="name" class="form-control" value="<?= $users_role_data['name'] ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primary my-3">Сохранить</button>
        </div>		
    </form>
</main>