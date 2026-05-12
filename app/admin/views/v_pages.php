<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<!-- Таблица сущностей -->
<main>
    <div class="container-fluid px-4">
        <?php
        $availableContentLanguageCodes = is_array($availableContentLanguageCodes ?? null) ? $availableContentLanguageCodes : [];
        $currentContentLanguageCode = strtoupper((string) ($currentContentLanguageCode ?? ee_get_default_content_lang_code()));
        $defaultContentLanguageCode = strtoupper((string) ($defaultContentLanguageCode ?? ee_get_default_content_lang_code()));
        $languageSwitchBaseUrl = (string) ($languageSwitchBaseUrl ?? '/admin/pages');
        $showContentLanguageSwitcher = count(array_unique(array_map('strtoupper', $availableContentLanguageCodes))) > 1;
        ?>
        <a href="/admin/page_edit/id?language_code=<?= rawurlencode((string) ($currentContentLanguageCode ?: $defaultContentLanguageCode)) ?>" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= $lang['sys.add'] ?>" type="button"
           class="btn btn-info mx-1 float-end">
            <i class="fa fa-plus-circle"></i>&nbsp;<?= $lang['sys.add'] ?>
        </a>        
        <h1 class="mt-4"><?= $lang['sys.pages'] ?></h1>
        <?php if ($showContentLanguageSwitcher): ?>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <span class="small text-muted"><?= htmlspecialchars((string)($lang['sys.content_language'] ?? 'Язык контента')) ?>:</span>
                <div class="btn-group btn-group-sm" role="group" aria-label="<?= htmlspecialchars((string)($lang['sys.content_language'] ?? 'Язык контента')) ?>">
                    <?php foreach ($availableContentLanguageCodes as $availableLanguageCode): ?>
                        <a
                            href="<?= htmlspecialchars($languageSwitchBaseUrl . '?language_code=' . rawurlencode((string)$availableLanguageCode), ENT_QUOTES, 'UTF-8') ?>"
                            class="btn <?= $availableLanguageCode === $currentContentLanguageCode ? 'btn-primary active' : 'btn-outline-secondary' ?>"
                        >
                            <?= htmlspecialchars((string)$availableLanguageCode) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <span class="small text-muted"><?= htmlspecialchars((string)($lang['sys.translation_list_help'] ?? 'Список показывает записи выбранного языка контента.')) ?></span>
            </div>
        <?php endif; ?>
        <div class="row">
            <div class="col">
                <?= $pagesTable ?>
            </div>
        </div>
    </div>
</main>
