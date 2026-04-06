<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<!-- Редактирование пользователя сайта -->
<?php
$isProtectedSystemUser = in_array((int) ($user_context['user_role'] ?? 0), [\classes\system\Constants::ADMIN, \classes\system\Constants::SYSTEM], true);
?>
<main>
    <form id="edit_users">
        <input type="hidden" name="fake" value="1" />
        <input type="hidden" name="new" value="<?= $user_context['new_user'] ? 1 : 0 ?>" />
        <div class="container-fluid px-4">
            <h1 class="mt-4"><?= $user_context['new_user'] ? $lang['sys.add'] : $lang['sys.edit'] ?></h1>
            <ol class="breadcrumb mb-4">
                <li class="breadcrumb-item active">
                    <span <?= $userData['user_role'] > 2 ? 'style="display:none;"' : '' ?> id="id_user" data-id="<?= $user_context['user_id'] ?>">id = <?php echo $user_context['new_user'] ? 'Не присвоен' : $user_context['user_id'] ?></span>
                </li>
            </ol>
            <div class="row">
                <div class="col">
                    <ul class="nav nav-tabs" id="myTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic-tab-pane" type="button" role="tab" aria-controls="basic-tab-pane" aria-selected="true"><?= $lang['sys.basics'] ?></button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security-tab-pane" type="button" role="tab" aria-controls="security-tab-pane" aria-selected="false"><?= $lang['sys.safety'] ?></button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="comment-tab" data-bs-toggle="tab" data-bs-target="#comment-tab-pane" type="button" role="tab" aria-controls="comment-tab-pane" aria-selected="false"><?= $lang['sys.comment'] ?></button>
                        </li>
                    </ul>
                    <div class="tab-content" id="myTabContent">
                        <!-- Основное содержимое -->
                        <div class="tab-pane fade show active mt-3" id="basic-tab-pane" role="tabpanel" aria-labelledby="basic-tab">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="name-input"><?= $lang['sys.name'] ?>:</label>
                                    <input type="text" id="name-input" name="name" class="form-control" placeholder="Введите имя..." value="<?= $user_context['name'] ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="email-input"><?= $lang['sys.email'] ?>:</label>
                                    <input type="email" id="email-input" name="email" class="form-control" placeholder="Введите почту..." value="<?= $user_context['email'] ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="phone-input"><?= $lang['sys.phone'] ?>:</label>
                                    <input type="tel" id="phone-input" name="phone" class="form-control" placeholder="Введите телефон..." value="<?= $user_context['phone'] ?>">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label><?= $lang['sys.status'] ?></label>
                                    <select <?= $isProtectedSystemUser ? 'disabled' : '' ?> name="active" class="selectpicker form-control">
                                        <option value="<?= $user_context['active'] ?>"><?= $user_context['active_text'] ?></option>
                                        <?php foreach ($free_active_status as $key => $val){?>
                                        <option value="<?= $key ?>"><?= $lang[$val] ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <!-- Посмотреть статус и роль пользователя может администратор и модератор -->
                                    <!-- Роль пользователя может сменить только администратор -->
                                    <label><?= $lang['sys.role'] ?></label>
                                    <?php if ($userData['user_role'] == 1) { ?>
                                        <select <?= $isProtectedSystemUser || $user_context['user_role'] == 3 ? 'disabled' : '' ?> name="user_role" class="selectpicker form-control">
                                            <option value="<?= $user_context['user_role'] ?>"><?= $user_context['user_role_text'] ?></option>
                                            <?php foreach ($get_free_roles as $role) { ?>
                                                <option value="<?= $role['role_id'] ?>"><?= $role['name'] ?></option>
                                            <?php } ?>
                                        </select>
                                    <?php } else { ?>
                                        <input name="user_role" type="hidden" value="<?= $user_context['user_role'] ?>" />
                                        <input class="form-control" readonly="true" value="<?= $user_context['user_role_text'] ? $user_context['user_role_text'] : "Пользователь" ?>" />
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="registration-date-input"><?= $lang['sys.date_create'] ?>:</label>
                                    <input type="text" disabled id="registration-date-input" class="form-control" value="<?= $user_context['created_at'] ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="update-date-input"><?= $lang['sys.date_update'] ?>:</label>
                                    <input type="text" disabled id="update-date-input" class="form-control" value="<?= $user_context['updated_at'] ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="update-date-input2"><?= $lang['sys.date_activity'] ?>:</label>
                                    <input type="text" disabled id="update-date-input2" class="form-control" value="<?= $user_context['last_activ'] ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="card border-0 bg-light">
                                    <div class="card-body">
                                        <h5 class="card-title mb-3"><?= $lang['sys.required_consents'] ?? 'Обязательные согласия' ?></h5>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" name="privacy_policy_accepted" type="checkbox" id="privacy-policy-check" <?= !empty($user_context['privacy_policy_accepted']) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="privacy-policy-check">
                                                <?= htmlspecialchars((string) ($lang['sys.accept_privacy_policy'] ?? 'Я ознакомлен(а) и принимаю Политику в отношении обработки персональных данных')) ?>
                                                <a href="/privacy_policy" target="_blank" rel="noopener"><?= htmlspecialchars((string) ($lang['sys.open_document'] ?? 'Открыть документ')) ?></a>
                                            </label>
                                            <?php if (!empty($user_context['privacy_policy_accepted_at'])) { ?>
                                                <div class="small text-muted mt-1">
                                                    <?= htmlspecialchars((string) ($lang['sys.accepted_at'] ?? 'Принято')) ?>:
                                                    <?= htmlspecialchars((string) $user_context['privacy_policy_accepted_at']) ?>
                                                    <?php if (!empty($user_context['privacy_policy_version'])) { ?>
                                                        , <?= htmlspecialchars((string) ($lang['sys.document_version'] ?? 'Версия документа')) ?>:
                                                        <?= htmlspecialchars((string) $user_context['privacy_policy_version']) ?>
                                                    <?php } ?>
                                                </div>
                                            <?php } ?>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" name="personal_data_consent_accepted" type="checkbox" id="personal-data-consent-check" <?= !empty($user_context['personal_data_consent_accepted']) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="personal-data-consent-check">
                                                <?= htmlspecialchars((string) ($lang['sys.accept_personal_data_consent'] ?? 'Я даю согласие на обработку персональных данных')) ?>
                                                <a href="/consent_personal_data" target="_blank" rel="noopener"><?= htmlspecialchars((string) ($lang['sys.open_document'] ?? 'Открыть документ')) ?></a>
                                            </label>
                                            <?php if (!empty($user_context['personal_data_consent_accepted_at'])) { ?>
                                                <div class="small text-muted mt-1">
                                                    <?= htmlspecialchars((string) ($lang['sys.accepted_at'] ?? 'Принято')) ?>:
                                                    <?= htmlspecialchars((string) $user_context['personal_data_consent_accepted_at']) ?>
                                                    <?php if (!empty($user_context['personal_data_consent_version'])) { ?>
                                                        , <?= htmlspecialchars((string) ($lang['sys.document_version'] ?? 'Версия документа')) ?>:
                                                        <?= htmlspecialchars((string) $user_context['personal_data_consent_version']) ?>
                                                    <?php } ?>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Содержимое для безопасности -->
                        <div class="tab-pane fade mt-3" id="security-tab-pane" role="tabpanel" aria-labelledby="security-tab">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="new_pass"><?= $lang['sys.new_password'] ?>:</label>
                                    <input type="password" id="new_pass" name="pwd" class="form-control" autocomplete="new-password" >
                                    <small></small>
                                </div>
                                <div class="col-md-6">
                                    <label for="new_pass_conf"><?= $lang['sys.confirm_password'] ?>:</label>
                                    <input type="password" id="new_pass_conf" name="new_pass_conf" class="form-control" >
                                    <small></small>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" id="auth_require_password_setup" name="auth_require_password_setup" <?= !empty($user_context['auth_security']['must_set_password']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="auth_require_password_setup">
                                            <?= $lang['sys.require_password_setup'] ?>
                                        </label>
                                    </div>
                                    <div class="form-check mt-3">
                                        <input class="form-check-input" type="checkbox" id="auth_ip_restricted" name="auth_ip_restricted" <?= !empty($user_context['options']['auth']['ip_restricted']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="auth_ip_restricted">
                                            <?= $lang['sys.ip_restricted'] ?>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="auth_password_setup_reason"><?= $lang['sys.password_setup_reason'] ?>:</label>
                                    <input type="text" id="auth_password_setup_reason" name="auth_password_setup_reason" class="form-control" value="<?= htmlspecialchars($user_context['options']['auth']['password_setup_reason'] ?? '') ?>" placeholder="<?= $lang['sys.enter_description'] ?>">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label><?= $lang['sys.local_password'] ?>:</label>
                                    <input type="text" class="form-control" disabled value="<?= !empty($user_context['auth_security']['has_local_password']) ? $lang['sys.yes'] : $lang['sys.no'] ?>">
                                </div>
                                <div class="col-md-4">
                                    <label><?= $lang['sys.failed_attempts'] ?>:</label>
                                    <input type="text" class="form-control" disabled value="<?= (int) ($user_context['auth_security']['failed_attempts'] ?? 0) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label><?= $lang['sys.locked_until'] ?>:</label>
                                    <input type="text" class="form-control" disabled value="<?= htmlspecialchars((string) ($user_context['auth_security']['locked_until'] ?? '')) ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label><?= $lang['sys.linked_providers'] ?>:</label>
                                <?php if (!empty($user_context['auth_security']['linked_identities'])) { ?>
                                    <ul class="list-group">
                                        <?php foreach ($user_context['auth_security']['linked_identities'] as $identity) { ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span><?= htmlspecialchars(ucfirst((string) ($identity['provider'] ?? 'provider'))) ?></span>
                                                <small class="text-muted"><?= htmlspecialchars((string) ($identity['provider_email'] ?? $identity['provider_user_id'] ?? '')) ?></small>
                                            </li>
                                        <?php } ?>
                                    </ul>
                                <?php } else { ?>
                                    <div class="text-muted small"><?= $lang['sys.no_linked_providers'] ?></div>
                                <?php } ?>
                            </div>
                        </div>
                        <!-- Содержимое для комментария -->
                        <div class="tab-pane fade mt-3 mb-3" id="comment-tab-pane" role="tabpanel" aria-labelledby="comment-tab">
                            <label><?= $lang['sys.comment'] ?>:</label>
                            <textarea class="form-control" name="comment" id="user_comment" placeholder="Оставьте ваш комментарий..."><?= $user_context['comment'] ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><?= $lang['sys.save'] ?></button>
        </div>		
    </form>
</main>
