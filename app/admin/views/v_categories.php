<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<!-- Таблица категорий -->
<main>
    <div class="container-fluid px-4">
        <?php
        $availableLanguageCodes = is_array($availableLanguageCodes ?? null) ? $availableLanguageCodes : [];
        $currentUiLanguageCode = strtoupper((string) ($currentUiLanguageCode ?? (\classes\system\Session::get('lang') ?: ENV_DEF_LANG)));
        $languageSwitchBaseUrl = (string) ($languageSwitchBaseUrl ?? '/admin/categories');
        ?>
        <a href="/admin/category_edit/id?language_code=<?= rawurlencode((string) $currentUiLanguageCode) ?>" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= $lang['sys.add'] ?>" type="button"
           class="btn btn-info mx-1 float-end">
            <i class="fa fa-plus-circle"></i>&nbsp;<?= $lang['sys.add'] ?>
        </a>        
        <h1 class="mt-4"><?= $lang['sys.categories'] ?></h1>
        <?php if (!empty($availableLanguageCodes)): ?>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <span class="small text-muted"><?= htmlspecialchars((string)($lang['sys.language'] ?? 'Язык')) ?>:</span>
                <div class="btn-group btn-group-sm" role="group" aria-label="<?= htmlspecialchars((string)($lang['sys.language'] ?? 'Язык')) ?>">
                    <?php foreach ($availableLanguageCodes as $availableLanguageCode): ?>
                        <a
                            href="<?= htmlspecialchars($languageSwitchBaseUrl . '?ui_lang=' . rawurlencode((string)$availableLanguageCode), ENT_QUOTES, 'UTF-8') ?>"
                            class="btn <?= $availableLanguageCode === $currentUiLanguageCode ? 'btn-primary active' : 'btn-outline-secondary' ?>"
                        >
                            <?= htmlspecialchars((string)$availableLanguageCode) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <span class="small text-muted"><?= htmlspecialchars((string)($lang['sys.translation_list_help'] ?? 'Список показывает записи в выбранной локали интерфейса.')) ?></span>
            </div>
        <?php endif; ?>
        <div class="row">
            <div class="col">
                <?= $categories_table ?>
            </div>
        </div>
    </div>
</main>
