<?php if (!defined('ENV_SITE')) exit(header("Location: http://" . $_SERVER['HTTP_HOST'], true, 301)); ?>
<!-- Почтовые сниппеты -->
<main>
    <div class="container-fluid px-4">
        <a href="<?= ENV_URL_SITE ?>/admin/email_snippet_edit/id/" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= $lang['sys.add'] ?>"
           type="button" class="btn btn-info mx-1 float-end">
            <i class="fa fa-plus-circle"></i>&nbsp;<?= $lang['sys.add'] ?>
        </a>
        <h1 class="mt-4"><?= $lang['sys.snippets'] ?></h1>
        <div class="row">
            <div class="col">
                <?= $snippets_table ?>
            </div>
        </div>
    </div>
</main>
