<!-- Таблица удалённых пользователей -->
<main>
    <div class="container-fluid px-4">        
        <h1 class="mt-4"><?= $lang['sys.deleted_users'] ?></h1>
        <div class="row">
            <div class="col">
                <?php var_export($deleted_users_table); ?>
            </div>
        </div>
    </div>
</main>
