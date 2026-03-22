<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<main>
    <div class="container-fluid px-4">
        <a href="/admin/edit_import_wp/id/0" data-bs-toggle="tooltip" data-bs-placement="top" title="Создать профиль" type="button"
           class="btn btn-info mx-1 float-end">
            <i class="fa fa-plus-circle"></i>&nbsp;Создать профиль
        </a>

        <h1 class="mt-4">Профили импорта</h1>

        <div class="row">
            <div class="col">
                <?php if (empty($import_jobs)): ?>
                    <div class="alert alert-info">Сохранённых профилей пока нет.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle">
                            <thead>
                            <tr>
                                <th style="width:70px;">ID</th>
                                <th>Профиль</th>
                                <th>Пакет</th>
                                <th style="width:180px;">Последний запуск</th>
                                <th style="width:230px;">Действия</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($import_jobs as $job): ?>
                                <?php
                                $settings = json_decode((string)($job['settings_json'] ?? ''), true);
                                if (!is_array($settings)) {
                                    $settings = [];
                                }
                                $packageName = trim((string)($settings['package_filename'] ?? ''));
                                ?>
                                <tr>
                                    <td><?= (int)$job['id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars((string)($job['settings_name'] ?? '')) ?></strong><br>
                                        <span class="text-muted small">Формат: <?= htmlspecialchars((string)($settings['package_format'] ?? 'не указан')) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($packageName !== ''): ?>
                                            <i class="fa fa-file-archive"></i> <?= htmlspecialchars($packageName) ?>
                                        <?php else: ?>
                                            <span class="text-muted">Не загружен</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($job['last_run_at'])): ?>
                                            <?= date('d.m.Y H:i', strtotime((string)$job['last_run_at'])) ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="/admin/edit_import_wp/id/<?= (int)$job['id'] ?>" class="btn btn-sm btn-primary">
                                            Открыть
                                        </a>
                                        <a href="/admin/delete_import_profile/id/<?= (int)$job['id'] ?>"
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Удалить этот профиль?');">
                                            Удалить
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
