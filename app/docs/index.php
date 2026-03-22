<?php

use classes\system\ControllerBase;
use classes\system\Hook;
use classes\system\SysClass;

class ControllerDocs extends ControllerBase {

    private function getStandardViews(): void {
        Hook::run('C_beforeGetStandardViews', $this->view);
        $this->view->set('logged_in', $this->logged_in);
        $this->view->set(
            'top_panel',
            $this->view->read(ENV_SITE_PATH . 'app/index/views/v_top_panel', false, '', true)
        );
        $this->parameters_layout['add_script'] .= '<script src="' . $this->getPathController() . '/js/index.js" type="text/javascript"></script>';
        $this->parameters_layout['add_style'] .= '<link rel="stylesheet" type="text/css" href="' . $this->getPathController() . '/css/index.css"/>';
        Hook::run('C_afterGetStandardViews', $this->view);
    }

    public function index($params = []): void {
        $requestedSlug = '';
        if (is_array($params) && isset($params[0])) {
            $requestedSlug = $this->normalizeSlug((string) $params[0]);
        } elseif (!empty($_GET['doc'])) {
            $requestedSlug = $this->normalizeSlug((string) $_GET['doc']);
        }

        $catalog = $this->getDocsCatalog();
        $defaultSlug = (string) ($catalog['default_slug'] ?? '');
        if ($defaultSlug === '' && !empty($catalog['ordered_slugs'][0])) {
            $defaultSlug = (string) $catalog['ordered_slugs'][0];
        }

        $activeSlug = $requestedSlug !== '' ? $requestedSlug : $defaultSlug;
        $currentDocument = $this->resolveDocument($activeSlug, $catalog);
        $aliases = is_array($catalog['aliases'] ?? null) ? $catalog['aliases'] : [];
        $resolvedRequestedSlug = $requestedSlug !== '' && isset($aliases[$requestedSlug])
            ? (string) $aliases[$requestedSlug]
            : $requestedSlug;
        $missingRequested = $requestedSlug !== '' && (string) ($currentDocument['slug'] ?? '') !== $resolvedRequestedSlug;
        $missingDocumentFile = !empty($currentDocument['_file_missing']);

        if ($missingRequested || $missingDocumentFile) {
            SysClass::handleRedirect(404);
            return;
        }

        $pagination = $this->buildPagination($catalog, (string) ($currentDocument['slug'] ?? ''));

        $this->getStandardViews();
        $this->view->set('docs_catalog', $catalog);
        $this->view->set('docs_current', $currentDocument);
        $this->view->set('docs_pagination', $pagination);
        $this->view->set('docs_missing_requested', false);
        $this->html = $this->view->read('v_index');

        $pageTitle = trim((string) ($currentDocument['title'] ?? 'Документация'));
        $pageDescription = trim((string) ($currentDocument['description'] ?? $catalog['description'] ?? 'Документация EE_FrameWork'));

        $this->parameters_layout['title'] = 'EE_FrameWork - ' . $pageTitle;
        $this->parameters_layout['description'] = $pageDescription;
        $this->parameters_layout['keywords'] = SysClass::getKeywordsFromText($this->html);
        $this->parameters_layout['canonical_href'] = ENV_URL_SITE . '/docs' . ($currentDocument['slug'] ? '/' . $currentDocument['slug'] : '');
        $this->parameters_layout['meta_subject'] = 'EE_FrameWork';
        $this->parameters_layout['meta_page_topic'] = 'EE_FrameWork, PHP framework documentation';
        $this->parameters_layout['meta_author'] = 'EE_FrameWork';
        $this->parameters_layout['meta_reply_to'] = '';
        $this->parameters_layout['meta_copyright'] = 'EE_FrameWork';
        $this->parameters_layout['layout_content'] = $this->html;
        $this->showLayout($this->parameters_layout);
    }

    private function getDocsCatalog(): array {
        $catalog = $this->buildFallbackCatalog();
        $catalog = Hook::filter('C_docsCatalog', $catalog, $this);
        return $this->normalizeCatalog(is_array($catalog) ? $catalog : []);
    }

