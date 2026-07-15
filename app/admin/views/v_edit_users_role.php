<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<?php
$roleId = (int)($users_role_data['role_id'] ?? 0);
$roleActionUrl = \classes\system\CsrfService::appendToUrl('/admin/users_role_edit/id' . ($roleId > 0 ? '/' . $roleId : ''));
$rolePropertySetIds = is_array($rolePropertySetIds ?? null) ? $rolePropertySetIds : [];
$propertySetsData = is_array($propertySetsData ?? null) ? $propertySetsData : ['data' => []];
?>
<!-- Редактирование роли пользователей -->
<main>
    <form  id="edit_users_role" action="<?= htmlspecialchars($roleActionUrl, ENT_QUOTES, 'UTF-8') ?>" method="POST">
        <input type="hidden" name="fake" value="1" />
        <input type="hidden" name="role_id" value="<?=$users_role_data['role_id']?>" />
        <div class="container-fluid px-4">
            <h1 class="mt-4"><?= $users_role_data['role_id'] ? $lang['sys.add'] : $lang['sys.edit'] ?></h1>
            <ol class="breadcrumb mb-4">
                <li class="breadcrumb-item active">
                    <span <?= $userData['user_role'] > 2 ? 'style="display:none;"' : '' ?> id="role_id" data-id="<?= $users_role_data['role_id'] ?>">
                        id = <?php echo !$users_role_data['role_id'] ? 'Не присвоен' : $users_role_data['role_id'] ?></span>
                </li>
            </ol>
            <div class="row">
                <div class="col-md-4">
                    <label for="name-input"><?= $lang['sys.name'] ?>:</label>
                    <input type="text" id="name-input" name="name" class="form-control" value="<?= $users_role_data['name'] ?>">
                </div>
            </div>
            <div class="row mt-4">
                <div class="col-12">
                    <h2 class="h5"><?= htmlspecialchars((string) ($lang['sys.user_role_property_sets'] ?? 'Наборы свойств пользователей')) ?></h2>
                    <p class="text-muted small mb-3">
                        <?= htmlspecialchars((string) ($lang['sys.user_role_property_sets_hint'] ?? 'Выбранные наборы будут доступны в карточках пользователей с этой ролью.')) ?>
                    </p>
                    <?php if (!empty($propertySetsData['data'])) { ?>
                        <?= \classes\system\Plugins::renderPropertySets($propertySetsData, $rolePropertySetIds, $rolePropertySetIds) ?>
                    <?php } else { ?>
                        <div class="alert alert-info">
                            <?= htmlspecialchars((string) ($lang['sys.no_property_sets'] ?? 'Наборы свойств не созданы.')) ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
            <button type="submit" class="btn btn-primary my-3"><?= $lang['sys.save'] ?></button>
        </div>		
    </form>
</main>
