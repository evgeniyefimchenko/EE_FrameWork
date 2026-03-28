<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<?php
$publicEntity = is_array($publicEntity ?? null) ? $publicEntity : [];
$breadcrumbs = is_array($publicEntity['breadcrumbs'] ?? null) ? $publicEntity['breadcrumbs'] : [];
$alternateLinks = array_values(array_filter(
    is_array($publicEntity['alternate_links'] ?? null) ? $publicEntity['alternate_links'] : [],
    static fn($item): bool => is_array($item) && !empty($item['language_code']) && strtoupper((string) ($item['language_code'] ?? '')) !== 'X-DEFAULT'
));
$currentLanguageCode = strtoupper((string) ($publicEntity['language_code'] ?? ee_get_current_lang_code()));
$entityType = (string) ($publicEntity['entity_type'] ?? '');
$entityTitle = trim((string) ($publicEntity['title'] ?? ''));
$shortDescription = trim((string) ($publicEntity['short_description'] ?? ''));
$descriptionHtml = (string) ($publicEntity['description_html'] ?? '');
$entityLabel = $entityType === 'category'
    ? (string) ($lang['sys.categories'] ?? 'Categories')
    : (string) ($lang['sys.pages'] ?? 'Pages');
?>

<div class="container py-4 py-lg-5">
    <div class="mb-4 text-center">
        <?= $top_panel ?? '' ?>
    </div>

    <div class="row justify-content-center">
        <div class="col-xl-10">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb small">
                    <li class="breadcrumb-item">
                        <a href="<?= htmlspecialchars((string) ENV_URL_SITE, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ENV_SITE_NAME, ENT_QUOTES, 'UTF-8') ?></a>
                    </li>
                    <?php foreach ($breadcrumbs as $breadcrumb): ?>
                        <?php if (!is_array($breadcrumb) || empty($breadcrumb['title'])) { continue; } ?>
                        <?php $breadcrumbUrl = trim((string) ($breadcrumb['url'] ?? '')); ?>
                        <?php $isCurrent = (int) ($breadcrumb['entity_id'] ?? 0) === (int) ($publicEntity['entity_id'] ?? 0); ?>
                        <li class="breadcrumb-item<?= $isCurrent ? ' active' : '' ?>"<?= $isCurrent ? ' aria-current="page"' : '' ?>>
                            <?php if (!$isCurrent && $breadcrumbUrl !== ''): ?>
                                <a href="<?= htmlspecialchars($breadcrumbUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $breadcrumb['title'], ENT_QUOTES, 'UTF-8') ?></a>
                            <?php else: ?>
                                <?= htmlspecialchars((string) $breadcrumb['title'], ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </nav>

            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-lg-5">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <span class="badge text-bg-light border"><?= htmlspecialchars($entityLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="badge text-bg-primary"><?= htmlspecialchars($currentLanguageCode, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php foreach ($alternateLinks as $alternateLink): ?>
                            <?php
                            $alternateCode = strtoupper((string) ($alternateLink['language_code'] ?? ''));
                            $alternateHref = trim((string) ($alternateLink['href'] ?? ''));
                            $isCurrentLanguage = $alternateCode === $currentLanguageCode;
                            ?>
                            <?php if ($alternateHref === '') { continue; } ?>
                            <a
                                href="<?= htmlspecialchars($alternateHref, ENT_QUOTES, 'UTF-8') ?>"
                                class="badge <?= $isCurrentLanguage ? 'text-bg-primary' : 'text-bg-secondary' ?> text-decoration-none"
                            ><?= htmlspecialchars($alternateCode, ENT_QUOTES, 'UTF-8') ?></a>
                        <?php endforeach; ?>
                    </div>

                    <h1 class="display-6 mb-3"><?= htmlspecialchars($entityTitle, ENT_QUOTES, 'UTF-8') ?></h1>

                    <?php if ($shortDescription !== ''): ?>
                        <div class="lead text-muted mb-4"><?= nl2br(htmlspecialchars($shortDescription, ENT_QUOTES, 'UTF-8')) ?></div>
                    <?php endif; ?>

                    <?php if ($descriptionHtml !== ''): ?>
                        <div class="content">
                            <?= $descriptionHtml ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
