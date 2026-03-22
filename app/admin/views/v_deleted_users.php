<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<!-- Таблица удалённых пользователей -->
<main>
    <div class="container-fluid px-4">        
        <h1 class="mt-4"><?= $lang['sys.deleted_users'] ?></h1>
        <div class="row">
            <div class="col">
                <?= $deleted_users_table ?>
            </div>
        </div>
    </div>
</main>
