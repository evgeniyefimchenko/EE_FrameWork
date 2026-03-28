<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<?php
$category = is_array($publicCatalog ?? null) ? $publicCatalog : [];
$breadcrumbs = is_array($category['breadcrumbs'] ?? null) ? $category['breadcrumbs'] : [];
$languageLinks = is_array($category['language_links'] ?? null) ? $category['language_links'] : [];
$gallery = is_array($category['gallery_preview'] ?? null) ? $category['gallery_preview'] : [];
$childCategories = is_array($category['child_categories'] ?? null) ? $category['child_categories'] : [];
$pages = is_array($category['pages'] ?? null) ? $category['pages'] : [];
$map = is_array($category['map'] ?? null) ? $category['map'] : [];
$currentLanguageCode = strtoupper((string) ($category['language_code'] ?? ee_get_current_lang_code()));
?>

<div class="container py-4 py-lg-5 ee-public-shell">
    <div class="d-flex justify-content-end mb-3">
        <?= $top_panel ?? '' ?>
    </div>

    <div class="ee-public-breadcrumbs mb-3">
        <a href="<?= htmlspecialchars((string) ENV_URL_SITE, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ENV_SITE_NAME, ENT_QUOTES, 'UTF-8') ?></a>
        <?php foreach ($breadcrumbs as $breadcrumb): ?>
            <?php if (!is_array($breadcrumb) || empty($breadcrumb['title'])) { continue; } ?>
            <span class="ee-breadcrumb-sep">/</span>
            <?php $isCurrent = ((int) ($breadcrumb['entity_id'] ?? 0) === (int) ($category['entity_id'] ?? 0)); ?>
            <?php if (!$isCurrent && !empty($breadcrumb['url'])): ?>
                <a href="<?= htmlspecialchars((string) $breadcrumb['url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $breadcrumb['title'], ENT_QUOTES, 'UTF-8') ?></a>
            <?php else: ?>
                <span><?= htmlspecialchars((string) $breadcrumb['title'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <section class="ee-hero-card mb-4">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <span class="badge rounded-pill text-bg-light border"><?= htmlspecialchars((string) ($lang['sys.public_resort'] ?? 'Курорт'), ENT_QUOTES, 'UTF-8') ?></span>
            <?php if (count($languageLinks) > 1): ?>
                <?php foreach ($languageLinks as $languageLink): ?>
                    <?php
                    $languageHref = trim((string) ($languageLink['href'] ?? ''));
                    $languageCode = strtoupper((string) ($languageLink['language_code'] ?? ''));
                    if ($languageHref === '' || $languageCode === '') { continue; }
                    ?>
                    <a
                        href="<?= htmlspecialchars($languageHref, ENT_QUOTES, 'UTF-8') ?>"
                        class="badge rounded-pill <?= $languageCode === $currentLanguageCode ? 'text-bg-primary' : 'text-bg-secondary' ?> text-decoration-none"
                    ><?= htmlspecialchars($languageCode, ENT_QUOTES, 'UTF-8') ?></a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <h1 class="ee-title mb-3"><?= htmlspecialchars((string) ($category['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>
        <?php if (!empty($category['summary'])): ?>
            <p class="ee-summary mb-0"><?= nl2br(htmlspecialchars((string) $category['summary'], ENT_QUOTES, 'UTF-8')) ?></p>
        <?php endif; ?>
    </section>

    <?php if ($gallery !== []): ?>
        <section class="ee-section-card mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="ee-section-title mb-0"><?= htmlspecialchars((string) ($lang['sys.public_gallery'] ?? 'Галерея'), ENT_QUOTES, 'UTF-8') ?></h2>
                <span class="text-muted small"><?= count($gallery) ?> фото</span>
            </div>
            <div class="row g-3 ee-gallery-grid">
                <?php foreach ($gallery as $image): ?>
                    <?php if (empty($image['file_url'])) { continue; } ?>
                    <div class="col-6 col-md-3">
                        <a class="ee-gallery-item d-block" href="<?= htmlspecialchars((string) $image['file_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                            <img
                                src="<?= htmlspecialchars((string) $image['file_url'], ENT_QUOTES, 'UTF-8') ?>"
                                alt="<?= htmlspecialchars((string) ($image['original_name'] ?? ($category['title'] ?? 'Курорт')), ENT_QUOTES, 'UTF-8') ?>"
                                loading="lazy"
                            />
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <?php if (!empty($category['overview_html'])): ?>
                <section class="ee-section-card mb-4">
                    <h2 class="ee-section-title"><?= htmlspecialchars((string) ($lang['sys.public_resort_overview'] ?? 'О курорте'), ENT_QUOTES, 'UTF-8') ?></h2>
                    <div class="ee-richtext">
                        <?= (string) $category['overview_html'] ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (!empty($category['left_block_html']) || !empty($category['right_block_html'])): ?>
                <section class="ee-section-card mb-4">
                    <div class="row g-4">
                        <?php if (!empty($category['left_block_html'])): ?>
                            <div class="col-lg-6">
                                <div class="ee-richtext">
                                    <?= (string) $category['left_block_html'] ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($category['right_block_html'])): ?>
                            <div class="col-lg-6">
                                <div class="ee-richtext">
                                    <?= (string) $category['right_block_html'] ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($pages !== []): ?>
                <section class="ee-section-card mb-4">
                    <h2 class="ee-section-title"><?= htmlspecialchars((string) ($lang['sys.public_objects_in_resort'] ?? 'Объекты на курорте'), ENT_QUOTES, 'UTF-8') ?></h2>
                    <div class="row g-3">
                        <?php foreach ($pages as $pageCard): ?>
                            <div class="col-md-6">
                                <a class="ee-listing-card d-block text-decoration-none" href="<?= htmlspecialchars((string) ($pageCard['url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                    <?php if (!empty($pageCard['image']['file_url'])): ?>
                                        <img
                                            class="ee-listing-thumb"
                                            src="<?= htmlspecialchars((string) $pageCard['image']['file_url'], ENT_QUOTES, 'UTF-8') ?>"
                                            alt="<?= htmlspecialchars((string) ($pageCard['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            loading="lazy"
                                        />
                                    <?php endif; ?>
                                    <div class="ee-listing-body">
                                        <h3><?= htmlspecialchars((string) ($pageCard['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h3>
                                        <?php if (!empty($pageCard['object_type']) || !empty($pageCard['distance_to_sea'])): ?>
                                            <div class="ee-listing-meta">
                                                <?php if (!empty($pageCard['object_type'])): ?>
                                                    <span><?= htmlspecialchars((string) $pageCard['object_type'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($pageCard['distance_to_sea'])): ?>
                                                    <span><?= htmlspecialchars((string) $pageCard['distance_to_sea'], ENT_QUOTES, 'UTF-8') ?> м</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($pageCard['summary'])): ?>
                                            <p><?= htmlspecialchars((string) $pageCard['summary'], ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <aside class="d-grid gap-4">
                <?php if ($childCategories !== []): ?>
                    <section class="ee-section-card">
                        <h2 class="ee-section-title"><?= htmlspecialchars((string) ($lang['sys.public_child_resorts'] ?? 'Курорты раздела'), ENT_QUOTES, 'UTF-8') ?></h2>
                        <div class="d-grid gap-3">
                            <?php foreach ($childCategories as $child): ?>
                                <a class="ee-listing-card d-block text-decoration-none" href="<?= htmlspecialchars((string) ($child['url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                    <?php if (!empty($child['image']['file_url'])): ?>
                                        <img
                                            class="ee-listing-thumb"
                                            src="<?= htmlspecialchars((string) $child['image']['file_url'], ENT_QUOTES, 'UTF-8') ?>"
                                            alt="<?= htmlspecialchars((string) ($child['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            loading="lazy"
                                        />
                                    <?php endif; ?>
                                    <div class="ee-listing-body">
                                        <h3><?= htmlspecialchars((string) ($child['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h3>
                                        <div class="ee-listing-meta">
                                            <span><?= (int) ($child['pages_total'] ?? 0) ?> <?= htmlspecialchars((string) ($lang['sys.public_objects_count'] ?? 'объектов'), ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <?php if (!empty($child['summary'])): ?>
                                            <p><?= htmlspecialchars((string) $child['summary'], ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($map !== []): ?>
                    <section class="ee-section-card">
                        <h2 class="ee-section-title"><?= htmlspecialchars((string) ($lang['sys.public_map'] ?? 'Карта'), ENT_QUOTES, 'UTF-8') ?></h2>
                        <div class="ee-map-box mb-3">
                            <div class="ee-map-coords"><?= htmlspecialchars((string) ($map['coords'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <?php if (!empty($map['google_url'])): ?>
                                <a class="btn btn-outline-primary btn-sm" href="<?= htmlspecialchars((string) $map['google_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Google Maps</a>
                            <?php endif; ?>
                            <?php if (!empty($map['yandex_url'])): ?>
                                <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars((string) $map['yandex_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Yandex Maps</a>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</div>
