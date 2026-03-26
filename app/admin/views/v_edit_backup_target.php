<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<?php
$target = is_array($backup_target ?? null) ? $backup_target : [];
$isNew = (int) ($target['target_id'] ?? 0) <= 0;
?>
<main>
    <div class="container-fluid px-4">
        <div class="d-flex align-items-center justify-content-between mt-4 mb-3 flex-wrap gap-2">
            <h1 class="mb-0"><?= htmlspecialchars((string)($isNew ? ($lang['sys.backup_target_new'] ?? 'Новый профиль удалённого хранилища') : ($lang['sys.backup_target_edit'] ?? 'Редактирование удалённого хранилища'))) ?></h1>
            <a href="/admin/backup" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left"></i>&nbsp;<?= htmlspecialchars((string)($lang['sys.backup'] ?? 'Резервное копирование')) ?>
            </a>
        </div>

        <form method="post" class="card shadow-sm border">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-lg-4">
                        <label class="form-label"><?= htmlspecialchars((string)($lang['sys.backup_target_code'] ?? 'Код профиля')) ?></label>
                        <input type="text" class="form-control" name="code" value="<?= htmlspecialchars((string)($target['code'] ?? '')) ?>" placeholder="main-sftp" required>
                    </div>
                    <div class="col-12 col-lg-5">
                        <label class="form-label"><?= htmlspecialchars((string)($lang['sys.name'] ?? 'Название')) ?></label>
                        <input type="text" class="form-control" name="name" value="<?= htmlspecialchars((string)($target['name'] ?? '')) ?>" placeholder="Основной offsite backup" required>
                    </div>
                    <div class="col-12 col-lg-3">
                        <label class="form-label"><?= htmlspecialchars((string)($lang['sys.type'] ?? 'Тип')) ?></label>
                        <select class="form-select" name="protocol" id="backup-target-protocol">
                            <option value="sftp" <?= (string)($target['protocol'] ?? 'sftp') === 'sftp' ? 'selected' : '' ?>>SFTP</option>
                            <option value="ftp" <?= (string)($target['protocol'] ?? '') === 'ftp' ? 'selected' : '' ?>>FTP</option>
                        </select>
                    </div>

                    <div class="col-12 col-lg-4">
                        <label class="form-label"><?= htmlspecialchars((string)($lang['sys.host'] ?? 'Хост')) ?></label>
                        <input type="text" class="form-control" name="host" value="<?= htmlspecialchars((string)($target['host'] ?? '')) ?>" placeholder="backup.example.com" required>
                    </div>
                    <div class="col-12 col-lg-2">
                        <label class="form-label"><?= htmlspecialchars((string)($lang['sys.port'] ?? 'Порт')) ?></label>
                        <input type="number" min="1" max="65535" class="form-control" id="backup-target-port" name="port" value="<?= (int)($target['port'] ?? 22) ?>">
                    </div>
                    <div class="col-12 col-lg-3">
                        <label class="form-label"><?= htmlspecialchars((string)($lang['sys.login'] ?? 'Логин')) ?></label>
                        <input type="text" class="form-control" name="username" value="<?= htmlspecialchars((string)($target['username'] ?? '')) ?>" required>
                    </div>
                    <div class="col-12 col-lg-3">
                        <label class="form-label"><?= htmlspecialchars((string)($lang['sys.password'] ?? 'Пароль')) ?></label>
                        <input type="password" class="form-control" name="password" value="" autocomplete="new-password" placeholder="<?= htmlspecialchars((string) (($target['password_mask'] ?? '') !== '' ? ($lang['sys.backup_target_password_keep'] ?? 'Оставьте пустым, чтобы сохранить текущий') : '')) ?>">
                    </div>

                    <div class="col-12 col-lg-8">
                        <label class="form-label"><?= htmlspecialchars((string)($lang['sys.backup_target_remote_path'] ?? 'Удалённый путь')) ?></label>
                        <input type="text" class="form-control" name="remote_path" value="<?= htmlspecialchars((string)($target['remote_path'] ?? '/backups')) ?>" placeholder="/backups/ee">
                        <div class="form-text"><?= htmlspecialchars((string)($lang['sys.backup_target_remote_path_help'] ?? 'Внутри этого пути система создаёт подпапку снапшота и загружает manifest/db/files архивы.')) ?></div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <label class="form-label"><?= htmlspecialchars((string)($lang['sys.timeout'] ?? 'Таймаут')) ?></label>
                        <input type="number" min="5" max="300" class="form-control" name="timeout_sec" value="<?= (int)($target['timeout_sec'] ?? 30) ?>">
                    </div>

                    <div class="col-12 col-lg-4" id="backup-target-ftp-passive-wrap">
                        <div class="form-check form-switch mt-lg-4 pt-lg-2">
                            <input class="form-check-input" type="checkbox" id="backup-target-ftp-passive" name="ftp_passive" value="1" <?= !empty($target['ftp_passive']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="backup-target-ftp-passive"><?= htmlspecialchars((string)($lang['sys.backup_target_ftp_passive'] ?? 'FTP passive mode')) ?></label>
                        </div>
                    </div>

                    <div class="col-12 col-lg-4">
                        <div class="form-check form-switch mt-lg-4 pt-lg-2">
                            <input class="form-check-input" type="checkbox" id="backup-target-active" name="is_active" value="1" <?= !empty($target['is_active']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="backup-target-active"><?= htmlspecialchars((string)($lang['sys.active'] ?? 'Активен')) ?></label>
                        </div>
                    </div>

                    <div class="col-12 col-lg-4">
                        <div class="form-check form-switch mt-lg-4 pt-lg-2">
                            <input class="form-check-input" type="checkbox" id="backup-target-default" name="is_default" value="1" <?= !empty($target['is_default']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="backup-target-default"><?= htmlspecialchars((string)($lang['sys.backup_target_default'] ?? 'Использовать по умолчанию')) ?></label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end gap-2">
                <a href="/admin/backup" class="btn btn-outline-secondary"><?= htmlspecialchars((string)($lang['sys.cancel'] ?? 'Отмена')) ?></a>
                <button type="submit" class="btn btn-primary"><?= htmlspecialchars((string)($lang['sys.save'] ?? 'Сохранить')) ?></button>
            </div>
        </form>
    </div>
</main>

<script>
(() => {
    const protocol = document.getElementById('backup-target-protocol');
    const port = document.getElementById('backup-target-port');
    const ftpPassiveWrap = document.getElementById('backup-target-ftp-passive-wrap');
    function syncTargetForm() {
        if (!protocol || !port || !ftpPassiveWrap) return;
        const isFtp = protocol.value === 'ftp';
        ftpPassiveWrap.style.display = isFtp ? '' : 'none';
        if (port.value === '' || port.dataset.autofill === '1') {
            port.value = isFtp ? '21' : '22';
            port.dataset.autofill = '1';
        }
    }
    if (port) {
        port.addEventListener('input', () => {
            port.dataset.autofill = '0';
        });
    }
    if (protocol) {
        protocol.addEventListener('change', syncTargetForm);
    }
    syncTargetForm();
})();
</script>
