<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<?php
$page = is_array($publicCatalog ?? null) ? $publicCatalog : [];
$breadcrumbs = is_array($page['breadcrumbs'] ?? null) ? $page['breadcrumbs'] : [];
$languageLinks = is_array($page['language_links'] ?? null) ? $page['language_links'] : [];
$gallery = is_array($page['gallery_preview'] ?? null) ? $page['gallery_preview'] : [];
$roomOffer = array_values(array_filter(is_array($page['room_offer'] ?? null) ? $page['room_offer'] : [], static fn($item): bool => is_array($item)));
$contacts = is_array($page['contacts'] ?? null) ? $page['contacts'] : [];
$location = is_array($page['location'] ?? null) ? $page['location'] : [];
$relatedPages = is_array($page['related_pages'] ?? null) ? $page['related_pages'] : [];
$facts = is_array($page['facts'] ?? null) ? $page['facts'] : [];
$resort = is_array($page['resort'] ?? null) ? $page['resort'] : [];
$map = is_array($location['map'] ?? null) ? $location['map'] : [];
$owner = is_array($page['owner'] ?? null) ? $page['owner'] : [];
$services = array_values(array_filter(is_array($page['services'] ?? null) ? $page['services'] : []));
$food = array_values(array_filter(is_array($page['food'] ?? null) ? $page['food'] : []));
$transfer = array_values(array_filter(is_array($page['transfer'] ?? null) ? $page['transfer'] : []));
$currentLanguageCode = strtoupper((string) ($page['language_code'] ?? ee_get_current_lang_code()));
?>

