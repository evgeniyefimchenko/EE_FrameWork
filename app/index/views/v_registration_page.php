<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<?php
$siteContent = is_array($siteContent ?? null) ? $siteContent : [];
$authContent = is_array($siteContent['auth']['registration'] ?? null) ? $siteContent['auth']['registration'] : [];
$legalContent = is_array($siteContent['legal'] ?? null) ? $siteContent['legal'] : [];
$linkContent = is_array($siteContent['links'] ?? null) ? $siteContent['links'] : [];
$loginUrl = trim((string) ($linkContent['login_url'] ?? '/login'));
$registrationUrl = trim((string) ($linkContent['registration_url'] ?? '/registration'));
$ownersUrl = trim((string) ($linkContent['owners_url'] ?? '/owners'));
$privacyPolicyUrl = trim((string) ($linkContent['privacy_policy_url'] ?? '/privacy_policy'));
$personalDataConsentUrl = trim((string) ($linkContent['personal_data_consent_url'] ?? '/consent_personal_data'));
?>
<?= $top_panel ?? '' ?>

<main class="ee-public-main">
    <section class="ee-auth-page">
        <div class="container">
            <div class="ee-auth-layout">
                <div class="ee-auth-copy">
                    <span class="ee-auth-kicker"><?= htmlspecialchars((string) ($authContent['kicker'] ?? ($lang['sys.sign_up_text'] ?? 'Регистрация')), ENT_QUOTES, 'UTF-8') ?></span>
                    <h1><?= htmlspecialchars((string) ($authContent['title'] ?? 'Создание аккаунта владельца'), ENT_QUOTES, 'UTF-8') ?></h1>
                    <p class="ee-auth-lead"><?= htmlspecialchars((string) ($authContent['lead'] ?? 'Публичная регистрация предназначена только для обычных пользователей и владельцев объектов. Доступы менеджеров, модераторов и администраторов через эту форму не создаются.'), ENT_QUOTES, 'UTF-8') ?></p>
                    <div class="ee-auth-points">
                        <div class="ee-auth-point">
                            <strong><?= htmlspecialchars((string) ($authContent['point_1_title'] ?? 'Понятная регистрация'), ENT_QUOTES, 'UTF-8') ?></strong>
                            <span><?= htmlspecialchars((string) ($authContent['point_1_text'] ?? 'Укажите рабочую почту, придумайте пароль и подтвердите обязательные документы платформы.'), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="ee-auth-point">
                            <strong><?= htmlspecialchars((string) ($authContent['point_2_title'] ?? 'Защита от спама'), ENT_QUOTES, 'UTF-8') ?></strong>
                            <span><?= htmlspecialchars((string) ($authContent['point_2_text'] ?? 'Форма проверяет время заполнения, скрытые поля для ботов и отдельный серверный challenge.'), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="ee-auth-point">
                            <strong><?= htmlspecialchars((string) ($authContent['point_3_title'] ?? 'Дальше можно размещать объект'), ENT_QUOTES, 'UTF-8') ?></strong>
                            <span><?= htmlspecialchars((string) ($authContent['point_3_text'] ?? 'После регистрации и входа вы попадёте в пользовательский контур и сможете работать со своими карточками.'), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>
                </div>

                <div class="ee-auth-card" data-auth-surface>
                    <div class="ee-auth-switch">
                        <a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>">Войти</a>
                        <a class="is-active" href="<?= htmlspecialchars($registrationUrl, ENT_QUOTES, 'UTF-8') ?>">Зарегистрироваться</a>
                    </div>

                    <div class="ee-auth-feedback auth-feedback" role="alert" aria-live="polite"></div>

                    <h2><?= htmlspecialchars((string) ($authContent['form_title'] ?? 'Регистрация пользователя'), ENT_QUOTES, 'UTF-8') ?></h2>
                    <p class="ee-auth-card-subtitle"><?= htmlspecialchars((string) ($authContent['form_subtitle'] ?? 'Создайте один аккаунт для работы с вашими объектами на платформе.'), ENT_QUOTES, 'UTF-8') ?></p>

                    <form id="reg_form" method="post" accept-charset="UTF-8" novalidate>
                        <div class="ee-auth-honeypot" aria-hidden="true">
                            <label for="reg_company_name">Компания</label>
                            <input id="reg_company_name" class="form-control" type="text" name="company_name" value="" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="reg_email"><?= htmlspecialchars((string) $lang['sys.email'], ENT_QUOTES, 'UTF-8') ?></label>
                            <input id="reg_email" class="form-control" autocomplete="email" type="email" placeholder="<?= htmlspecialchars((string) $lang['sys.email'], ENT_QUOTES, 'UTF-8') ?>" name="email" required value="<?= htmlspecialchars((string) ($registration_prefilled_email ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="reg_password"><?= htmlspecialchars((string) $lang['sys.password'], ENT_QUOTES, 'UTF-8') ?></label>
                            <input id="reg_password" class="form-control" type="password" autocomplete="new-password" placeholder="<?= htmlspecialchars((string) $lang['sys.password'], ENT_QUOTES, 'UTF-8') ?>" name="password" required value="">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="reg_password_confirmation"><?= htmlspecialchars((string) $lang['sys.confirm_password'], ENT_QUOTES, 'UTF-8') ?></label>
                            <input id="reg_password_confirmation" class="form-control" autocomplete="new-password" type="password" placeholder="<?= htmlspecialchars((string) $lang['sys.confirm_password'], ENT_QUOTES, 'UTF-8') ?>" name="password_confirmation" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="reg_captcha_answer"><?= htmlspecialchars((string) ($authContent['captcha_label'] ?? 'Проверка на человека'), ENT_QUOTES, 'UTF-8') ?></label>
                            <div class="ee-auth-captcha-box">
                                <div class="ee-auth-captcha-question" data-registration-captcha-question><?= htmlspecialchars((string) ($registration_captcha_question ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                <a class="ee-auth-captcha-refresh" href="<?= htmlspecialchars($registrationUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($authContent['captcha_refresh_label'] ?? 'Новый вопрос'), ENT_QUOTES, 'UTF-8') ?></a>
                            </div>
                            <input id="reg_captcha_answer" class="form-control" type="text" name="captcha_answer" autocomplete="off" placeholder="<?= htmlspecialchars((string) ($authContent['captcha_placeholder'] ?? 'Введите ответ'), ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="reg_privacy_policy_accepted" name="privacy_policy_accepted" value="1" required>
                            <label class="form-check-label" for="reg_privacy_policy_accepted">
                                <?= htmlspecialchars((string) ($legalContent['privacy_policy_label'] ?? ($lang['sys.accept_privacy_policy'] ?? 'Я ознакомлен(а) и принимаю Политику в отношении обработки персональных данных')), ENT_QUOTES, 'UTF-8') ?>
                                <a href="<?= htmlspecialchars($privacyPolicyUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars((string) ($legalContent['open_document_label'] ?? ($lang['sys.open_document'] ?? 'Открыть документ')), ENT_QUOTES, 'UTF-8') ?></a>
                            </label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="reg_personal_data_consent_accepted" name="personal_data_consent_accepted" value="1" required>
                            <label class="form-check-label" for="reg_personal_data_consent_accepted">
                                <?= htmlspecialchars((string) ($legalContent['personal_data_consent_label'] ?? ($lang['sys.accept_personal_data_consent'] ?? 'Я даю согласие на обработку персональных данных')), ENT_QUOTES, 'UTF-8') ?>
                                <a href="<?= htmlspecialchars($personalDataConsentUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars((string) ($legalContent['open_document_label'] ?? ($lang['sys.open_document'] ?? 'Открыть документ')), ENT_QUOTES, 'UTF-8') ?></a>
                            </label>
                        </div>
                        <button class="btn btn-default btn-register" type="submit"><?= htmlspecialchars((string) $lang['sys.sign_up'], ENT_QUOTES, 'UTF-8') ?></button>
                    </form>

                    <?php if (defined('ENV_AUTH_GOOGLE_CLIENT_ID') && trim((string) ENV_AUTH_GOOGLE_CLIENT_ID) !== '') { ?>
                        <div class="mt-3">
                            <a class="btn btn-outline-dark w-100" href="/auth_consent/provider/google"><?= htmlspecialchars((string) $lang['sys.continue_with_google'], ENT_QUOTES, 'UTF-8') ?></a>
                        </div>
                    <?php } ?>

                    <div class="ee-auth-links">
                        <a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $lang['sys.log_in'], ENT_QUOTES, 'UTF-8') ?></a>
                        <a href="<?= htmlspecialchars($ownersUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($authContent['owners_link_label'] ?? 'Подробнее для владельцев'), ENT_QUOTES, 'UTF-8') ?></a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?= $footer_public ?? '' ?>
