<!-- Редактирование почтового шаблона -->
<main>
    <form id="edit_email_template" action="/admin/edit_email_template/id/<?= $templateData['template_id'] ?>" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="fake" value="1" />
        <div class="container-fluid px-4">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="mt-4"><?= !$templateData['template_id'] ? $lang['sys.add_email_template'] : $lang['sys.edit_email_template'] ?></h1>
                <div>
                    <button type="submit" class="btn btn-primary mx-1"><?=$lang['sys.save']?></button>
                </div>
            </div>

            <ol class="breadcrumb mb-4">
                <li>
                    <span id="template_id" data-id="<?= $templateData['template_id'] ?>">id = <?php echo !$templateData['template_id'] ? $lang['sys.not_assigned'] : $templateData['template_id'] ?></span>
                    <input type="hidden" name="template_id" class="form-control" value="<?= !empty($templateData['template_id']) ? $templateData['template_id'] : 0 ?>">
                </li>
            </ol>

            <div class="row">
                <div class="col">
                    <ul class="nav nav-tabs" id="eeTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic-tab-pane" type="button" role="tab"
                                    aria-controls="basic-tab-pane" aria-selected="true"><?= $lang['sys.basics'] ?></button>
                        </li>
                    </ul>
                    <div class="tab-content" id="eeTabContent">
                        <!-- Основное содержимое -->
                        <div class="tab-pane show active mt-3" id="basic-tab-pane" role="tabpanel" aria-labelledby="basic-tab">
                            <div class="row mb-3">
                                <div class="col-sm-6">
                                    <label for="name-input"><?= $lang['sys.name'] ?>:</label>
                                    <div role="group" class="input-group">
                                        <input type="text" id="name-input" name="name" class="form-control" placeholder="<?= $lang['sys.enter_name'] ?>" value="<?= $templateData['name'] ?>">
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <label for="subject-input"><?= $lang['sys.subject'] ?>:</label>
                                    <div role="group" class="input-group">
                                        <input type="text" id="subject-input" name="subject" class="form-control" placeholder="<?= $lang['sys.enter_subject'] ?>" value="<?= $templateData['subject'] ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-12">
                                    <label for="description-input"><?= $lang['sys.description'] ?>:</label>
                                    <textarea id="description-input" name="description" class="form-control" placeholder="<?= $lang['sys.enter_description'] ?>"><?= $templateData['description'] ?></textarea>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-12">
                                    <label><?= $lang['sys.snippets'] ?>:</label>
                                    <?php foreach($codeSnippet as $name => $content) { ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary insert-snippet m-1" data-snippet="{{<?= $name ?>}}"
                                                title="<?= $content ?>" data-bs-toggle="tooltip" data-bs-placement="bottom">
                                            <?= $name ?>
                                        </button>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-12">
                                    <label><?= $lang['sys.vars'] ?>:</label>
                                    <?php foreach($codeVars as $name => $content) { ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary insert-snippet m-1" data-snippet="{{<?= $name ?>}}"
                                                title="<?= $content ?>" data-bs-toggle="tooltip" data-bs-placement="bottom">
                                            <?= $name ?>
                                        </button>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-12">
                                    <label for="body-input"><?= $lang['sys.body'] ?>:</label>
                                    <textarea id="body-input" name="body" class="form-control ee_editor" rows="10" placeholder="<?= $lang['sys.enter_body'] ?>"><?= $templateData['body'] ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="text-center">
                <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#previewModal">
                    <i class="fas fa-eye"></i>&nbsp;<?= $lang['sys.preview'] ?>
                </button>
                <br>
            </div>
            <div class="row mt-3">
                <div class="col-6">
                    <div class="input-group mb-3">
                        <input type="email" id="testEmail" class="form-control" placeholder="<?= $lang['sys.test_email'] ?>">
                        <button type="button" class="btn btn-info" id="send_test_email">
                            <i class="fas fa-envelope"></i>&nbsp;<?= $lang['sys.send_email'] ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
    <!-- Модальное окно для предварительного просмотра -->
    <div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="previewModalLabel"><?= $lang['sys.preview'] ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?= $emailBodyWithSnippets ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $lang['sys.close'] ?></button>
                </div>
            </div>
        </div>
    </div>
</main>