<div class="container py-4 py-lg-5 ee-public-shell">
    <div class="d-flex justify-content-end mb-3">
        <?= $top_panel ?? '' ?>
    </div>

    <div class="ee-public-breadcrumbs mb-3">
        <a href="<?= htmlspecialchars((string) ENV_URL_SITE, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ENV_SITE_NAME, ENT_QUOTES, 'UTF-8') ?></a>
        <?php foreach ($breadcrumbs as $index => $breadcrumb): ?>
            <?php if (!is_array($breadcrumb) || empty($breadcrumb['title'])) { continue; } ?>
            <span class="ee-breadcrumb-sep">/</span>
            <?php $isCurrent = ((int) ($breadcrumb['entity_id'] ?? 0) === (int) ($page['entity_id'] ?? 0)); ?>
            <?php if (!$isCurrent && !empty($breadcrumb['url'])): ?>
                <a href="<?= htmlspecialchars((string) $breadcrumb['url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $breadcrumb['title'], ENT_QUOTES, 'UTF-8') ?></a>
            <?php else: ?>
                <span><?= htmlspecialchars((string) $breadcrumb['title'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <section class="ee-hero-card mb-4">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <?php if (!empty($resort['title']) && !empty($resort['url'])): ?>
                        <a class="badge rounded-pill text-bg-light border text-decoration-none" href="<?= htmlspecialchars((string) $resort['url'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars((string) $resort['title'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    <?php endif; ?>
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

                <h1 class="ee-title mb-3"><?= htmlspecialchars((string) ($page['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>

                <?php if (!empty($page['summary'])): ?>
                    <p class="ee-summary mb-0"><?= nl2br(htmlspecialchars((string) $page['summary'], ENT_QUOTES, 'UTF-8')) ?></p>
                <?php endif; ?>
            </section>

            <?php if ($gallery !== []): ?>
                <section class="ee-section-card mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="ee-section-title mb-0"><?= htmlspecialchars((string) ($lang['sys.public_gallery'] ?? 'Галерея'), ENT_QUOTES, 'UTF-8') ?></h2>
                        <span class="text-muted small"><?= count($gallery) ?> фото</span>
                    </div>
                    <div class="row g-3 ee-gallery-grid">
                        <?php foreach ($gallery as $index => $image): ?>
                            <?php
                            $imageUrl = trim((string) ($image['file_url'] ?? ''));
                            if ($imageUrl === '') { continue; }
                            $imageTitle = trim((string) ($image['original_name'] ?? ($page['title'] ?? '')));
                            ?>
                            <div class="col-6 col-md-4">
                                <a class="ee-gallery-item d-block" href="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                    <img
                                        src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>"
                                        alt="<?= htmlspecialchars($imageTitle, ENT_QUOTES, 'UTF-8') ?>"
                                        loading="lazy"
                                    />
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (!empty($page['description_html'])): ?>
                <section class="ee-section-card mb-4">
                    <h2 class="ee-section-title"><?= htmlspecialchars((string) ($lang['sys.description'] ?? 'Описание'), ENT_QUOTES, 'UTF-8') ?></h2>
                    <div class="ee-richtext">
                        <?= (string) $page['description_html'] ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($roomOffer !== []): ?>
                <section class="ee-section-card mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="ee-section-title mb-0"><?= htmlspecialchars((string) ($lang['sys.public_rooms_prices'] ?? 'Номера и цены'), ENT_QUOTES, 'UTF-8') ?></h2>
                        <span class="text-muted small"><?= count($roomOffer) ?> <?= htmlspecialchars((string) ($lang['sys.public_room_variants'] ?? 'вариантов размещения'), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="ee-room-list">
                        <?php foreach ($roomOffer as $roomIndex => $roomItem): ?>
                            <article class="ee-room-card">
                                <?php if (!empty($roomItem['name'])): ?>
                                    <h3 class="ee-subtitle mb-3"><?= htmlspecialchars((string) $roomItem['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                                <?php endif; ?>

                                <?php if (!empty($roomItem['facts'])): ?>
                                    <div class="ee-facts-grid mb-3">
                                        <?php foreach ($roomItem['facts'] as $fact): ?>
                                            <?php if (empty($fact['label']) || $fact['value'] === '') { continue; } ?>
                                            <div class="ee-fact-tile">
                                                <span class="ee-fact-label"><?= htmlspecialchars((string) $fact['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <strong><?= htmlspecialchars((string) $fact['value'], ENT_QUOTES, 'UTF-8') ?></strong>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($roomItem['amenities'])): ?>
                                    <div class="ee-chip-list mb-3">
                                        <?php foreach ($roomItem['amenities'] as $item): ?>
                                            <span class="ee-chip"><?= htmlspecialchars((string) $item, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($roomItem['description_html'])): ?>
                                    <div class="ee-richtext mb-4">
                                        <?= (string) $roomItem['description_html'] ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($roomItem['gallery'])): ?>
                                    <div class="row g-3 ee-gallery-grid mb-4">
                                        <?php foreach ($roomItem['gallery'] as $image): ?>
                                            <?php if (empty($image['file_url'])) { continue; } ?>
                                            <div class="col-6 col-md-4">
                                                <a class="ee-gallery-item d-block" href="<?= htmlspecialchars((string) $image['file_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                    <img
                                                        src="<?= htmlspecialchars((string) $image['file_url'], ENT_QUOTES, 'UTF-8') ?>"
                                                        alt="<?= htmlspecialchars((string) ($image['original_name'] ?? ($roomItem['name'] ?? 'Номер')), ENT_QUOTES, 'UTF-8') ?>"
                                                        loading="lazy"
                                                    />
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($roomItem['periods'])): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle ee-price-table">
                                            <thead>
                                                <tr>
                                                    <th><?= htmlspecialchars((string) ($lang['sys.public_period'] ?? 'Период'), ENT_QUOTES, 'UTF-8') ?></th>
                                                    <th><?= htmlspecialchars((string) ($lang['sys.public_price'] ?? 'Цена'), ENT_QUOTES, 'UTF-8') ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($roomItem['periods'] as $period): ?>
                                                    <tr>
                                                        <td>
                                                            <?= htmlspecialchars(trim((string) ($period['from'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                                                            <?php if (!empty($period['to'])): ?>
                                                                <span class="text-muted">-</span>
                                                                <?= htmlspecialchars(trim((string) $period['to']), ENT_QUOTES, 'UTF-8') ?>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if (!empty($period['price'])): ?>
                                                                <strong><?= htmlspecialchars((string) $period['price'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                                <?php if (!empty($roomItem['price_mode'])): ?>
                                                                    <span class="text-muted small d-block"><?= htmlspecialchars((string) $roomItem['price_mode'], ENT_QUOTES, 'UTF-8') ?></span>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!empty($page['price_comment_html'])): ?>
                        <div class="ee-note mt-3">
                            <?= (string) $page['price_comment_html'] ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($relatedPages !== []): ?>
                <section class="ee-section-card">
                    <h2 class="ee-section-title"><?= htmlspecialchars((string) ($lang['sys.public_related_objects'] ?? 'Другие объекты на курорте'), ENT_QUOTES, 'UTF-8') ?></h2>
                    <div class="row g-3">
                        <?php foreach ($relatedPages as $relatedPage): ?>
                            <div class="col-md-6">
                                <a class="ee-listing-card d-block text-decoration-none" href="<?= htmlspecialchars((string) ($relatedPage['url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                    <?php if (!empty($relatedPage['image']['file_url'])): ?>
                                        <img
                                            class="ee-listing-thumb"
                                            src="<?= htmlspecialchars((string) $relatedPage['image']['file_url'], ENT_QUOTES, 'UTF-8') ?>"
                                            alt="<?= htmlspecialchars((string) ($relatedPage['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            loading="lazy"
                                        />
                                    <?php endif; ?>
                                    <div class="ee-listing-body">
                                        <h3><?= htmlspecialchars((string) ($relatedPage['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h3>
                                        <?php if (!empty($relatedPage['object_type']) || !empty($relatedPage['distance_to_sea'])): ?>
                                            <div class="ee-listing-meta">
                                                <?php if (!empty($relatedPage['object_type'])): ?>
                                                    <span><?= htmlspecialchars((string) $relatedPage['object_type'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($relatedPage['distance_to_sea'])): ?>
                                                    <span><?= htmlspecialchars((string) $relatedPage['distance_to_sea'], ENT_QUOTES, 'UTF-8') ?> м</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($relatedPage['summary'])): ?>
                                            <p><?= htmlspecialchars((string) $relatedPage['summary'], ENT_QUOTES, 'UTF-8') ?></p>
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
                <section class="ee-section-card">
                    <h2 class="ee-section-title"><?= htmlspecialchars((string) ($lang['sys.public_contacts'] ?? 'Контакты'), ENT_QUOTES, 'UTF-8') ?></h2>
                    <div class="ee-contact-list">
                        <?php foreach ((array) ($contacts['phones'] ?? []) as $phone): ?>
                            <?php if (empty($phone['phone'])) { continue; } ?>
                            <div class="ee-contact-row">
                                <span class="ee-contact-label"><?= htmlspecialchars((string) ($lang['sys.phone'] ?? 'Телефон'), ENT_QUOTES, 'UTF-8') ?></span>
                                <a href="<?= htmlspecialchars((string) ($phone['tel_href'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $phone['phone'], ENT_QUOTES, 'UTF-8') ?></a>
                                <?php if (!empty($phone['comment'])): ?>
                                    <small><?= htmlspecialchars((string) $phone['comment'], ENT_QUOTES, 'UTF-8') ?></small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <?php if (!empty($contacts['email'])): ?>
                            <div class="ee-contact-row">
                                <span class="ee-contact-label"><?= htmlspecialchars((string) ($lang['sys.email'] ?? 'Почта'), ENT_QUOTES, 'UTF-8') ?></span>
                                <a href="<?= htmlspecialchars((string) ($contacts['email_href'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $contacts['email'], ENT_QUOTES, 'UTF-8') ?></a>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($contacts['website'])): ?>
                            <div class="ee-contact-row">
                                <span class="ee-contact-label"><?= htmlspecialchars((string) ($lang['sys.public_website'] ?? 'Сайт'), ENT_QUOTES, 'UTF-8') ?></span>
                                <a href="<?= htmlspecialchars((string) $contacts['website'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars((string) preg_replace('~^https?://~', '', (string) $contacts['website']), ENT_QUOTES, 'UTF-8') ?></a>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($contacts['address'])): ?>
                            <div class="ee-contact-row">
                                <span class="ee-contact-label"><?= htmlspecialchars((string) ($lang['sys.public_address'] ?? 'Адрес'), ENT_QUOTES, 'UTF-8') ?></span>
                                <strong><?= htmlspecialchars((string) $contacts['address'], ENT_QUOTES, 'UTF-8') ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <?php if ($facts !== [] || $services !== [] || $food !== [] || $transfer !== []): ?>
                    <section class="ee-section-card">
                        <h2 class="ee-section-title"><?= htmlspecialchars((string) ($lang['sys.public_details'] ?? 'Полезные детали'), ENT_QUOTES, 'UTF-8') ?></h2>
                        <?php if ($facts !== []): ?>
                            <div class="ee-fact-list mb-3">
                                <?php foreach ($facts as $fact): ?>
                                    <div class="ee-fact-row">
                                        <span><?= htmlspecialchars((string) ($fact['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                        <strong><?= htmlspecialchars((string) ($fact['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php foreach ([
                            ['label' => $lang['sys.public_services'] ?? 'Услуги', 'items' => $services],
                            ['label' => $lang['sys.public_food'] ?? 'Питание', 'items' => $food],
                            ['label' => $lang['sys.public_transfer'] ?? 'Трансфер', 'items' => $transfer],
                        ] as $group): ?>
                            <?php if (empty($group['items'])) { continue; } ?>
                            <div class="mb-3">
                                <h3 class="ee-minor-title"><?= htmlspecialchars((string) $group['label'], ENT_QUOTES, 'UTF-8') ?></h3>
                                <div class="ee-chip-list">
                                    <?php foreach ($group['items'] as $item): ?>
                                        <span class="ee-chip"><?= htmlspecialchars((string) $item, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>

                <?php if ($map !== []): ?>
                    <section class="ee-section-card">
                        <h2 class="ee-section-title"><?= htmlspecialchars((string) ($lang['sys.public_location'] ?? 'Расположение'), ENT_QUOTES, 'UTF-8') ?></h2>
                        <?php if (!empty($location['distance_to_sea'])): ?>
                            <div class="ee-fact-row mb-3">
                                <span><?= htmlspecialchars((string) ($lang['sys.public_distance_to_sea'] ?? 'До моря'), ENT_QUOTES, 'UTF-8') ?></span>
                                <strong><?= htmlspecialchars((string) $location['distance_to_sea'], ENT_QUOTES, 'UTF-8') ?> м</strong>
                            </div>
                        <?php endif; ?>
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

                <?php if (!empty($owner['name'])): ?>
                    <section class="ee-section-card">
                        <h2 class="ee-section-title"><?= htmlspecialchars((string) ($lang['sys.public_owner'] ?? 'Владелец карточки'), ENT_QUOTES, 'UTF-8') ?></h2>
                        <div class="ee-owner-box">
                            <strong><?= htmlspecialchars((string) $owner['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <?php if (!empty($owner['email'])): ?>
                                <a href="mailto:<?= htmlspecialchars((string) $owner['email'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $owner['email'], ENT_QUOTES, 'UTF-8') ?></a>
                            <?php endif; ?>
                            <?php if (!empty($owner['phone']) && !empty($owner['phone_href'])): ?>
                                <a href="<?= htmlspecialchars((string) $owner['phone_href'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $owner['phone'], ENT_QUOTES, 'UTF-8') ?></a>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</div>
