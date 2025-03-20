<?php if (!defined('ENV_SITE')) exit(header("Location: http://" . $_SERVER['HTTP_HOST'], true, 301)); ?>
<!-- Страница просмотра сообщений -->
<main>
    <div class="container-fluid px-4">
        <a href="/admin/kill_all_message" onclick="return confirm('<?= $lang['sys.delete_all'] ?>');" data-bs-toggle="tooltip" data-bs-placement="top"
           title="<?= $lang['sys.delete_all'] ?>" type="button"
           class="btn btn-danger mx-1 float-end">
            <i class="fa-solid fa-trash-arrow-up"></i>&nbsp;<?= $lang['sys.delete_all'] ?>
        </a>
        <a href="/admin/read_all_message" onclick="return confirm('<?= $lang['sys.mark_everything_as_read'] ?>');" data-bs-toggle="tooltip"
           data-bs-placement="top" title="<?= $lang['sys.mark_everything_as_read'] ?>" type="button"
           class="btn btn-info mx-1 float-end">
            <i class="fa-solid fa-check-double"></i>&nbsp;<?= $lang['sys.mark_everything_as_read'] ?>
        </a>    
        <h1 class="mt-4"><?= $lang['sys.messages'] ?></h1>
        <div class="row">
            <div class="col">
                <?= $messages_table ?>
            </div>
        </div>
    </div>
</main>