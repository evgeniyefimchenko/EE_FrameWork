<?php if (!defined('ENV_SITE')) exit(header("Location: http://" . $_SERVER['HTTP_HOST'], true, 301)); ?>
<!-- Таблица ролей пользователей -->
<main>
    <div class="container-fluid px-4">
        <a href="/admin/users_role_edit/id" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= $lang['sys.add'] ?>" type="button"
           class="btn btn-info mx-1 float-end">
            <i class="fa fa-plus-circle"></i>&nbsp;<?= $lang['sys.add'] ?>
        </a>        
        <h1 class="mt-4"><?= $lang['sys.registered_users'] ?></h1>
        <div class="row">
            <div class="col">
                <?= $users_roles_table?>
            </div>
        </div>
    </div>
</main>
