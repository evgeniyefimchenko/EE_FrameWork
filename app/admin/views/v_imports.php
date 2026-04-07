<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<main>
    <div class="container-fluid px-4">
        <a href="/admin/edit_import_wp/id/0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= htmlspecialchars((string) ($lang['sys.imports_new_profile_wp'] ?? 'Create profile')) ?>" type="button"
           class="btn btn-info mx-1 float-end">
            <i class="fa fa-plus-circle"></i>&nbsp;<?= htmlspecialchars((string) ($lang['sys.imports_new_profile_wp'] ?? 'Create profile')) ?>
        </a>

        <h1 class="mt-4"><?= htmlspecialchars((string) ($lang['sys.imports_profiles_list'] ?? 'Import profiles')) ?></h1>

        <div class="row">
            <div class="col">
                <?php if (empty($import_jobs)): ?>
                    <div class="alert alert-info"><?= htmlspecialchars((string) ($lang['sys.imports_no_profiles'] ?? 'No import profiles found yet.')) ?></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle">
                            <thead>
                            <tr>
                                <th style="width:70px;">ID</th>
                                <th><?= htmlspecialchars((string) ($lang['sys.profile'] ?? 'Profile')) ?></th>
                                <th><?= htmlspecialchars((string) ($lang['sys.package'] ?? 'Package')) ?></th>
                                <th style="width:220px;"><?= htmlspecialchars((string)($lang['sys.cron_agents'] ?? 'Cron-агенты')) ?></th>
                                <th style="width:180px;"><?= htmlspecialchars((string) ($lang['sys.imports_last_run'] ?? 'Last run')) ?></th>
                                <th style="width:230px;"><?= htmlspecialchars((string) ($lang['sys.actions'] ?? 'Actions')) ?></th>
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
                                $cronAgent = is_array($job['cron_agent'] ?? null) ? $job['cron_agent'] : null;
                                ?>
                                <tr>
                                    <td><?= (int)$job['id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars((string)($job['settings_name'] ?? '')) ?></strong><br>
                                        <span class="text-muted small"><?= htmlspecialchars((string) ($lang['sys.import_format'] ?? 'Format')) ?>: <?= htmlspecialchars((string)($settings['package_format'] ?? ($lang['sys.not_specified'] ?? 'not specified'))) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($packageName !== ''): ?>
                                            <i class="fa fa-file-archive"></i> <?= htmlspecialchars($packageName) ?>
                                        <?php else: ?>
                                            <span class="text-muted"><?= htmlspecialchars((string) ($lang['sys.not_uploaded'] ?? 'Not uploaded')) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($cronAgent): ?>
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <span class="badge <?= htmlspecialchars((string) ($cronAgent['runtime_status_class'] ?? 'bg-secondary')) ?>"><?= htmlspecialchars((string) ($cronAgent['runtime_status_label'] ?? '')) ?></span>
                                                <a href="/admin/cron_agent_edit/id/<?= (int) ($cronAgent['agent_id'] ?? 0) ?>" class="small text-decoration-none"><?= htmlspecialchars((string) ($cronAgent['code'] ?? '')) ?></a>
                                            </div>
                                            <div class="small text-muted mt-1"><?= htmlspecialchars((string) ($cronAgent['schedule_human'] ?? '')) ?></div>
                                        <?php else: ?>
                                            <span class="text-muted"><?= htmlspecialchars((string)($lang['sys.imports_cron_agent_missing'] ?? 'No linked cron agent has been created yet.')) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($job['last_run_at'])): ?>
                                            <?= htmlspecialchars(ee_format_utc_datetime((string) $job['last_run_at'], 'd.m.Y H:i')) ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="/admin/edit_import_wp/id/<?= (int)$job['id'] ?>" class="btn btn-sm btn-primary">
                                            <?= htmlspecialchars((string) ($lang['sys.open'] ?? 'Open')) ?>
                                        </a>
                                        <a href="/admin/sync_import_cron_agent/id/<?= (int)$job['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                            <?= htmlspecialchars((string)($lang['sys.imports_cron_agent_sync'] ?? 'Sync cron agent')) ?>
                                        </a>
                                        <a href="<?= htmlspecialchars(\classes\system\CsrfService::appendToUrl('/admin/delete_import_profile/id/' . (int)$job['id']), ENT_QUOTES, 'UTF-8') ?>"
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('<?= htmlspecialchars((string) ($lang['sys.imports_delete_confirm_short'] ?? 'Delete this profile?'), ENT_QUOTES, 'UTF-8') ?>');">
                                            <?= htmlspecialchars((string) ($lang['sys.delete'] ?? 'Delete')) ?>
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
