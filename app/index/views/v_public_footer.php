<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<?php
$siteContent = is_array($siteContent ?? null) ? $siteContent : [];
$headerContent = is_array($siteContent['header'] ?? null) ? $siteContent['header'] : [];
$linkContent = is_array($siteContent['links'] ?? null) ? $siteContent['links'] : [];
$footerContent = is_array($siteContent['footer'] ?? null) ? $siteContent['footer'] : [];
$logoUrl = trim((string) ($headerContent['logo_url'] ?? ''));
if ($logoUrl === '') {
    $logoUrl = '/favicon.png';
}
$blogUrl = trim((string) ($linkContent['blog_url'] ?? '/blog'));
$ownersUrl = trim((string) ($linkContent['owners_url'] ?? '/owners'));
$aboutUrl = trim((string) ($linkContent['about_url'] ?? '/about'));
$contactsUrl = trim((string) ($linkContent['contacts_url'] ?? '/contact'));
$loginUrl = trim((string) ($linkContent['login_url'] ?? '/login'));
$registrationUrl = trim((string) ($linkContent['registration_url'] ?? '/registration'));
$privacyPolicyUrl = trim((string) ($linkContent['privacy_policy_url'] ?? '/privacy_policy'));
$personalDataConsentUrl = trim((string) ($linkContent['personal_data_consent_url'] ?? '/consent_personal_data'));
?>
<footer class="ee-public-footer">
    <div class="container">
        <div class="ee-footer-grid">
            <div>
                <a class="ee-footer-brand" href="<?= htmlspecialchars((string) ENV_URL_SITE, ENT_QUOTES, 'UTF-8') ?>">
                    <img class="ee-brand-logo" src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ENV_SITE_NAME, ENT_QUOTES, 'UTF-8') ?>">
                </a>
            </div>
            <div>
                <h3><?= htmlspecialchars((string) ($footerContent['sections_title'] ?? 'Разделы'), ENT_QUOTES, 'UTF-8') ?></h3>
                <div class="ee-footer-links">
                    <a href="/"><?= htmlspecialchars((string) ($footerContent['home_label'] ?? 'Главная'), ENT_QUOTES, 'UTF-8') ?></a>
                    <a href="<?= htmlspecialchars($blogUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($headerContent['blog_label'] ?? 'Блог'), ENT_QUOTES, 'UTF-8') ?></a>
                    <a href="<?= htmlspecialchars($ownersUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($headerContent['owners_label'] ?? 'Владельцам'), ENT_QUOTES, 'UTF-8') ?></a>
                    <a href="<?= htmlspecialchars($aboutUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($headerContent['about_label'] ?? 'О нас'), ENT_QUOTES, 'UTF-8') ?></a>
                    <a href="<?= htmlspecialchars($contactsUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($headerContent['contacts_label'] ?? 'Контакты'), ENT_QUOTES, 'UTF-8') ?></a>
                </div>
            </div>
            <div>
                <h3><?= htmlspecialchars((string) ($footerContent['service_title'] ?? 'Сервис'), ENT_QUOTES, 'UTF-8') ?></h3>
                <div class="ee-footer-links">
                    <a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($footerContent['login_label'] ?? 'Войти'), ENT_QUOTES, 'UTF-8') ?></a>
                    <a href="<?= htmlspecialchars($registrationUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($footerContent['registration_label'] ?? 'Зарегистрироваться'), ENT_QUOTES, 'UTF-8') ?></a>
                </div>
            </div>
            <div>
                <h3><?= htmlspecialchars((string) ($footerContent['legal_title'] ?? 'Документы'), ENT_QUOTES, 'UTF-8') ?></h3>
                <div class="ee-footer-links">
                    <a href="<?= htmlspecialchars($privacyPolicyUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($footerContent['privacy_policy_label'] ?? 'Политика персональных данных'), ENT_QUOTES, 'UTF-8') ?></a>
                    <a href="<?= htmlspecialchars($personalDataConsentUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($footerContent['personal_data_consent_label'] ?? 'Согласие на обработку данных'), ENT_QUOTES, 'UTF-8') ?></a>
                </div>
            </div>
            <div>
                <h3><?= htmlspecialchars((string) ($footerContent['demo_title'] ?? 'О стенде'), ENT_QUOTES, 'UTF-8') ?></h3>
                <div class="ee-footer-links">
                    <span><?= htmlspecialchars((string) ($footerContent['demo_text_1'] ?? 'Публичный фронт и панель менеджера находятся в демонстрационном режиме.'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span><?= htmlspecialchars((string) ($footerContent['demo_text_2'] ?? 'Контакты объектов и коммерческие действия временно скрыты до запуска проекта.'), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>
        </div>
        <div class="ee-footer-bottom">
            <span>&copy; <?= date('Y') ?> <?= htmlspecialchars((string) ENV_SITE_NAME, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>
</footer>
