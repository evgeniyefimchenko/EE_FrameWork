<?php if (!defined('ENV_SITE')) exit(header("Location: http://" . $_SERVER['HTTP_HOST'], true, 301)); ?>
<main>
    <form id="edit_email_snippet" action="<?= ENV_URL_SITE ?>/admin/email_snippet_edit<?= !empty($snippetData['snippet_id']) ? '/id/' . $snippetData['snippet_id'] : '' ?>" method="POST">
        <div class="container-fluid px-4">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="mt-4"><?= empty($snippetData['snippet_id']) ? $lang['sys.add'] : $lang['sys.edit'] ?></h1>
                <div>
                    <button type="submit" class="btn btn-primary mx-1"><?= $lang['sys.save'] ?></button>
                    <a href="<?= ENV_URL_SITE ?>/admin/email_snippets" class="btn btn-secondary"><?= $lang['sys.cancel'] ?></a>
                </div>
            </div>

            <ol class="breadcrumb mb-4">
                <li>
                    <span id="snippet_id" data-id="<?= $snippetData['snippet_id'] ?>">id = <?= empty($snippetData['snippet_id']) ? $lang['sys.not_assigned'] : $snippetData['snippet_id'] ?></span>
                    <input type="hidden" name="snippet_id" class="form-control" value="<?= !empty($snippetData['snippet_id']) ? $snippetData['snippet_id'] : 0 ?>">
                </li>
            </ol>

            <div class="row mb-3">
                <div class="col-sm-6">
                    <label for="snippet_name"><?= $lang['sys.name'] ?>:</label>
                    <input type="text" id="snippet_name" name="name" class="form-control" required value="<?= $snippetData['name'] ?? '' ?>">
                </div>
                <div class="col-sm-6">
                    <label for="snippet_description"><?= $lang['sys.description'] ?>:</label>
                    <textarea id="snippet_description" name="description" class="form-control"><?= $snippetData['description'] ?? '' ?></textarea>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-12">
                    <label for="snippet_content"><?= $lang['sys.content'] ?>:</label>
                    <textarea id="snippet_content" name="content" class="form-control"><?= $snippetData['content'] ?? '' ?></textarea>
                </div>
            </div>

            <!-- Блок с переменными -->
            <div class="row mb-3">
                <div class="col-12">
                    <label><?= $lang['sys.vars'] ?>:</label>
                    <?php foreach ($codeVars as $name => $value): ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary insert-snippet m-1" data-snippet="{{<?= $name ?>}}" title="<?= $value ?>" data-bs-toggle="tooltip" data-bs-placement="bottom">
                            <?= $name ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Блок с сниппетами -->
            <div class="row mb-3">
                <div class="col-12">
                    <label><?= $lang['sys.snippets'] ?>:</label>
                    <?php foreach ($codeSnippet as $name => $content): ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary insert-snippet m-1" data-snippet="{{<?= $name ?>}}" title="<?= $content ?>" data-bs-toggle="tooltip" data-bs-placement="bottom">
                            <?= $name ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </form>
</main>
