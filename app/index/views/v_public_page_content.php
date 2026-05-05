<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<?php
$page = is_array($publicCatalog ?? null) ? $publicCatalog : [];
$breadcrumbs = is_array($page['breadcrumbs'] ?? null) ? $page['breadcrumbs'] : [];
$recentArticles = array_values(array_filter(is_array($page['recent_articles'] ?? null) ? $page['recent_articles'] : [], static fn($item): bool => is_array($item)));
$heroImage = trim((string) ($page['hero_image'] ?? ''));
if ($heroImage === '') {
    $heroImage = ENV_URL_SITE . '/assets/vendor/tourm/img/bg/breadcumb-bg.jpg';
}
$isBlogArticle = (string) ($page['content_kind'] ?? '') === 'blog_article';
$section = is_array($page['section'] ?? null) ? $page['section'] : [];
?>

<?= $top_panel ?? '' ?>

<main class="allbriz-public-main">
    <section class="breadcumb-wrapper allbriz-breadcrumb-hero" data-bg-src="<?= htmlspecialchars($heroImage, ENT_QUOTES, 'UTF-8') ?>">
        <div class="container">
            <div class="breadcumb-content">
                <h1 class="breadcumb-title"><?= htmlspecialchars((string) ($page['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>
                <ul class="breadcumb-menu">
                    <li><a href="<?= htmlspecialchars((string) ENV_URL_SITE, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ENV_SITE_NAME, ENT_QUOTES, 'UTF-8') ?></a></li>
                    <?php foreach ($breadcrumbs as $breadcrumb): ?>
                        <?php if (!is_array($breadcrumb) || empty($breadcrumb['title'])) { continue; } ?>
                        <?php $isCurrent = ((int) ($breadcrumb['entity_id'] ?? 0) === (int) ($page['entity_id'] ?? 0)); ?>
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

    <section class="th-blog-wrapper blog-details space-top space-extra-bottom">
        <div class="container">
            <div class="row">
                <div class="<?= $isBlogArticle ? 'col-xxl-8 col-lg-7' : 'col-12' ?>">
                    <div class="th-blog blog-single allbriz-page-card">
                        <?php if ($isBlogArticle): ?>
                            <div class="blog-img">
                                <img src="<?= htmlspecialchars($heroImage, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($page['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        <?php endif; ?>
                        <div class="blog-content">
                            <?php if ($isBlogArticle): ?>
                                <div class="blog-meta">
                                    <?php if (!empty($page['published_at_pretty'])): ?>
                                        <span><i class="fa-solid fa-calendar-days"></i><?= htmlspecialchars((string) $page['published_at_pretty'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($section['title']) && !empty($section['url'])): ?>
                                        <a href="<?= htmlspecialchars((string) $section['url'], ENT_QUOTES, 'UTF-8') ?>"><i class="fa-light fa-folder"></i><?= htmlspecialchars((string) $section['title'], ENT_QUOTES, 'UTF-8') ?></a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($page['summary'])): ?>
                                <p class="blog-text mb-30"><?= nl2br(htmlspecialchars((string) $page['summary'], ENT_QUOTES, 'UTF-8')) ?></p>
                            <?php endif; ?>

                            <?php if (!empty($page['description_html'])): ?>
                                <div class="allbriz-richtext"><?= (string) $page['description_html'] ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($isBlogArticle && $recentArticles !== []): ?>
                    <div class="col-xxl-4 col-lg-5">
                        <aside class="sidebar-area">
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
                        </aside>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<?= $footer_public ?? '' ?>