    private function resolveDocument(string $slug, array $catalog): array {
        $itemsBySlug = is_array($catalog['items_by_slug'] ?? null) ? $catalog['items_by_slug'] : [];
        $aliases = is_array($catalog['aliases'] ?? null) ? $catalog['aliases'] : [];

        if ($slug !== '' && isset($aliases[$slug])) {
            $slug = (string) $aliases[$slug];
        }
        if ($slug === '' || !isset($itemsBySlug[$slug])) {
            $slug = (string) ($catalog['default_slug'] ?? '');
        }

        $document = [
            'slug' => '',
            'title' => 'Документация EE_FrameWork',
            'summary' => '',
            'description' => (string) ($catalog['description'] ?? 'Документация EE_FrameWork'),
            'html' => '<p>Документ пока не найден.</p>',
            'group_id' => '',
            'group_title' => '',
            'icon' => 'fa-book',
            'updated_at' => '',
            'file' => '',
            '_file_missing' => false,
        ];

        if ($slug !== '' && isset($itemsBySlug[$slug])) {
            $document = array_merge($document, $itemsBySlug[$slug]);
        }

        $document = $this->loadDocumentHtml($document);
        $document = Hook::filter('C_docsResolveDocument', $document, $slug, $catalog, $this);

        if (!is_array($document)) {
            return [
                'slug' => '',
                'title' => 'Документация EE_FrameWork',
                'summary' => '',
                'description' => (string) ($catalog['description'] ?? 'Документация EE_FrameWork'),
                'html' => '<p>Документ пока не найден.</p>',
                'group_id' => '',
                'group_title' => '',
                'icon' => 'fa-book',
                'updated_at' => '',
                'file' => '',
                '_file_missing' => true,
            ];
        }

        $document['slug'] = $this->normalizeSlug((string) ($document['slug'] ?? ''));
        $document['title'] = trim((string) ($document['title'] ?? 'Документация EE_FrameWork'));
        $document['summary'] = trim((string) ($document['summary'] ?? ''));
        $document['description'] = trim((string) ($document['description'] ?? ''));
        $document['html'] = (string) ($document['html'] ?? '<p>Документ пока не найден.</p>');
        $document['group_id'] = trim((string) ($document['group_id'] ?? ''));
        $document['group_title'] = trim((string) ($document['group_title'] ?? ''));
        $document['icon'] = trim((string) ($document['icon'] ?? 'fa-book'));
        $document['updated_at'] = trim((string) ($document['updated_at'] ?? ''));
        $document['file'] = trim((string) ($document['file'] ?? ''));
        $document['_file_missing'] = !empty($document['_file_missing']);

        return $document;
    }

