<?php if (!defined('ENV_SITE')) exit(header("Location: http://" . $_SERVER['HTTP_HOST'], true, 301)); ?>
<!-- Страница просмотра логов -->
<div class="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><?= $lang['sys.logs'] ?></h4>
                    </div>                    
                    <div class="card-body">
                        <!-- Tab links -->
                        <ul class="nav nav-tabs" id="eeTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="php_log_logs-tab" data-bs-toggle="tab" data-bs-target="#php_log_logs" role="tab" aria-controls="php_log_logs" aria-selected="true">PHP logs</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="fatal_errors-tab" data-bs-toggle="tab" data-bs-target="#fatal_errors" role="tab" aria-controls="fatal_errors" aria-selected="false">Fatal logs</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="progect_logs-tab" data-bs-toggle="tab" data-bs-target="#progect_logs" role="tab" aria-controls="progect_logs" aria-selected="false">Progect logs</button>
                            </li>
                        </ul>
                        <!-- Tab content -->
                        <div class="tab-content" id="eeTabContent">
                            <!-- php_log_logs -->
                            <div id="php_log_logs" class="tab-pane show active mt-3" role="tabpanel" aria-labelledby="php_log_logs-tab">
                                <a href="/admin/clear_php_logs" onclick="return confirm('<?= $lang['sys.clear_file'] ?>');" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= $lang['sys.clear_file'] ?>" class="btn btn-info mx-1 float-end">
                                    <i class="fa-solid fa-trash"></i>&nbsp;<?= $lang['sys.clear'] ?>
                                </a>
                                <?=$php_logs_table ?>
                            </div>
                            <!-- fatal_errors -->
                            <div id="fatal_errors" class="tab-pane fade mt-3" role="tabpanel" aria-labelledby="fatal_errors-tab">
                                <?= $fatal_errors_table ?>
                            </div>
                            <!-- progect_logs -->
                            <div id="progect_logs" class="tab-pane fade mt-3" role="tabpanel" aria-labelledby="progect_logs-tab">
                                <?= $progect_logs_table ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
