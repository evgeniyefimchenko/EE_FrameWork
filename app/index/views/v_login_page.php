<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<?php
$siteContent = is_array($siteContent ?? null) ? $siteContent : [];
$authContent = is_array($siteContent['auth']['login'] ?? null) ? $siteContent['auth']['login'] : [];
$linkContent = is_array($siteContent['links'] ?? null) ? $siteContent['links'] : [];
$loginUrl = trim((string) ($linkContent['login_url'] ?? '/login'));
$registrationUrl = trim((string) ($linkContent['registration_url'] ?? '/registration'));
?>
<?= $top_panel ?? '' ?>

<main class="allbriz-public-main">
    <section class="allbriz-auth-page">
        <div class="container">
            <div class="allbriz-auth-layout">
                <div class="allbriz-auth-copy">
                    <span class="allbriz-auth-kicker"><?= htmlspecialchars((string) ($authContent['kicker'] ?? ($lang['sys.authorization'] ?? 'Авторизация')), ENT_QUOTES, 'UTF-8') ?></span>
                    <h1><?= htmlspecialchars((string) ($authContent['title'] ?? 'Вход в личный кабинет'), ENT_QUOTES, 'UTF-8') ?></h1>
                    <p class="allbriz-auth-lead"><?= htmlspecialchars((string) ($authContent['lead'] ?? 'Один аккаунт подходит для обычного пользователя и владельца объекта. После входа система сама отправит вас в нужный контур.'), ENT_QUOTES, 'UTF-8') ?></p>
                    <div class="allbriz-auth-points">
                        <div class="allbriz-auth-point">
                            <strong><?= htmlspecialchars((string) ($authContent['point_1_title'] ?? 'Личный кабинет пользователя'), ENT_QUOTES, 'UTF-8') ?></strong>
                            <span><?= htmlspecialchars((string) ($authContent['point_1_text'] ?? 'Управление своими объектами, комментариями, счетами и настройками профиля.'), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="allbriz-auth-point">
                            <strong><?= htmlspecialchars((string) ($authContent['point_2_title'] ?? 'Прозрачная модель доступа'), ENT_QUOTES, 'UTF-8') ?></strong>
                            <span><?= htmlspecialchars((string) ($authContent['point_2_text'] ?? 'Публично регистрируются только обычные пользователи. Менеджеры и администраторы создаются только внутри платформы.'), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="allbriz-auth-point">
                            <strong><?= htmlspecialchars((string) ($authContent['point_3_title'] ?? 'Защищённый вход'), ENT_QUOTES, 'UTF-8') ?></strong>
                            <span><?= htmlspecialchars((string) ($authContent['point_3_text'] ?? 'Используются обязательные соглашения платформы и серверные проверки авторизации.'), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>
                </div>

                <div class="allbriz-auth-card" data-auth-surface>
                    <div class="allbriz-auth-switch">
                        <a class="is-active" href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>">Войти</a>
                        <a href="<?= htmlspecialchars($registrationUrl, ENT_QUOTES, 'UTF-8') ?>">Зарегистрироваться</a>
                    </div>

                    <div class="allbriz-auth-feedback auth-feedback" role="alert" aria-live="polite"></div>

                    <div data-auth-page-panel="login">
                        <h2><?= htmlspecialchars((string) ($authContent['form_title'] ?? 'Добро пожаловать'), ENT_QUOTES, 'UTF-8') ?></h2>
                        <p class="allbriz-auth-card-subtitle"><?= htmlspecialchars((string) ($authContent['form_subtitle'] ?? 'Введите почту и пароль, чтобы продолжить работу с платформой.'), ENT_QUOTES, 'UTF-8') ?></p>

                        <form id="log_form" method="post" accept-charset="UTF-8" novalidate>
                            <div class="form-group">
                                <label class="form-label" for="log_email"><?= htmlspecialchars((string) $lang['sys.email'], ENT_QUOTES, 'UTF-8') ?></label>
                                <input id="log_email" class="form-control" type="email" autocomplete="email" placeholder="<?= htmlspecialchars((string) $lang['sys.email'], ENT_QUOTES, 'UTF-8') ?>" required name="email">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="log_password"><?= htmlspecialchars((string) $lang['sys.password'], ENT_QUOTES, 'UTF-8') ?></label>
                                <input id="log_password" class="form-control" type="password" autocomplete="current-password" placeholder="<?= htmlspecialchars((string) $lang['sys.password'], ENT_QUOTES, 'UTF-8') ?>" name="password" required>
                            </div>
                            <button class="btn btn-default btn-login" type="submit"><?= htmlspecialchars((string) $lang['sys.log_in'], ENT_QUOTES, 'UTF-8') ?></button>
                        </form>

                        <?php if (defined('ENV_AUTH_GOOGLE_CLIENT_ID') && trim((string) ENV_AUTH_GOOGLE_CLIENT_ID) !== '') { ?>
                            <div class="mt-3">
                                <a class="btn btn-outline-dark w-100" href="/auth_consent/provider/google"><?= htmlspecialchars((string) $lang['sys.continue_with_google'], ENT_QUOTES, 'UTF-8') ?></a>
                            </div>
                        <?php } ?>

                        <div class="allbriz-auth-links">
                            <a href="#" data-auth-page-panel-toggle="recovery"><?= htmlspecialchars((string) $lang['sys.restore_password'], ENT_QUOTES, 'UTF-8') ?></a>
                            <a href="<?= htmlspecialchars($registrationUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $lang['sys.sign_up'], ENT_QUOTES, 'UTF-8') ?></a>
                        </div>
                    </div>

                    <div data-auth-page-panel="recovery" style="display:none;" aria-hidden="true">
                        <h2><?= htmlspecialchars((string) ($authContent['recovery_title'] ?? 'Восстановление доступа'), ENT_QUOTES, 'UTF-8') ?></h2>
                        <p class="allbriz-auth-card-subtitle"><?= htmlspecialchars((string) ($authContent['recovery_subtitle'] ?? 'Отправим ссылку для восстановления на почту, которая указана в аккаунте.'), ENT_QUOTES, 'UTF-8') ?></p>

                        <form id="recovery_form" method="post" accept-charset="UTF-8" novalidate>
                            <div class="form-group">
                                <label class="form-label" for="rec_email"><?= htmlspecialchars((string) $lang['sys.email'], ENT_QUOTES, 'UTF-8') ?></label>
                                <input id="rec_email" class="form-control" autocomplete="email" type="email" placeholder="<?= htmlspecialchars((string) ($lang['sys.your_email'] ?? $lang['sys.email']), ENT_QUOTES, 'UTF-8') ?>" name="email" required>
                            </div>
                            <button class="btn btn-default btn-recovery" type="submit"><?= htmlspecialchars((string) $lang['sys.restore_password'], ENT_QUOTES, 'UTF-8') ?></button>
                        </form>

                        <div class="allbriz-auth-links">
                            <a href="#" data-auth-page-panel-toggle="login"><?= htmlspecialchars((string) $lang['sys.log_in'], ENT_QUOTES, 'UTF-8') ?></a>
                            <a href="<?= htmlspecialchars($registrationUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $lang['sys.sign_up'], ENT_QUOTES, 'UTF-8') ?></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?= $footer_public ?? '' ?>