    private function buildFallbackCatalog(): array {
        $docsDir = ENV_CUSTOM_PATH . 'docs' . ENV_DIRSEP;
        $files = array_merge(glob($docsDir . '*.md') ?: [], glob($docsDir . '*.html') ?: []);
        natcasesort($files);

        $items = [];
        foreach ($files as $filePath) {
            if (basename($filePath) === 'manifest.json') {
                continue;
            }
            $slug = $this->normalizeSlug(pathinfo($filePath, PATHINFO_FILENAME));
            if ($slug === '') {
                continue;
            }
            $contents = @file_get_contents($filePath);
            $extension = strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION));
            $title = $extension === 'md'
                ? $this->extractTitleFromMarkdown((string) $contents)
                : $this->extractTitleFromHtml((string) $contents);
            $items[] = [
                'slug' => $slug,
                'title' => $title !== '' ? $title : ucfirst(str_replace('-', ' ', $slug)),
                'summary' => '',
                'description' => '',
                'file' => basename($filePath),
                'icon' => 'fa-file-lines',
                'keywords' => [],
            ];
        }

        return [
            'title' => 'Документация EE_FrameWork',
            'description' => 'Техническая документация по ядру, расширению и эксплуатации EE_FrameWork.',
            'intro' => 'Если manifest ещё не собран, docs-модуль показывает документы напрямую из custom/docs/.',
            'default_slug' => $items[0]['slug'] ?? '',
            'groups' => [
                [
                    'id' => 'fallback',
                    'title' => 'Документы',
                    'description' => '',
                    'items' => $items,
                ],
            ],
            'aliases' => [],
        ];
    }

    private function normalizeCatalog(array $catalog): array {
        $normalized = [
            'title' => trim((string) ($catalog['title'] ?? 'Документация EE_FrameWork')),
            'description' => trim((string) ($catalog['description'] ?? 'Техническая документация по EE_FrameWork.')),
            'intro' => trim((string) ($catalog['intro'] ?? '')),
            'default_slug' => $this->normalizeSlug((string) ($catalog['default_slug'] ?? '')),
            'groups' => [],
            'ordered_slugs' => [],
            'items_by_slug' => [],
            'aliases' => [],
        ];

        $groups = is_array($catalog['groups'] ?? null) ? $catalog['groups'] : [];
        foreach ($groups as $groupIndex => $group) {
            if (!is_array($group)) {
                continue;
            }
            $groupId = $this->normalizeSlug((string) ($group['id'] ?? ('group-' . $groupIndex)));
            if ($groupId === '') {
                $groupId = 'group-' . $groupIndex;
            }
            $normalizedGroup = [
                'id' => $groupId,
                'title' => trim((string) ($group['title'] ?? 'Раздел')),
                'description' => trim((string) ($group['description'] ?? '')),
                'items' => [],
            ];
            $items = is_array($group['items'] ?? null) ? $group['items'] : [];
            foreach ($items as $itemIndex => $item) {
                if (!is_array($item)) {
                    continue;
                }
                $slug = $this->normalizeSlug((string) ($item['slug'] ?? ''));
                if ($slug === '') {
                    $slug = $groupId . '-' . $itemIndex;
                }
                if (isset($normalized['items_by_slug'][$slug])) {
                    continue;
                }
                $normalizedItem = [
                    'slug' => $slug,
                    'title' => trim((string) ($item['title'] ?? ucfirst(str_replace('-', ' ', $slug)))),
                    'summary' => trim((string) ($item['summary'] ?? '')),
                    'description' => trim((string) ($item['description'] ?? '')),
                    'file' => trim((string) ($item['file'] ?? ($slug . '.md'))),
                    'icon' => trim((string) ($item['icon'] ?? 'fa-file-lines')),
                    'keywords' => array_values(array_filter(array_map('trim', (array) ($item['keywords'] ?? [])), static fn(string $value): bool => $value !== '')),
                    'updated_at' => trim((string) ($item['updated_at'] ?? '')),
                    'group_id' => $groupId,
                    'group_title' => trim((string) ($group['title'] ?? 'Раздел')),
                ];
                $normalizedGroup['items'][] = $normalizedItem;
                $normalized['ordered_slugs'][] = $slug;
                $normalized['items_by_slug'][$slug] = $normalizedItem;
                foreach ((array) ($item['aliases'] ?? []) as $alias) {
                    $alias = $this->normalizeSlug((string) $alias);
                    if ($alias !== '' && $alias !== $slug) {
                        $normalized['aliases'][$alias] = $slug;
                    }
                }
            }
            if ($normalizedGroup['items'] !== []) {
                $normalized['groups'][] = $normalizedGroup;
            }
        }

        foreach ((array) ($catalog['aliases'] ?? []) as $alias => $targetSlug) {
            $alias = $this->normalizeSlug((string) $alias);
            $targetSlug = $this->normalizeSlug((string) $targetSlug);
            if ($alias === '' || $targetSlug === '' || !isset($normalized['items_by_slug'][$targetSlug]) || $alias === $targetSlug) {
                continue;
            }
            $normalized['aliases'][$alias] = $targetSlug;
        }

        if ($normalized['default_slug'] === '' && !empty($normalized['ordered_slugs'][0])) {
            $normalized['default_slug'] = (string) $normalized['ordered_slugs'][0];
        }

        return $normalized;
    }

    private function buildPagination(array $catalog, string $currentSlug): array {
        $orderedSlugs = is_array($catalog['ordered_slugs'] ?? null) ? $catalog['ordered_slugs'] : [];
        $itemsBySlug = is_array($catalog['items_by_slug'] ?? null) ? $catalog['items_by_slug'] : [];
        $currentIndex = array_search($currentSlug, $orderedSlugs, true);
        if ($currentIndex === false) {
            return ['prev' => null, 'next' => null];
        }

        $prevSlug = $orderedSlugs[$currentIndex - 1] ?? null;
        $nextSlug = $orderedSlugs[$currentIndex + 1] ?? null;

        return [
            'prev' => $prevSlug && isset($itemsBySlug[$prevSlug]) ? $itemsBySlug[$prevSlug] : null,
            'next' => $nextSlug && isset($itemsBySlug[$nextSlug]) ? $itemsBySlug[$nextSlug] : null,
        ];
    }

    private function loadDocumentHtml(array $document): array {
        $file = trim((string) ($document['file'] ?? ''));
        $document['_file_missing'] = false;
        if ($file === '') {
            return $document;
        }

        if (class_exists(\custom\docs\ProjectDocsRepository::class)) {
            $repository = new \custom\docs\ProjectDocsRepository();
            $resolvedPath = $repository->resolveDocumentFile($file);
            if ($resolvedPath === '') {
                $document['_file_missing'] = true;
                return $document;
            }

            $html = $repository->readDocumentHtml($file);
            if (trim($html) !== '') {
                $document['html'] = $html;
            }
            if (empty($document['updated_at'])) {
                $document['updated_at'] = $repository->getDocumentUpdatedAt($file);
            }
            return $document;
        }

        $docsDir = ENV_CUSTOM_PATH . 'docs' . ENV_DIRSEP;
        $filePath = realpath($docsDir . $file);
        $realDocsDir = realpath($docsDir);
        if ($filePath === false || $realDocsDir === false || !str_starts_with($filePath, $realDocsDir) || !is_readable($filePath)) {
            $document['_file_missing'] = true;
            return $document;
        }

        $html = @file_get_contents($filePath);
        if ($html !== false && trim($html) !== '') {
            $document['html'] = $html;
        }
        if (empty($document['updated_at'])) {
            $mtime = @filemtime($filePath);
            if ($mtime) {
                $document['updated_at'] = date('Y-m-d H:i', $mtime);
            }
        }

        return $document;
    }

    private function extractTitleFromHtml(string $html): string {
        if ($html === '') {
            return '';
        }
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/isu', $html, $matches) || preg_match('/<h2[^>]*>(.*?)<\/h2>/isu', $html, $matches)) {
            return trim(strip_tags((string) ($matches[1] ?? '')));
        }
        return '';
    }

    private function extractTitleFromMarkdown(string $markdown): string {
        if ($markdown === '') {
            return '';
        }
        if (preg_match('/^#\s+(.+)$/m', $markdown, $matches)) {
            return trim((string) ($matches[1] ?? ''));
        }
        return '';
    }

    private function normalizeSlug(string $value): string {
        $value = strtolower(trim($value));
        $value = preg_replace('~[^a-z0-9\\-_]+~', '-', $value) ?? '';
        $value = trim($value, '-_');
        return $value;
    }
}
