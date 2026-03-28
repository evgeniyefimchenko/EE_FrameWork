<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<div style="width: 100%; text-align: center;">
    <h1><?= htmlspecialchars((string) ($lang['sys.about_intro_title'] ?? 'The project is an open-source PHP platform and CMS core for building content systems.'), ENT_QUOTES, 'UTF-8') ?></h1>
    <h2><?= htmlspecialchars((string) ($lang['sys.about_author_title'] ?? 'Project author'), ENT_QUOTES, 'UTF-8') ?> <a href="https://efimchenko.com" target="_blank" rel="noopener noreferrer">efimchenko.com</a></h2>
    <h3><?= htmlspecialchars((string) ($lang['sys.about_repository_title'] ?? 'Project repository'), ENT_QUOTES, 'UTF-8') ?> <a href="https://github.com/evgeniyefimchenko/EE_FrameWork" target="_blank" rel="noopener noreferrer">https://github.com/evgeniyefimchenko/EE_FrameWork</a></h3>
    <h4><a href="<?= ENV_URL_SITE ?>"><?= htmlspecialchars((string) ($lang['sys.back_to_home'] ?? 'Back to home'), ENT_QUOTES, 'UTF-8') ?></a></h4>
</div>
