<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<!-- Просмотр удалённого пользователя -->
<main>
    <div class="container-fluid px-4">
        <ol class="breadcrumb mb-4">
            <li class="breadcrumb-item active">
                <?= htmlspecialchars($deleted_user_data['name']) ?>
            </li>
        </ol>
        <div class="mb-3">
            <a href="<?= htmlspecialchars(\classes\system\CsrfService::appendToUrl('/admin/restore_user/id/' . (int) $deleted_user_data['user_id']), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-success" onclick="return confirm('<?= htmlspecialchars($lang['sys.restore_user_confirm']) ?>');">
                <?= $lang['sys.restore_user'] ?>
            </a>
            <?php if (!empty($deleted_user_can_purge)): ?>
                <form method="post" action="<?= htmlspecialchars((string) ($deleted_user_purge_url ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="d-inline-block ms-2" onsubmit="return confirm('<?= htmlspecialchars('Полностью удалить пользователя из архива без возможности восстановления?', ENT_QUOTES, 'UTF-8') ?>');">
                    <button type="submit" class="btn btn-outline-danger">Полностью удалить</button>
                </form>
            <?php else: ?>
                <button type="button" class="btn btn-outline-danger ms-2" disabled>Полностью удалить</button>
            <?php endif; ?>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header"><?= $lang['sys.user_info'] ?></div>
                    <div class="card-body">
                        <table class="table">
                            <tbody>
                                <tr>
                                    <th><?= $lang['sys.email'] ?>:</th>
                                    <td><?= htmlspecialchars($deleted_user_data['email']) ?></td>
                                </tr>
                                <tr>
                                    <th><?= $lang['sys.role'] ?>:</th>
                                    <td><?= htmlspecialchars($deleted_user_data['user_role_text']) ?></td>
                                </tr>
                                <tr>
                                    <th><?= $lang['sys.last_ip'] ?>:</th>
                                    <td><?= htmlspecialchars($deleted_user_data['last_ip']) ?></td>
                                </tr>
                                <tr>
                                    <th><?= $lang['sys.privacy_policy'] ?? 'Политика в отношении обработки персональных данных' ?>:</th>
                                    <td><?= !empty($deleted_user_data['privacy_policy_accepted']) ? $lang['sys.yes'] : $lang['sys.no'] ?></td>
                                </tr>
                                <tr>
                                    <th><?= $lang['sys.personal_data_consent'] ?? 'Согласие на обработку персональных данных' ?>:</th>
                                    <td><?= !empty($deleted_user_data['personal_data_consent_accepted']) ? $lang['sys.yes'] : $lang['sys.no'] ?></td>
                                </tr>
                                <tr>
                                    <th><?= $lang['sys.sign_up_text'] ?>:</th>
                                    <td><?= htmlspecialchars($deleted_user_data['created_at']) ?></td>
                                </tr>
                                <?php foreach ((array) ($deleted_user_extra_info_rows ?? []) as $extraRow): ?>
                                    <tr>
                                        <th><?= htmlspecialchars((string) ($extraRow['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>:</th>
                                        <td>
                                            <?php if (!empty($extraRow['value_html'])): ?>
                                                <?= (string) $extraRow['value_html'] ?>
                                            <?php else: ?>
                                                <?= htmlspecialchars((string) ($extraRow['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if (!empty($deleted_user_purge_warning)): ?>
                            <div class="alert alert-warning mb-0">
                                <?= htmlspecialchars((string) $deleted_user_purge_warning, ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?= (string) ($deleted_user_extra_sidebar_html ?? '') ?>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><?= $lang['sys.messages'] ?></div>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($deleted_user_data['messages'] as $message): ?>
                            <li class="list-group-item">
                                <?= htmlspecialchars($message['message_text']) ?> - 
                                <small><?= htmlspecialchars($message['created_at']) ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>  
</main>
