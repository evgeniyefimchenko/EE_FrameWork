<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<?php
$category = is_array($publicCatalog ?? null) ? $publicCatalog : [];
$breadcrumbs = is_array($category['breadcrumbs'] ?? null) ? $category['breadcrumbs'] : [];
$articles = array_values(array_filter(is_array($category['articles'] ?? null) ? $category['articles'] : [], static fn($item): bool => is_array($item)));
$recentArticles = array_values(array_filter(is_array($category['recent_articles'] ?? null) ? $category['recent_articles'] : [], static fn($item): bool => is_array($item)));
$pagination = is_array($category['pagination'] ?? null) ? $category['pagination'] : [];
$heroImage = trim((string) ($category['hero_image'] ?? ''));
if ($heroImage === '') {
    $heroImage = ENV_URL_SITE . '/assets/vendor/tourm/img/bg/breadcumb-bg.jpg';
}
?>

<?= $top_panel ?? '' ?>

<main class="allbriz-public-main">
    <section class="breadcumb-wrapper allbriz-breadcrumb-hero" data-bg-src="<?= htmlspecialchars($heroImage, ENT_QUOTES, 'UTF-8') ?>">
        <div class="container">
            <div class="breadcumb-content">
                <h1 class="breadcumb-title"><?= htmlspecialchars((string) ($category['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>
                <ul class="breadcumb-menu">
                    <li><a href="<?= htmlspecialchars((string) ENV_URL_SITE, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ENV_SITE_NAME, ENT_QUOTES, 'UTF-8') ?></a></li>
                    <?php foreach ($breadcrumbs as $breadcrumb): ?>
                        <?php if (!is_array($breadcrumb) || empty($breadcrumb['title'])) { continue; } ?>
                        <?php $isCurrent = ((int) ($breadcrumb['entity_id'] ?? 0) === (int) ($category['entity_id'] ?? 0)); ?>
                        <?php if (!$isCurrent && !empty($breadcrumb['url'])): ?>
                            <li><a href="<?= htmlspecialchars((string) $breadcrumb['url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $breadcrumb['title'], ENT_QUOTES, 'UTF-8') ?></a></li>
                        <?php else: ?>
                            <li><?= htmlspecialchars((string) $breadcrumb['title'], ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </section>

    <section class="th-blog-wrapper space-top space-extra-bottom">
        <div class="container">
            <div class="row">
                <div class="col-xxl-8 col-lg-7">
                    <?php if (!empty($category['overview_html'])): ?>
                        <div class="allbriz-page-card mb-4">
                            <div class="page-content d-block">
                                <div class="allbriz-richtext"><?= (string) $category['overview_html'] ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($articles as $article): ?>
                        <div class="th-blog blog-single has-post-thumbnail">
                            <?php if (!empty($article['image_url'])): ?>
                                <div class="blog-img">
                                    <a href="<?= htmlspecialchars((string) ($article['url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <img src="<?= htmlspecialchars((string) $article['image_url'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($article['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                    </a>
                                </div>
                            <?php endif; ?>
                            <div class="blog-content">
                                <div class="blog-meta">
                                    <?php if (!empty($article['published_at_pretty'])): ?>
                                        <span><i class="fa-solid fa-calendar-days"></i><?= htmlspecialchars((string) $article['published_at_pretty'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                    <span><i class="fa-light fa-folder"></i><?= htmlspecialchars((string) ($category['title'] ?? 'Блог'), ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <h2 class="blog-title">
                                    <a href="<?= htmlspecialchars((string) ($article['url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string) ($article['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                </h2>
                                <?php if (!empty($article['summary'])): ?>
                                    <p class="blog-text"><?= htmlspecialchars((string) $article['summary'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                                <a href="<?= htmlspecialchars((string) ($article['url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="th-btn style4 th-icon">Читать статью</a>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($articles === []): ?>
                        <div class="alert alert-secondary">
                            Статьи готовятся к публикации.
                        </div>
                    <?php endif; ?>

                    <?php if ((int) ($pagination['total_pages'] ?? 1) > 1): ?>
                        <nav class="allbriz-blog-pagination mt-4" aria-label="Навигация по статьям">
                            <ul class="pagination justify-content-center flex-wrap gap-2">
                                <li class="page-item <?= empty($pagination['prev_href']) ? 'disabled' : '' ?>">
                                    <?php if (!empty($pagination['prev_href'])): ?>
                                        <a class="page-link" href="<?= htmlspecialchars((string) $pagination['prev_href'], ENT_QUOTES, 'UTF-8') ?>">Предыдущая</a>
                                    <?php else: ?>
                                        <span class="page-link">Предыдущая</span>
                                    <?php endif; ?>
                                </li>
                                <?php foreach ((array) ($pagination['links'] ?? []) as $link): ?>
                                    <?php if (!is_array($link)) { continue; } ?>
                                    <?php if (!empty($link['is_gap'])): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                        <?php continue; ?>
                                    <?php endif; ?>
                                    <li class="page-item <?= !empty($link['is_current']) ? 'active' : '' ?>">
                                        <?php if (!empty($link['is_current'])): ?>
                                            <span class="page-link"><?= htmlspecialchars((string) ($link['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php else: ?>
                                            <a class="page-link" href="<?= htmlspecialchars((string) ($link['href'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($link['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                                <li class="page-item <?= empty($pagination['next_href']) ? 'disabled' : '' ?>">
                                    <?php if (!empty($pagination['next_href'])): ?>
                                        <a class="page-link" href="<?= htmlspecialchars((string) $pagination['next_href'], ENT_QUOTES, 'UTF-8') ?>">Следующая</a>
                                    <?php else: ?>
                                        <span class="page-link">Следующая</span>
                                    <?php endif; ?>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>

                <div class="col-xxl-4 col-lg-5">
                    <aside class="sidebar-area">
                        <?php if (!empty($category['summary'])): ?>
                            <div class="widget">
                                <h3 class="widget_title">О разделе</h3>
                                <p class="mb-0"><?= htmlspecialchars((string) $category['summary'], ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if ($recentArticles !== []): ?>
                            <div class="widget">
                                <h3 class="widget_title">Свежие статьи</h3>
                                <div class="recent-post-wrap">
                                    <?php foreach ($recentArticles as $article): ?>
                                        <div class="recent-post">
                                            <div class="media-img">
                                                <a href="<?= htmlspecialchars((string) ($article['url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                    <img src="<?= htmlspecialchars((string) ($article['image_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($article['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                </a>
                                            </div>
                                            <div class="media-body">
                                                <?php if (!empty($article['published_at_pretty'])): ?>
                                                    <div class="recent-post-meta">
                                                        <span><i class="far fa-calendar"></i><?= htmlspecialchars((string) $article['published_at_pretty'], ENT_QUOTES, 'UTF-8') ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                <h4 class="post-title">
                                                    <a class="text-inherit" href="<?= htmlspecialchars((string) ($article['url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                        <?= htmlspecialchars((string) ($article['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                    </a>
                                                </h4>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </aside>
                </div>
            </div>
        </div>
    </section>
</main>

<?= $footer_public ?? '' ?>
