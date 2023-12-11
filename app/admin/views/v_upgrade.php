<?php
if (!defined('ENV_SITE')) exit(header('Location: /', true, 301));
$lang = is_array($lang ?? null) ? $lang : [];
$supportEmail = trim((string) ENV_SUPPORT_EMAIL);
$subject = rawurlencode((string) ($lang['sys.upgrade_mail_subject'] ?? ('EE_FrameWork v.' . ENV_VERSION_CORE)));
$body = rawurlencode((string) ($lang['sys.upgrade_mail_body'] ?? 'Describe what kind of help you need with the project.'));
$to = $supportEmail !== '' ? rawurlencode($supportEmail) : '';
$githubUrl = 'https://github.com/evgeniyefimchenko/EE_FrameWork';
?>
<!-- Upgrade / support page -->

<main>
    <div class="container mt-3">
        <div class="row">
            <div class="col-md-12">
                <h2 class="text-center"><?= htmlspecialchars((string) ($lang['sys.upgrade_heading'] ?? 'EE_FrameWork is distributed as an open-source project.'), ENT_QUOTES) ?></h2>
                <?= htmlspecialchars((string) ($lang['sys.upgrade_text'] ?? 'If you need help with integration or further development, use the GitHub repository and the configured support contacts.'), ENT_QUOTES) ?>
                <div class="card mt-3 p-3 w-75">
                    <a href="<?= htmlspecialchars($githubUrl, ENT_QUOTES) ?>" target="_blank" rel="noopener">
                        <?= htmlspecialchars((string) ($lang['sys.github_repository'] ?? 'GitHub repository'), ENT_QUOTES) ?>
                        <span class="float-end badge bg-secondary"><?= htmlspecialchars($githubUrl, ENT_QUOTES) ?></span>
                    </a>
                    <?php if ($supportEmail !== ''): ?>
                        <a href='mailto:?subject=<?= $subject ?>&body=<?= $body ?>&to=<?= $to ?>' target="_blank" rel="noopener">
                            <?= htmlspecialchars((string) ($lang['sys.support_email'] ?? 'Support email'), ENT_QUOTES) ?>
                            <span class="float-end badge bg-secondary"><?= htmlspecialchars($supportEmail, ENT_QUOTES) ?></span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>
