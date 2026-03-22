<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<?php
$docsCatalog = is_array($docs_catalog ?? null) ? $docs_catalog : [];
$docsGroups = is_array($docsCatalog['groups'] ?? null) ? $docsCatalog['groups'] : [];
$docsCurrent = is_array($docs_current ?? null) ? $docs_current : [];
$docsPagination = is_array($docs_pagination ?? null) ? $docs_pagination : ['prev' => null, 'next' => null];
$currentSlug = (string) ($docsCurrent['slug'] ?? '');
?>
<div class="docs-shell">
    <section class="docs-hero">
        <div class="docs-hero__content">
            <span class="docs-hero__eyebrow">EE_FrameWork</span>
            <h1><?= htmlspecialchars((string) ($docsCatalog['title'] ?? 'Документация EE_FrameWork'), ENT_QUOTES) ?></h1>
            <p class="docs-hero__lead">
                <?= htmlspecialchars((string) ($docsCatalog['description'] ?? 'Техническая документация по ядру, расширению и эксплуатации EE_FrameWork.'), ENT_QUOTES) ?>
            </p>
            <?php if (!empty($docsCatalog['intro'])) { ?>
                <p class="docs-hero__intro"><?= htmlspecialchars((string) $docsCatalog['intro'], ENT_QUOTES) ?></p>
            <?php } ?>
        </div>
        <div class="docs-hero__actions">
            <a href="<?= ENV_URL_SITE ?>" class="docs-home-link">
                <i class="fa-solid fa-house"></i>
                <span>На главную</span>
            </a>
            <?= $top_panel ?? '' ?>
        </div>
    </section>

    <section class="docs-layout">
        <aside class="docs-sidebar">
            <label class="docs-sidebar__search">
                <span>Быстрый поиск по разделам</span>
                <input type="search" id="docs-nav-filter" placeholder="Например: hooks, auth, import" autocomplete="off">
            </label>

            <?php foreach ($docsGroups as $group) { ?>
                <div class="docs-nav-group" data-doc-group="<?= htmlspecialchars((string) ($group['id'] ?? ''), ENT_QUOTES) ?>">
                    <div class="docs-nav-group__header">
                        <h2><?= htmlspecialchars((string) ($group['title'] ?? 'Раздел'), ENT_QUOTES) ?></h2>
                        <?php if (!empty($group['description'])) { ?>
                            <p><?= htmlspecialchars((string) $group['description'], ENT_QUOTES) ?></p>
                        <?php } ?>
                    </div>
                    <div class="docs-nav-list">
                        <?php foreach ((array) ($group['items'] ?? []) as $item) { ?>
                            <?php
                            $isActive = (string) ($item['slug'] ?? '') === $currentSlug;
                            $keywords = implode(' ', (array) ($item['keywords'] ?? []));
                            ?>
                            <a
                                href="/docs/<?= rawurlencode((string) ($item['slug'] ?? '')) ?>"
                                class="docs-nav-item<?= $isActive ? ' is-active' : '' ?>"
                                data-doc-search="<?= htmlspecialchars(mb_strtolower(trim((string) (($item['title'] ?? '') . ' ' . ($item['summary'] ?? '') . ' ' . $keywords))), ENT_QUOTES) ?>"
                            >
                                <span class="docs-nav-item__icon">
                                    <i class="fa-solid <?= htmlspecialchars((string) ($item['icon'] ?? 'fa-file-lines'), ENT_QUOTES) ?>"></i>
                                </span>
                                <span class="docs-nav-item__text">
                                    <strong><?= htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES) ?></strong>
                                    <?php if (!empty($item['summary'])) { ?>
                                        <small><?= htmlspecialchars((string) $item['summary'], ENT_QUOTES) ?></small>
                                    <?php } ?>
                                </span>
                            </a>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </aside>

        <main class="docs-article-wrap">
            <article class="docs-article">
                <header class="docs-article__header">
                    <div class="docs-article__meta">
                        <?php if (!empty($docsCurrent['group_title'])) { ?>
                            <span class="docs-badge"><?= htmlspecialchars((string) $docsCurrent['group_title'], ENT_QUOTES) ?></span>
                        <?php } ?>
                        <?php if (!empty($docsCurrent['updated_at'])) { ?>
                            <span class="docs-updated">Обновлено: <?= htmlspecialchars((string) $docsCurrent['updated_at'], ENT_QUOTES) ?></span>
                        <?php } ?>
                    </div>
                    <h2><?= htmlspecialchars((string) ($docsCurrent['title'] ?? 'Документация'), ENT_QUOTES) ?></h2>
                    <?php if (!empty($docsCurrent['summary'])) { ?>
                        <p class="docs-article__summary"><?= htmlspecialchars((string) $docsCurrent['summary'], ENT_QUOTES) ?></p>
                    <?php } ?>
                </header>

                <div class="docs-article__content" id="docs-article-content">
                    <?= (string) ($docsCurrent['html'] ?? '<p>Документ пока не найден.</p>') ?>
                </div>
            </article>

            <nav class="docs-pagination" aria-label="Навигация по документации">
                <?php if (!empty($docsPagination['prev'])) { ?>
                    <a class="docs-pagination__link" href="/docs/<?= rawurlencode((string) $docsPagination['prev']['slug']) ?>">
                        <span>Назад</span>
                        <strong><?= htmlspecialchars((string) $docsPagination['prev']['title'], ENT_QUOTES) ?></strong>
                    </a>
                <?php } else { ?>
                    <span></span>
                <?php } ?>

                <?php if (!empty($docsPagination['next'])) { ?>
                    <a class="docs-pagination__link docs-pagination__link--next" href="/docs/<?= rawurlencode((string) $docsPagination['next']['slug']) ?>">
                        <span>Далее</span>
                        <strong><?= htmlspecialchars((string) $docsPagination['next']['title'], ENT_QUOTES) ?></strong>
                    </a>
                <?php } ?>
            </nav>
        </main>
    </section>
</div>
