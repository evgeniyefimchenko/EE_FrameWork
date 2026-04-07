<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<!-- Всплывающая форма авторизации и регистрации -->
<div class="container">
    <div class="modal fade login" id="loginModal">    
        <div class="modal-dialog login animated modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="w-100 pe-4">
                        <h4 class="modal-title text-center col-sm pl-5"></h4>
                        <p class="login-modal-subtitle mb-0"><?= htmlspecialchars((string) ($lang['sys.login_modal_hint'] ?? 'Используйте свою почту и пароль для входа в систему.')) ?></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="close_button"></button>
                </div>
                <div class="modal-body">
                    <div class="auth-feedback" id="auth-feedback" role="alert" aria-live="polite"></div>
                    <div class="box-login">
                        <div class="content">
                            <div class="form loginBox">
                                <form id="log_form" method="post" accept-charset="UTF-8" novalidate>
                                    <div class="form-group">
                                        <label class="form-label" for="log_email"><?= htmlspecialchars((string) $lang['sys.email']) ?></label>
                                        <input id="log_email" class="form-control" type="email" autocomplete="email" placeholder="<?= $lang['sys.email'] ?>" required="true" name="email">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="log_password"><?= htmlspecialchars((string) $lang['sys.password']) ?></label>
                                        <input id="log_password" class="form-control" type="password" autocomplete="current-password" placeholder="<?= $lang['sys.password'] ?>" name="password" required="true">
                                    </div>
                                    <div class="form-group">
                                        <input class="btn btn-default btn-login" type="submit" value="<?= $lang['sys.log_in'] ?>">
                                    </div>                                
                                </form>
                                <?php if (defined('ENV_AUTH_GOOGLE_CLIENT_ID') && trim((string) ENV_AUTH_GOOGLE_CLIENT_ID) !== '') { ?>
                                    <div class="mt-3 text-center">
                                        <a class="btn btn-outline-dark w-100" href="/auth_consent/provider/google"><?= $lang['sys.continue_with_google'] ?></a>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                    <div class="box-login">
                        <div class="content registerBox" style="display:none;">
                            <div class="form">
                                <form id="reg_form" method="post" accept-charset="UTF-8" novalidate>
                                    <div class="form-group">
                                        <label class="form-label" for="reg_email"><?= htmlspecialchars((string) $lang['sys.email']) ?></label>
                                        <input id="reg_email" class="form-control" autocomplete="email" type="email" placeholder="<?= $lang['sys.email'] ?>" name="email" required="true" value="">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="reg_password"><?= htmlspecialchars((string) $lang['sys.password']) ?></label>
                                        <input id="reg_password" class="form-control" type="password" autocomplete="new-password" placeholder="<?= $lang['sys.password'] ?>" name="password" required="true" value="">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="reg_password_confirmation"><?= htmlspecialchars((string) $lang['sys.confirm_password']) ?></label>
                                        <input id="reg_password_confirmation" class="form-control" autocomplete="new-password" type="password" placeholder="<?= $lang['sys.confirm_password'] ?>" name="password_confirmation" required="true">
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="reg_privacy_policy_accepted" name="privacy_policy_accepted" value="1" required>
                                        <label class="form-check-label" for="reg_privacy_policy_accepted">
                                            <?= htmlspecialchars((string) ($lang['sys.accept_privacy_policy'] ?? 'Я ознакомлен(а) и принимаю Политику в отношении обработки персональных данных')) ?>
                                            <a href="/privacy_policy" target="_blank" rel="noopener"><?= htmlspecialchars((string) ($lang['sys.open_document'] ?? 'Открыть документ')) ?></a>
                                        </label>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="reg_personal_data_consent_accepted" name="personal_data_consent_accepted" value="1" required>
                                        <label class="form-check-label" for="reg_personal_data_consent_accepted">
                                            <?= htmlspecialchars((string) ($lang['sys.accept_personal_data_consent'] ?? 'Я даю согласие на обработку персональных данных')) ?>
                                            <a href="/consent_personal_data" target="_blank" rel="noopener"><?= htmlspecialchars((string) ($lang['sys.open_document'] ?? 'Открыть документ')) ?></a>
                                        </label>
                                    </div>
                                    <input class="btn btn-default btn-register" type="submit" value="<?= $lang['sys.sign_up'] ?>" name="commit">
                                </form>
                                <?php if (defined('ENV_AUTH_GOOGLE_CLIENT_ID') && trim((string) ENV_AUTH_GOOGLE_CLIENT_ID) !== '') { ?>
                                    <div class="mt-3 text-center">
                                        <a class="btn btn-outline-dark w-100" href="/auth_consent/provider/google"><?= $lang['sys.continue_with_google'] ?></a>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                    <div class="box-login">
                        <div class="content PasswordRecoveryBox" style="display:none;">
                            <div class="form">
                                <form id="recovery_form" method="post" accept-charset="UTF-8" novalidate>
                                    <div class="form-group">
                                        <label class="form-label" for="rec_email"><?= htmlspecialchars((string) $lang['sys.email']) ?></label>
                                        <input id="rec_email" class="form-control" autocomplete="email" type="email" placeholder="<?= $lang['sys.your_email'] ?>" name="email" required="true" value="">
                                    </div>
                                    <input class="btn btn-default btn-recovery" type="submit" value="<?= $lang['sys.restore_password'] ?>" name="commit">
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="forgot login-footer">
                        <span>
                            <a href="#" data-auth-modal="register"><?= $lang['sys.sign_up'] ?></a><br/>
                            <a href="#" data-auth-modal="recovery"><?= $lang['sys.restore_password'] ?></a>
                        </span>
                    </div>
                    <div class="forgot register-footer" style="display:none;">
                        <a href="#" data-auth-modal="login"><?= $lang['sys.log_in'] ?></a>
                        <br/>
                        <a href="#" data-auth-modal="recovery"><?= $lang['sys.restore_password'] ?></a>
                    </div>
                    <div class="forgot recovery-footer" style="display:none;">
                        <a href="#" data-auth-modal="register"><?= $lang['sys.sign_up'] ?></a>
                        <br/>
                        <a href="#" data-auth-modal="login"><?= $lang['sys.log_in'] ?></a>
                    </div>
                </div>        
            </div>
        </div>
    </div>
    <form id="return_general" method="get" action="/" accept-charset="UTF-8" ></form>
</div>
