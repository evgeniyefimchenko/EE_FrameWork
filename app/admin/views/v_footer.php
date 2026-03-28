<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<footer class="py-4 bg-light mt-auto">
    <div class="container-fluid px-4">
        <div class="d-flex align-items-center justify-content-between small">
            <div class="text-muted"><?= htmlspecialchars((string) (($lang['sys.copyright'] ?? 'Copyright') . ' © ' . ENV_SITE_NAME . ' ' . date('Y'))) ?></div>
            <div>
                <a href="/privacy_policy"><?= htmlspecialchars((string) ($lang['sys.privacy_policy'] ?? 'Privacy Policy')) ?></a>
                &middot;
                <a href="/consent_personal_data"><?= htmlspecialchars((string) ($lang['sys.terms_of_use'] ?? 'Terms of use')) ?></a>
            </div>
        </div>
    </div>
</footer>
