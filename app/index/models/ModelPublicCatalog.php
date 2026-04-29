<?php

use classes\plugins\SafeMySQL;
use classes\system\Constants;
use classes\system\EntityPublicUrlService;
use classes\system\FileSystem;
use classes\system\PropertyFieldContract;
use classes\system\RedirectService;
use classes\system\SysClass;
use classes\system\WordpressImporter;
use custom\PublicMapProvider;
use custom\SiteContentProvisioner;

class ModelPublicCatalog {

    private array $propertyIdCache = [];
    private array $categoryRowsCache = [];
    private array $recursiveCategoryCountCache = [];
    private array $categoryTypeNameCache = [];

    public function getHomePayload(string $languageCode = ENV_DEF_LANG): array {
        $languageCode = trim($languageCode) !== '' ? trim($languageCode) : ENV_DEF_LANG;
        $rootCategories = $this->getRootCategoryCards($languageCode, 8);
        $popularResorts = $this->getPopularCategoryCards($languageCode, 200);
        $featuredObjects = $this->getFeaturedPageCards($languageCode, 8);
        $latestArticles = $this->getRecentContentPageCardsByTypeNames(['Блог', 'Blog', 'Информация', 'Information'], $languageCode, 0, 4);

        return [
            'hero_resorts' => array_slice($popularResorts, 0, 3),
            'root_categories' => $rootCategories,
            'popular_resorts' => $popularResorts,
            'featured_objects' => $featuredObjects,
            'latest_articles' => $latestArticles,
            'stats' => $this->getHomeStats($languageCode),
        ];
    }

    public function getSiteContent(string $languageCode = ENV_DEF_LANG): array {
        $languageCode = trim($languageCode) !== '' ? trim($languageCode) : ENV_DEF_LANG;
        $content = SiteContentProvisioner::getDefaultContent();
        $pageId = (int) SafeMySQL::gi()->getOne(
            'SELECT p.page_id
             FROM ?n AS p
             JOIN ?n AS c ON c.category_id = p.category_id
             JOIN ?n AS t ON t.type_id = c.type_id
             WHERE p.language_code = ?s
               AND c.language_code = ?s
               AND t.language_code = ?s
               AND t.name IN (?a)
               AND p.title = ?s
             ORDER BY p.page_id ASC
             LIMIT 1',
            Constants::PAGES_TABLE,
            Constants::CATEGORIES_TABLE,
            Constants::CATEGORIES_TYPES_TABLE,
            $languageCode,
            $languageCode,
            $languageCode,
            SiteContentProvisioner::getTypeNames(),
            SiteContentProvisioner::getPageTitle()
        );
        if ($pageId <= 0) {
            return $content;
        }

        $propertyMap = $this->getEntityPropertyMap('page', $pageId, $languageCode);
        $propertyNames = SiteContentProvisioner::getPropertyNameMap();

        $imageMappings = [
            [['header', 'logo_url'], 'site_header_logo'],
            [['home', 'hero_banner_url'], 'site_home_hero_banner'],
        ];
        foreach ($imageMappings as [$path, $propertyCode]) {
            $defaultValue = $this->readNestedSiteContentValue($content, $path);
            $resolvedValue = $this->extractSiteContentImageUrl($propertyMap[$propertyNames[$propertyCode]] ?? null, $defaultValue);
            $this->writeNestedSiteContentValue($content, $path, $resolvedValue);
        }

        $scalarMappings = [
            [['header', 'blog_label'], 'site_header_blog_label'],
            [['header', 'owners_label'], 'site_header_owners_label'],
            [['header', 'about_label'], 'site_header_about_label'],
            [['header', 'contacts_label'], 'site_header_contacts_label'],
            [['header', 'auth_label'], 'site_header_auth_label'],
            [['links', 'blog_url'], 'site_link_blog_url'],
            [['links', 'owners_url'], 'site_link_owners_url'],
            [['links', 'about_url'], 'site_link_about_url'],
            [['links', 'contacts_url'], 'site_link_contacts_url'],
            [['links', 'login_url'], 'site_link_login_url'],
            [['links', 'registration_url'], 'site_link_registration_url'],
            [['links', 'privacy_policy_url'], 'site_link_privacy_policy_url'],
            [['links', 'personal_data_consent_url'], 'site_link_personal_data_consent_url'],
            [['home', 'hero_title'], 'site_home_hero_title'],
            [['home', 'hero_text'], 'site_home_hero_text'],
            [['home', 'hero_primary_label'], 'site_home_hero_primary_label'],
            [['home', 'hero_secondary_label'], 'site_home_hero_secondary_label'],
            [['home', 'popular_resorts_title'], 'site_home_popular_resorts_title'],
            [['home', 'directions_title'], 'site_home_directions_title'],
            [['home', 'latest_objects_subtitle'], 'site_home_latest_objects_subtitle'],
            [['home', 'latest_objects_title'], 'site_home_latest_objects_title'],
            [['home', 'articles_title'], 'site_home_articles_title'],
            [['home', 'articles_read_more_label'], 'site_home_articles_read_more_label'],
            [['home', 'owner_cta_subtitle'], 'site_home_owner_cta_subtitle'],
            [['home', 'owner_cta_title'], 'site_home_owner_cta_title'],
            [['home', 'owner_cta_text'], 'site_home_owner_cta_text'],
            [['home', 'owner_cta_primary_label'], 'site_home_owner_cta_primary_label'],
            [['home', 'owner_cta_secondary_label'], 'site_home_owner_cta_secondary_label'],
            [['auth', 'login', 'kicker'], 'site_auth_login_kicker'],
            [['auth', 'login', 'title'], 'site_auth_login_title'],
            [['auth', 'login', 'lead'], 'site_auth_login_lead'],
            [['auth', 'login', 'point_1_title'], 'site_auth_login_point_1_title'],
            [['auth', 'login', 'point_1_text'], 'site_auth_login_point_1_text'],
            [['auth', 'login', 'point_2_title'], 'site_auth_login_point_2_title'],
            [['auth', 'login', 'point_2_text'], 'site_auth_login_point_2_text'],
            [['auth', 'login', 'point_3_title'], 'site_auth_login_point_3_title'],
            [['auth', 'login', 'point_3_text'], 'site_auth_login_point_3_text'],
            [['auth', 'login', 'form_title'], 'site_auth_login_form_title'],
            [['auth', 'login', 'form_subtitle'], 'site_auth_login_form_subtitle'],
            [['auth', 'login', 'recovery_title'], 'site_auth_login_recovery_title'],
            [['auth', 'login', 'recovery_subtitle'], 'site_auth_login_recovery_subtitle'],
            [['auth', 'registration', 'kicker'], 'site_auth_registration_kicker'],
            [['auth', 'registration', 'title'], 'site_auth_registration_title'],
            [['auth', 'registration', 'lead'], 'site_auth_registration_lead'],
            [['auth', 'registration', 'point_1_title'], 'site_auth_registration_point_1_title'],
            [['auth', 'registration', 'point_1_text'], 'site_auth_registration_point_1_text'],
            [['auth', 'registration', 'point_2_title'], 'site_auth_registration_point_2_title'],
            [['auth', 'registration', 'point_2_text'], 'site_auth_registration_point_2_text'],
            [['auth', 'registration', 'point_3_title'], 'site_auth_registration_point_3_title'],
            [['auth', 'registration', 'point_3_text'], 'site_auth_registration_point_3_text'],
            [['auth', 'registration', 'form_title'], 'site_auth_registration_form_title'],
            [['auth', 'registration', 'form_subtitle'], 'site_auth_registration_form_subtitle'],
            [['auth', 'registration', 'captcha_label'], 'site_auth_registration_captcha_label'],
            [['auth', 'registration', 'captcha_placeholder'], 'site_auth_registration_captcha_placeholder'],
            [['auth', 'registration', 'captcha_refresh_label'], 'site_auth_registration_captcha_refresh_label'],
            [['auth', 'registration', 'owners_link_label'], 'site_auth_registration_owners_link_label'],
            [['legal', 'privacy_policy_label'], 'site_legal_privacy_policy_label'],
            [['legal', 'personal_data_consent_label'], 'site_legal_personal_data_consent_label'],
            [['legal', 'open_document_label'], 'site_legal_open_document_label'],
            [['footer', 'home_label'], 'site_footer_home_label'],
            [['footer', 'sections_title'], 'site_footer_sections_title'],
            [['footer', 'service_title'], 'site_footer_service_title'],
            [['footer', 'login_label'], 'site_footer_login_label'],
            [['footer', 'registration_label'], 'site_footer_registration_label'],
            [['footer', 'legal_title'], 'site_footer_legal_title'],
            [['footer', 'privacy_policy_label'], 'site_footer_privacy_policy_label'],
            [['footer', 'personal_data_consent_label'], 'site_footer_personal_data_consent_label'],
            [['footer', 'demo_title'], 'site_footer_demo_title'],
            [['footer', 'demo_text_1'], 'site_footer_demo_text_1'],
            [['footer', 'demo_text_2'], 'site_footer_demo_text_2'],
        ];
        foreach ($scalarMappings as [$path, $propertyCode]) {
            $defaultValue = $this->readNestedSiteContentValue($content, $path);
            $resolvedValue = $this->extractScalarValue($propertyMap[$propertyNames[$propertyCode]] ?? null, $defaultValue);
            $this->writeNestedSiteContentValue($content, $path, $resolvedValue);
        }

        $content['links'] = $this->normalizeSiteContentLinkMap(
            (array) ($content['links'] ?? []),
            (array) (SiteContentProvisioner::getDefaultContent()['links'] ?? []),
            $languageCode
        );

        return $content;
    }

    /**
     * @param array<int,string> $path
     */
    private function readNestedSiteContentValue(array $content, array $path): string {
        $cursor = $content;
        foreach ($path as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return '';
            }
            $cursor = $cursor[$segment];
        }

        return is_string($cursor) ? $cursor : '';
    }

    /**
     * @param array<int,string> $path
     */
    private function writeNestedSiteContentValue(array &$content, array $path, string $value): void {
        $cursor = &$content;
        $lastIndex = count($path) - 1;
        foreach ($path as $index => $segment) {
            if ($index === $lastIndex) {
                $cursor[$segment] = $value;
                return;
            }

            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }
            $cursor = &$cursor[$segment];
        }
    }

    private function normalizeSiteContentLinkMap(array $links, array $defaults, string $languageCode): array {
        foreach ($defaults as $key => $defaultValue) {
            $value = trim((string) ($links[$key] ?? ''));
            $fallback = trim((string) $defaultValue);
            if ($value === '') {
                $links[$key] = $fallback;
                continue;
            }

            $normalized = $this->normalizeInternalPath($value);
            if ($normalized === '' || !$this->isAllowedSiteContentPath($normalized, $languageCode)) {
                $links[$key] = $fallback;
                continue;
            }

            $links[$key] = $normalized;
        }

        return $links;
    }

    private function isAllowedSiteContentPath(string $path, string $languageCode): bool {
        static $cache = [];
        if (!isset($cache[$languageCode])) {
            $routes = [
                '/',
                '/about',
                '/contact',
                '/owners',
                '/blog',
                '/login',
                '/show_login_form',
                '/registration',
                '/privacy_policy',
                '/consent_personal_data',
            ];
            $pageRoutes = SafeMySQL::gi()->getCol(
                'SELECT route_path FROM ?n WHERE language_code = ?s AND status = ?s AND route_path IS NOT NULL AND route_path != ""',
                Constants::PAGES_TABLE,
                $languageCode,
                'active'
            );
            $categoryRoutes = SafeMySQL::gi()->getCol(
                'SELECT route_path FROM ?n WHERE language_code = ?s AND status = ?s AND route_path IS NOT NULL AND route_path != ""',
                Constants::CATEGORIES_TABLE,
                $languageCode,
                'active'
            );
            foreach (array_merge((array) $pageRoutes, (array) $categoryRoutes) as $routePath) {
                $normalized = $this->normalizeInternalPath((string) $routePath);
                if ($normalized !== '') {
                    $routes[] = $normalized;
                }
            }
            $cache[$languageCode] = array_flip(array_values(array_unique($routes)));
        }

        return isset($cache[$languageCode][$path]);
    }

    public function getPagePayload(int $pageId, string $languageCode = ENV_DEF_LANG): ?array {
        $basePayload = EntityPublicUrlService::getEntityViewPayload('page', $pageId, $languageCode);
        if (!$basePayload) {
            return null;
        }

        $pageId = (int) ($basePayload['entity_id'] ?? 0);
        $languageCode = (string) ($basePayload['language_code'] ?? ENV_DEF_LANG);
        $pageRow = SafeMySQL::gi()->getRow(
            'SELECT p.page_id, p.title, p.category_id, p.parent_page_id, p.language_code, p.short_description, p.description, p.status, p.slug, p.route_path, p.created_at, p.updated_at,
                    c.title AS category_title, c.slug AS category_slug, c.type_id AS category_type_id, t.name AS category_type_name
             FROM ?n AS p
             LEFT JOIN ?n AS c ON c.category_id = p.category_id
             LEFT JOIN ?n AS t ON t.type_id = c.type_id
             WHERE page_id = ?i
             LIMIT 1',
            Constants::PAGES_TABLE,
            Constants::CATEGORIES_TABLE,
            Constants::CATEGORIES_TYPES_TABLE,
            $pageId
        );
        if (!$pageRow) {
            return null;
        }

        $categoryRow = SafeMySQL::gi()->getRow(
            'SELECT category_id, title, parent_id, slug, type_id
             FROM ?n
             WHERE category_id = ?i
             LIMIT 1',
            Constants::CATEGORIES_TABLE,
            (int) ($pageRow['category_id'] ?? 0)
        ) ?: [];

        $categoryTypeName = trim((string) ($pageRow['category_type_name'] ?? ''));
        if ($this->isBlogCategoryTypeName($categoryTypeName) || $this->isSitePageCategoryTypeName($categoryTypeName)) {
            return $this->buildContentPagePayload($basePayload, $pageRow, $categoryRow, $languageCode, $categoryTypeName);
        }

        $propertyMap = $this->getEntityPropertyMap('page', $pageId, $languageCode);
        $gallery = $this->extractGalleryItems($propertyMap['Фотографии объекта'] ?? null);
        $roomOffer = $this->extractRoomOffer($propertyMap['Номера и цены'] ?? null, $languageCode);
        $phones = $this->extractPhoneEntries($propertyMap['Телефоны'] ?? null);
        $owner = $this->getPageOwner($pageId);

        $email = $this->extractScalarValue($propertyMap['Электронная почта'] ?? null);
        if ($email === '' && !empty($owner['email'])) {
            $email = trim((string) $owner['email']);
        }

        $website = $this->normalizeWebsite($this->extractScalarValue($propertyMap['Сайт'] ?? null));
        $address = $this->extractScalarValue($propertyMap['Адрес'] ?? null);
        $distanceToSea = $this->extractScalarValue($propertyMap['Удаленность от моря'] ?? null);
        $map = $this->extractMapData($propertyMap['Карта'] ?? null, (string) ($pageRow['title'] ?? ''));

        $objectType = $this->extractChoiceSummary($propertyMap['Тип объекта'] ?? null);
        $operationMode = $this->extractChoiceSummary($propertyMap['Характер функционирования объекта'] ?? null);
        $childrenPolicy = $this->extractChoiceSummary($propertyMap['Дети'] ?? null);
        $services = $this->extractChoiceList($propertyMap['Предоставляемые услуги'] ?? null);
        $food = $this->extractChoiceList($propertyMap['Питание'] ?? null);
        $transfer = $this->extractChoiceList($propertyMap['Трансфер'] ?? null);
        $priceComment = $this->normalizeRichTextHtml($this->extractScalarValue($propertyMap['Комментарий к ценам'] ?? null), $languageCode);
        $priceFrom = $this->extractScalarValue($propertyMap['Цена от'] ?? null);

        $summary = $this->resolveSummary(
            (string) ($pageRow['short_description'] ?? ''),
            (string) ($pageRow['description'] ?? '')
        );
        $descriptionHtml = $this->normalizeRichTextHtml((string) ($pageRow['description'] ?? ''), $languageCode);
        $metaDescription = $summary !== '' ? $summary : $this->truncateText($this->extractPlainText($descriptionHtml), 180);

        $facts = array_values(array_filter([
            $this->makeFact($this->langLabel('sys.type', 'Type'), $objectType),
            $this->makeFact($this->langLabel('sys.public_operation_mode', 'Operation mode'), $operationMode),
            $this->makeFact($this->langLabel('sys.public_children_policy', 'Children'), $childrenPolicy),
            $this->makeFact($this->langLabel('sys.public_distance_to_sea', 'Distance to the sea'), $distanceToSea !== '' ? trim($distanceToSea) . ' м' : ''),
        ]));

        return array_merge($basePayload, [
            'view_type' => 'page',
            'view_template' => 'v_public_page',
            'summary' => $summary,
            'meta_description' => $metaDescription !== '' ? $metaDescription : (string) ($basePayload['meta_description'] ?? ''),
            'description_html' => $descriptionHtml,
            'gallery' => $gallery,
            'gallery_preview' => array_slice($gallery, 0, 8),
            'resort' => [
                'title' => trim((string) ($categoryRow['title'] ?? '')),
                'url' => !empty($categoryRow['category_id'])
                    ? EntityPublicUrlService::buildEntityUrl('category', (int) $categoryRow['category_id'], $languageCode)
                    : '',
            ],
            'facts' => $facts,
            'services' => $services,
            'food' => $food,
            'transfer' => $transfer,
            'price_from' => $priceFrom,
            'contacts' => [
                'phones' => $phones,
                'email' => $email,
                'email_href' => $email !== '' ? ('mailto:' . $email) : '',
                'website' => $website,
                'address' => $address,
            ],
            'location' => [
                'distance_to_sea' => $distanceToSea,
                'map' => $map,
            ],
            'room_offer' => $roomOffer,
            'price_comment_html' => $priceComment,
            'owner' => $owner,
            'language_links' => $this->filterLanguageLinks((array) ($basePayload['alternate_links'] ?? [])),
            'related_pages' => $this->getPageCardsByCategory((int) ($pageRow['category_id'] ?? 0), $languageCode, $pageId, 8),
            'reviews' => $this->getPageReviews($pageId, $languageCode, 6),
        ]);
    }

    public function getCategoryPayload(int $categoryId, string $languageCode = ENV_DEF_LANG): ?array {
        $basePayload = EntityPublicUrlService::getEntityViewPayload('category', $categoryId, $languageCode);
        if (!$basePayload) {
            return null;
        }

        $categoryId = (int) ($basePayload['entity_id'] ?? 0);
        $languageCode = (string) ($basePayload['language_code'] ?? ENV_DEF_LANG);
        $categoryRow = SafeMySQL::gi()->getRow(
            'SELECT c.category_id, c.title, c.parent_id, c.type_id, c.language_code, c.short_description, c.description, c.status, c.slug, c.route_path, t.name AS type_name
             FROM ?n AS c
             LEFT JOIN ?n AS t ON t.type_id = c.type_id
             WHERE category_id = ?i
             LIMIT 1',
            Constants::CATEGORIES_TABLE,
            Constants::CATEGORIES_TYPES_TABLE,
            $categoryId
        );
        if (!$categoryRow) {
            return null;
        }

        if ($this->isBlogCategoryTypeName((string) ($categoryRow['type_name'] ?? ''))) {
            return $this->buildBlogCategoryPayload($basePayload, $categoryRow, $languageCode);
        }

        $propertyMap = $this->getEntityPropertyMap('category', $categoryId, $languageCode);
        $overviewHtml = $this->normalizeRichTextHtml(
            $this->extractScalarValue($propertyMap['Описание курорта'] ?? null, (string) ($categoryRow['description'] ?? '')),
            $languageCode
        );
        $leftBlockHtml = $this->normalizeRichTextHtml($this->extractScalarValue($propertyMap['Левый блок текста'] ?? null), $languageCode);
        $rightBlockHtml = $this->normalizeRichTextHtml($this->extractScalarValue($propertyMap['Правый блок текста'] ?? null), $languageCode);
        $locativeTitle = $this->extractScalarValue($propertyMap['Предложный падеж'] ?? null);
        $gallery = $this->extractGalleryItems($propertyMap['Фотографии курорта'] ?? null);
        $map = $this->extractMapData($propertyMap['Карта курорта'] ?? null, (string) ($categoryRow['title'] ?? ''));
        $resortBlocks = [];
        foreach ([
            'Блок текста: Жилье',
            'Блок текста: Коттеджи и дома под ключ',
            'Блок текста: Квартиры',
            'Блок текста: Гостиницы и отели',
            'Блок текста: Гостевые дома',
            'Блок текста: Частный сектор',
        ] as $propertyName) {
            $html = $this->normalizeRichTextHtml($this->extractScalarValue($propertyMap[$propertyName] ?? null), $languageCode);
            if ($html === '') {
                continue;
            }
            $resortBlocks[] = [
                'title' => $propertyName,
                'html' => $html,
            ];
        }

        $summary = $this->resolveSummary(
            (string) ($categoryRow['short_description'] ?? ''),
            $overviewHtml !== '' ? $overviewHtml : (string) ($categoryRow['description'] ?? '')
        );
        $metaDescription = $summary !== '' ? $summary : $this->truncateText($this->extractPlainText($overviewHtml), 180);

        return array_merge($basePayload, [
            'view_type' => 'category',
            'view_template' => 'v_public_category',
            'summary' => $summary,
            'meta_description' => $metaDescription !== '' ? $metaDescription : (string) ($basePayload['meta_description'] ?? ''),
            'locative_title' => $locativeTitle !== '' ? $locativeTitle : (string) ($categoryRow['title'] ?? ''),
            'overview_html' => $overviewHtml,
            'left_block_html' => $leftBlockHtml,
            'right_block_html' => $rightBlockHtml,
            'resort_blocks' => $resortBlocks,
            'gallery' => $gallery,
            'gallery_preview' => array_slice($gallery, 0, 10),
            'map' => $map,
            'language_links' => $this->filterLanguageLinks((array) ($basePayload['alternate_links'] ?? [])),
            'child_categories' => $this->getChildCategoryCards($categoryId, $languageCode, 24),
            'pages' => $this->getPageCardsByCategory($categoryId, $languageCode, 0, 0),
            'object_type_groups' => $this->getCategoryObjectTypeGroups($categoryId, $languageCode),
        ]);
    }

    public function findPublicPageIdByRouteOrSlug(string $routeOrSlug, array $typeNames, string $languageCode = ENV_DEF_LANG): int {
        $routeOrSlug = trim($routeOrSlug);
        $typeNames = array_values(array_filter(array_map(static fn($item): string => trim((string) $item), $typeNames)));
        if ($routeOrSlug === '' || $typeNames === []) {
            return 0;
        }

        $routeCandidates = array_values(array_unique(array_filter([
            $routeOrSlug,
            '/' . ltrim($routeOrSlug, '/'),
            ltrim($routeOrSlug, '/'),
        ], static fn($value): bool => trim((string) $value) !== '')));

        return (int) SafeMySQL::gi()->getOne(
            'SELECT p.page_id
             FROM ?n AS p
             JOIN ?n AS c ON c.category_id = p.category_id
             JOIN ?n AS t ON t.type_id = c.type_id
             WHERE p.language_code = ?s
               AND p.status = ?s
               AND t.language_code = ?s
               AND t.name IN (?a)
               AND (p.route_path IN (?a) OR p.slug = ?s)
             ORDER BY p.page_id ASC
             LIMIT 1',
            Constants::PAGES_TABLE,
            Constants::CATEGORIES_TABLE,
            Constants::CATEGORIES_TYPES_TABLE,
            $languageCode,
            'active',
            $languageCode,
            $typeNames,
            $routeCandidates,
            $routeOrSlug
        );
    }

    private function buildContentPagePayload(array $basePayload, array $pageRow, array $categoryRow, string $languageCode, string $categoryTypeName): array {
        $descriptionHtml = $this->normalizeRichTextHtml((string) ($pageRow['description'] ?? ''), $languageCode);
        $summary = $this->resolveSummary((string) ($pageRow['short_description'] ?? ''), (string) ($pageRow['description'] ?? ''));
        $metaDescription = $summary !== '' ? $summary : $this->truncateText($this->extractPlainText($descriptionHtml), 180);
        $isBlog = $this->isBlogCategoryTypeName($categoryTypeName);
        $heroImage = $this->extractFirstImageUrlFromHtml($descriptionHtml);
        if ($isBlog && $heroImage !== '') {
            $descriptionHtml = $this->removeLeadingImageFromHtml($descriptionHtml, $heroImage);
        }
        if ($heroImage === '') {
            $heroImage = $isBlog
                ? ENV_URL_SITE . '/assets/vendor/tourm/img/blog/blog-s-1-1.jpg'
                : ENV_URL_SITE . '/assets/vendor/tourm/img/bg/breadcumb-bg.jpg';
        }

        return array_merge($basePayload, [
            'view_type' => 'page',
            'view_template' => 'v_public_page_content',
            'content_kind' => $isBlog ? 'blog_article' : 'site_page',
            'summary' => $summary,
            'meta_description' => $metaDescription !== '' ? $metaDescription : (string) ($basePayload['meta_description'] ?? ''),
            'description_html' => $descriptionHtml,
            'hero_image' => $heroImage,
            'published_at' => (string) ($pageRow['created_at'] ?? ''),
            'published_at_pretty' => $this->formatPublicDate((string) ($pageRow['created_at'] ?? ''), $languageCode),
            'updated_at' => (string) ($pageRow['updated_at'] ?? ''),
            'updated_at_pretty' => $this->formatPublicDate((string) ($pageRow['updated_at'] ?? ''), $languageCode),
            'section' => [
                'title' => trim((string) ($categoryRow['title'] ?? '')),
                'url' => !empty($categoryRow['category_id'])
                    ? EntityPublicUrlService::buildEntityUrl('category', (int) $categoryRow['category_id'], $languageCode)
                    : '',
            ],
            'language_links' => $this->filterLanguageLinks((array) ($basePayload['alternate_links'] ?? [])),
            'recent_articles' => $this->getRecentContentPageCardsByTypeNames(['Блог', 'Blog', 'Информация', 'Information'], $languageCode, (int) ($pageRow['page_id'] ?? 0), 5),
        ]);
    }

    private function buildBlogCategoryPayload(array $basePayload, array $categoryRow, string $languageCode): array {
        $descriptionHtml = $this->normalizeRichTextHtml((string) ($categoryRow['description'] ?? ''), $languageCode);
        $summary = $this->resolveSummary((string) ($categoryRow['short_description'] ?? ''), (string) ($categoryRow['description'] ?? ''));
        $metaDescription = $summary !== '' ? $summary : $this->truncateText($this->extractPlainText($descriptionHtml), 180);
        $heroImage = ENV_URL_SITE . '/assets/vendor/tourm/img/bg/breadcumb-bg.jpg';
        $articles = $this->getContentPageCardsByCategory((int) ($categoryRow['category_id'] ?? 0), $languageCode, 0, 24, true);
        if (!empty($articles[0]['image_url'])) {
            $heroImage = (string) $articles[0]['image_url'];
        }

        return array_merge($basePayload, [
            'view_type' => 'category',
            'view_template' => 'v_public_blog_category',
            'summary' => $summary,
            'meta_description' => $metaDescription !== '' ? $metaDescription : (string) ($basePayload['meta_description'] ?? ''),
            'overview_html' => $descriptionHtml,
            'hero_image' => $heroImage,
            'articles' => $articles,
            'recent_articles' => array_slice($articles, 0, 5),
            'language_links' => $this->filterLanguageLinks((array) ($basePayload['alternate_links'] ?? [])),
        ]);
    }

    private function getContentPageCardsByCategory(int $categoryId, string $languageCode, int $excludePageId = 0, int $limit = 12, bool $includeDescendants = false): array {
        if ($categoryId <= 0) {
            return [];
        }

        $categoryIds = [$categoryId];
        if ($includeDescendants) {
            $categoryIds = $this->collectCategoryDescendantIds($categoryId, $languageCode);
        }
        $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds), static fn(int $id): bool => $id > 0)));
        if ($categoryIds === []) {
            return [];
        }

        $sql = 'SELECT p.page_id, p.title, p.slug, p.route_path, p.short_description, p.description, p.created_at, p.updated_at, c.title AS category_title
                FROM ?n AS p
                JOIN ?n AS c ON c.category_id = p.category_id
                WHERE p.category_id IN (?a)
                  AND p.status = ?s
                  AND p.language_code = ?s';
        $params = [Constants::PAGES_TABLE, Constants::CATEGORIES_TABLE, $categoryIds, 'active', $languageCode];
        if ($excludePageId > 0) {
            $sql .= ' AND p.page_id != ?i';
            $params[] = $excludePageId;
        }
        $sql .= ' ORDER BY p.created_at DESC, p.page_id DESC LIMIT ?i';
        $params[] = max(1, $limit);

        $rows = SafeMySQL::gi()->getAll($sql, ...$params) ?: [];
        return array_map(fn(array $row): array => $this->buildContentPageCard($row, $languageCode), $rows);
    }

    private function getRecentContentPageCardsByTypeNames(array $typeNames, string $languageCode, int $excludePageId = 0, int $limit = 5): array {
        $typeNames = array_values(array_filter(array_map(static fn($item): string => trim((string) $item), $typeNames)));
        if ($typeNames === []) {
            return [];
        }

        $sql = 'SELECT p.page_id, p.title, p.slug, p.route_path, p.short_description, p.description, p.created_at, p.updated_at, c.title AS category_title
                FROM ?n AS p
                JOIN ?n AS c ON c.category_id = p.category_id
                JOIN ?n AS t ON t.type_id = c.type_id
                WHERE p.status = ?s
                  AND p.language_code = ?s
                  AND t.language_code = ?s
                  AND t.name IN (?a)';
        $params = [Constants::PAGES_TABLE, Constants::CATEGORIES_TABLE, Constants::CATEGORIES_TYPES_TABLE, 'active', $languageCode, $languageCode, $typeNames];
        if ($excludePageId > 0) {
            $sql .= ' AND p.page_id != ?i';
            $params[] = $excludePageId;
        }
        $sql .= ' ORDER BY p.created_at DESC, p.page_id DESC LIMIT ?i';
        $params[] = max(1, $limit);

        $rows = SafeMySQL::gi()->getAll($sql, ...$params) ?: [];
        return array_map(fn(array $row): array => $this->buildContentPageCard($row, $languageCode), $rows);
    }

    private function buildContentPageCard(array $row, string $languageCode): array {
        $descriptionHtml = $this->normalizeRichTextHtml((string) ($row['description'] ?? ''), $languageCode);
        $imageUrl = $this->extractFirstImageUrlFromHtml($descriptionHtml);
        if ($imageUrl === '') {
            $imageUrl = ENV_URL_SITE . '/assets/vendor/tourm/img/blog/blog-s-1-1.jpg';
        }

        return [
            'page_id' => (int) ($row['page_id'] ?? 0),
            'title' => trim((string) ($row['title'] ?? '')),
            'url' => EntityPublicUrlService::buildEntityUrl('page', (int) ($row['page_id'] ?? 0), $languageCode),
            'summary' => $this->buildCardSummary((string) ($row['short_description'] ?? ''), (string) ($row['description'] ?? ''), 220),
            'image_url' => $imageUrl,
            'published_at' => (string) ($row['created_at'] ?? ''),
            'published_at_pretty' => $this->formatPublicDate((string) ($row['created_at'] ?? ''), $languageCode),
            'category_title' => trim((string) ($row['category_title'] ?? '')),
        ];
    }

    private function collectCategoryDescendantIds(int $categoryId, string $languageCode): array {
        $queue = [$categoryId];
        $ids = [];
        while ($queue !== []) {
            $currentId = (int) array_shift($queue);
            if ($currentId <= 0 || in_array($currentId, $ids, true)) {
                continue;
            }
            $ids[] = $currentId;
            $childIds = SafeMySQL::gi()->getCol(
                'SELECT category_id FROM ?n WHERE parent_id = ?i AND language_code = ?s',
                Constants::CATEGORIES_TABLE,
                $currentId,
                $languageCode
            ) ?: [];
            foreach ($childIds as $childId) {
                $queue[] = (int) $childId;
            }
        }

        return $ids;
    }

    private function getPageReviews(int $pageId, string $languageCode, int $limit = 6): array {
        if ($pageId <= 0) {
            return [];
        }

        $rows = SafeMySQL::gi()->getAll(
            'SELECT p.page_id, p.title, p.description, p.updated_at
             FROM ?n AS p
             JOIN ?n AS c ON c.category_id = p.category_id
             JOIN ?n AS t ON t.type_id = c.type_id
             WHERE p.status = ?s
               AND p.language_code = ?s
               AND t.language_code = ?s
               AND t.name IN (?a)
             ORDER BY p.updated_at DESC, p.page_id DESC',
            Constants::PAGES_TABLE,
            Constants::CATEGORIES_TABLE,
            Constants::CATEGORIES_TYPES_TABLE,
            'active',
            $languageCode,
            $languageCode,
            ['Отзывы', 'Reviews']
        ) ?: [];

        $reviews = [];
        foreach ($rows as $row) {
            $reviewPageId = (int) ($row['page_id'] ?? 0);
            if ($reviewPageId <= 0) {
                continue;
            }
            $propertyMap = $this->getEntityPropertyMap('page', $reviewPageId, $languageCode);
            $objectPageId = (int) $this->extractScalarValue($propertyMap['Отзыв: Объект размещения (page_id)'] ?? null);
            if ($objectPageId !== $pageId) {
                continue;
            }
            $moderationStatus = $this->normalizeReviewStatusValue(
                $this->extractStoredScalarValue($propertyMap['Отзыв: Статус модерации'] ?? null, $this->extractScalarValue($propertyMap['Отзыв: Статус модерации'] ?? null))
            );
            $showOnSite = $this->normalizeReviewVisibilityValue(
                $this->extractStoredScalarValue($propertyMap['Отзыв: Показывать на сайте'] ?? null, $this->extractScalarValue($propertyMap['Отзыв: Показывать на сайте'] ?? null))
            );
            if ($moderationStatus !== 'approved' || $showOnSite !== 'yes') {
                continue;
            }

            $authorName = trim($this->extractScalarValue($propertyMap['Отзыв: Имя автора'] ?? null));
            $authorUserId = (int) $this->extractScalarValue($propertyMap['Отзыв: Автор (user_id)'] ?? null);
            if ($authorName === '' && $authorUserId > 0) {
                $authorName = (string) SafeMySQL::gi()->getOne(
                    'SELECT name FROM ?n WHERE user_id = ?i LIMIT 1',
                    Constants::USERS_TABLE,
                    $authorUserId
                );
            }
            $rating = max(0, min(5, (int) $this->extractScalarValue($propertyMap['Отзыв: Оценка'] ?? null)));
            $reviewDate = trim($this->extractScalarValue($propertyMap['Отзыв: Дата'] ?? null));
            $descriptionHtml = $this->normalizeRichTextHtml((string) ($row['description'] ?? ''), $languageCode);

            $reviews[] = [
                'page_id' => $reviewPageId,
                'author_name' => $authorName !== '' ? $authorName : 'Гость',
                'rating' => $rating,
                'rating_stars' => str_repeat('★', $rating) . str_repeat('☆', max(0, 5 - $rating)),
                'review_date' => $reviewDate,
                'review_date_pretty' => $this->formatPublicDate($reviewDate !== '' ? $reviewDate : (string) ($row['updated_at'] ?? ''), $languageCode),
                'description_html' => $descriptionHtml,
            ];
        }

        usort($reviews, static function (array $left, array $right): int {
            return strcmp((string) ($right['review_date'] ?? ''), (string) ($left['review_date'] ?? ''));
        });

        return array_slice($reviews, 0, max(1, $limit));
    }

    private function getRootCategoryCards(string $languageCode, int $limit = 8): array {
        $categories = array_values(array_filter(
            $this->getPublicCategoryRows($languageCode),
            fn(array $row): bool => empty($row['parent_id']) && $this->isResortCategoryTypeId((int) ($row['type_id'] ?? 0), $languageCode)
        ));
        usort($categories, static function (array $left, array $right): int {
            return ((int) ($left['category_id'] ?? 0)) <=> ((int) ($right['category_id'] ?? 0));
        });
        $countMap = $this->getRecursiveCategoryPageCountMap($languageCode);
        $rows = [];
        foreach ($categories as $category) {
            $categoryId = (int) ($category['category_id'] ?? 0);
            $pagesTotal = (int) ($countMap[$categoryId] ?? 0);
            if ($pagesTotal <= 0) {
                continue;
            }
            $category['pages_total'] = $pagesTotal;
            $rows[] = $category;
            if (count($rows) >= max(1, $limit)) {
                break;
            }
        }
        if (!$rows) {
            return [];
        }

        $categoryIds = array_map(static fn($row): int => (int) ($row['category_id'] ?? 0), $rows);
        $previewMap = $this->getCategoryPreviewPropertyMap($categoryIds, $languageCode);
        $cards = [];
        foreach ($rows as $row) {
            $categoryId = (int) ($row['category_id'] ?? 0);
            $preview = $previewMap[$categoryId] ?? [];
            $cards[] = [
                'category_id' => $categoryId,
                'title' => trim((string) ($row['title'] ?? '')),
                'url' => EntityPublicUrlService::buildEntityUrl('category', $categoryId, $languageCode),
                'summary' => $this->buildCardSummary((string) ($row['short_description'] ?? ''), (string) ($row['description'] ?? '')),
                'pages_total' => (int) ($row['pages_total'] ?? 0),
                'image' => $preview['image'] ?? null,
            ];
        }

        return $cards;
    }

    private function getPopularCategoryCards(string $languageCode, int $limit = 8): array {
        $countMap = $this->getRecursiveCategoryPageCountMap($languageCode);
        $rows = array_values(array_filter(
            $this->getPublicCategoryRows($languageCode),
            fn(array $row): bool => !empty($row['parent_id']) && $this->isResortCategoryTypeId((int) ($row['type_id'] ?? 0), $languageCode)
        ));
        foreach ($rows as &$row) {
            $row['pages_total'] = (int) ($countMap[(int) ($row['category_id'] ?? 0)] ?? 0);
        }
        unset($row);
        $rows = array_values(array_filter($rows, static fn(array $row): bool => (int) ($row['pages_total'] ?? 0) > 0));
        usort($rows, static function (array $left, array $right): int {
            $countCompare = ((int) ($right['pages_total'] ?? 0)) <=> ((int) ($left['pages_total'] ?? 0));
            if ($countCompare !== 0) {
                return $countCompare;
            }
            return strcmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
        });
        $rows = array_slice($rows, 0, max(1, $limit));
        if (!$rows) {
            return [];
        }

        $categoryIds = array_map(static fn($row): int => (int) ($row['category_id'] ?? 0), $rows);
        $previewMap = $this->getCategoryPreviewPropertyMap($categoryIds, $languageCode);
        $cards = [];
        foreach ($rows as $row) {
            $categoryId = (int) ($row['category_id'] ?? 0);
            $preview = $previewMap[$categoryId] ?? [];
            $cards[] = [
                'category_id' => $categoryId,
                'title' => trim((string) ($row['title'] ?? '')),
                'url' => EntityPublicUrlService::buildEntityUrl('category', $categoryId, $languageCode),
                'summary' => $this->buildCardSummary((string) ($row['short_description'] ?? ''), (string) ($row['description'] ?? '')),
                'pages_total' => (int) ($row['pages_total'] ?? 0),
                'image' => $preview['image'] ?? null,
            ];
        }

        return $cards;
    }

    private function getFeaturedPageCards(string $languageCode, int $limit = 8): array {
        $rows = SafeMySQL::gi()->getAll(
            'SELECT p.page_id, p.title, p.category_id, p.short_description, p.description, c.title AS category_title
             FROM ?n AS p
             JOIN ?n AS c ON c.category_id = p.category_id
             JOIN ?n AS t ON t.type_id = c.type_id
             WHERE p.status = ?s
               AND p.language_code = ?s
               AND c.status = ?s
               AND c.language_code = ?s
               AND t.language_code = ?s
               AND t.name IN (?a)
               AND p.title NOT LIKE ?s
               AND c.title NOT LIKE ?s
             ORDER BY p.updated_at DESC, p.page_id DESC
             LIMIT ?i',
            Constants::PAGES_TABLE,
            Constants::CATEGORIES_TABLE,
            Constants::CATEGORIES_TYPES_TABLE,
            'active',
            $languageCode,
            'active',
            $languageCode,
            $languageCode,
            ['Тип записи: Objects', 'Objects', 'Объекты'],
            'Запись #%',
            'Без категории%',
            max(1, $limit)
        );
        if (!$rows) {
            return [];
        }

        $pageIds = array_map(static fn($row): int => (int) ($row['page_id'] ?? 0), $rows);
        $previewMap = $this->getPagePreviewPropertyMap($pageIds, $languageCode);
        $cards = [];
        foreach ($rows as $row) {
            $pageId = (int) ($row['page_id'] ?? 0);
            $preview = $previewMap[$pageId] ?? [];
            $cards[] = [
                'page_id' => $pageId,
                'title' => trim((string) ($row['title'] ?? '')),
                'url' => EntityPublicUrlService::buildEntityUrl('page', $pageId, $languageCode),
                'summary' => $this->buildCardSummary((string) ($row['short_description'] ?? ''), (string) ($row['description'] ?? '')),
                'image' => $preview['image'] ?? null,
                'object_type' => trim((string) ($preview['object_type'] ?? '')),
                'distance_to_sea' => trim((string) ($preview['distance_to_sea'] ?? '')),
                'price_from' => trim((string) ($preview['price_from'] ?? '')),
                'category_title' => trim((string) ($row['category_title'] ?? '')),
            ];
        }

        return $cards;
    }

    private function getHomeStats(string $languageCode): array {
        return [
            'regions_total' => (int) SafeMySQL::gi()->getOne(
                'SELECT COUNT(*)
                 FROM ?n AS c
                 JOIN ?n AS t ON t.type_id = c.type_id
                 WHERE c.parent_id IS NULL AND c.status = ?s AND c.language_code = ?s
                   AND t.language_code = ?s AND t.name IN (?a)',
                Constants::CATEGORIES_TABLE,
                Constants::CATEGORIES_TYPES_TABLE,
                'active',
                $languageCode,
                $languageCode,
                ['Resorts', 'Курорты']
            ),
            'resorts_total' => (int) SafeMySQL::gi()->getOne(
                'SELECT COUNT(*)
                 FROM ?n AS c
                 JOIN ?n AS t ON t.type_id = c.type_id
                 WHERE c.parent_id IS NOT NULL AND c.status = ?s AND c.language_code = ?s
                   AND t.language_code = ?s AND t.name IN (?a)',
                Constants::CATEGORIES_TABLE,
                Constants::CATEGORIES_TYPES_TABLE,
                'active',
                $languageCode,
                $languageCode,
                ['Resorts', 'Курорты']
            ),
            'objects_total' => (int) SafeMySQL::gi()->getOne(
                'SELECT COUNT(*)
                 FROM ?n AS p
                 JOIN ?n AS c ON c.category_id = p.category_id
                 JOIN ?n AS t ON t.type_id = c.type_id
                 WHERE p.status = ?s AND p.language_code = ?s
                   AND c.language_code = ?s
                   AND t.language_code = ?s
                   AND t.name IN (?a)',
                Constants::PAGES_TABLE,
                Constants::CATEGORIES_TABLE,
                Constants::CATEGORIES_TYPES_TABLE,
                'active',
                $languageCode,
                $languageCode,
                $languageCode,
                ['Тип записи: Objects', 'Objects', 'Объекты']
            ),
            'owners_total' => (int) SafeMySQL::gi()->getOne(
                'SELECT COUNT(DISTINCT user_id) FROM ?n',
                Constants::PAGE_USER_LINKS_TABLE
            ),
        ];
    }

    private function getEntityPropertyMap(string $entityType, int $entityId, string $languageCode): array {
        $rows = SafeMySQL::gi()->getAll(
            'SELECT p.property_id, p.name, p.default_values, p.is_multiple, p.is_required, pt.fields AS type_fields, pv.property_values
             FROM ?n AS pv
             JOIN ?n AS p ON p.property_id = pv.property_id
             LEFT JOIN ?n AS pt ON pt.type_id = p.type_id
             WHERE pv.entity_type = ?s AND pv.entity_id = ?i AND pv.language_code = ?s
             ORDER BY p.property_id ASC',
            Constants::PROPERTY_VALUES_TABLE,
            Constants::PROPERTIES_TABLE,
            Constants::PROPERTY_TYPES_TABLE,
            $entityType,
            $entityId,
            $languageCode
        );

        $map = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $runtimeFields = PropertyFieldContract::buildRuntimeFields(
                $row['default_values'] ?? [],
                $row['property_values'] ?? [],
                $row['type_fields'] ?? [],
                $row
            );
            $map[$name] = [
                'name' => $name,
                'runtime_fields' => $runtimeFields,
                'row' => $row,
            ];
        }

        return $map;
    }

    private function filterLanguageLinks(array $links): array {
        return array_values(array_filter($links, static function ($item): bool {
            return is_array($item)
                && !empty($item['href'])
                && strtoupper((string) ($item['language_code'] ?? '')) !== 'X-DEFAULT';
        }));
    }

    private function getPageOwner(int $pageId): array {
        $row = SafeMySQL::gi()->getRow(
            'SELECT pu.user_id, u.name, u.email, u.phone
             FROM ?n AS pu
             JOIN ?n AS u ON u.user_id = pu.user_id
             WHERE pu.page_id = ?i
             LIMIT 1',
            Constants::PAGE_USER_LINKS_TABLE,
            Constants::USERS_TABLE,
            $pageId
        );

        if (!$row) {
            return [];
        }

        $phone = trim((string) ($row['phone'] ?? ''));
        return [
            'user_id' => (int) ($row['user_id'] ?? 0),
            'name' => trim((string) ($row['name'] ?? '')),
            'email' => trim((string) ($row['email'] ?? '')),
            'phone' => $phone,
            'phone_href' => $phone !== '' ? ('tel:' . preg_replace('~[^0-9+]~', '', $phone)) : '',
        ];
    }

    private function getPageCardsByCategory(int $categoryId, string $languageCode, int $excludePageId = 0, int $limit = 12): array {
        if ($categoryId <= 0) {
            return [];
        }

        $sql = 'SELECT page_id, title, slug, short_description, description
                FROM ?n
                WHERE category_id = ?i AND status = ?s AND language_code = ?s';
        $params = [Constants::PAGES_TABLE, $categoryId, 'active', $languageCode];
        if ($excludePageId > 0) {
            $sql .= ' AND page_id != ?i';
            $params[] = $excludePageId;
        }
        $sql .= ' ORDER BY title ASC';
        if ($limit > 0) {
            $sql .= ' LIMIT ?i';
            $params[] = $limit;
        }

        $rows = SafeMySQL::gi()->getAll($sql, ...$params);
        if (!$rows) {
            return [];
        }

        $pageIds = array_map(static fn($row): int => (int) ($row['page_id'] ?? 0), $rows);
        $previewMap = $this->getPagePreviewPropertyMap($pageIds, $languageCode);
        $cards = [];

        foreach ($rows as $row) {
            $pageId = (int) ($row['page_id'] ?? 0);
            $preview = $previewMap[$pageId] ?? [];
            $cards[] = [
                'page_id' => $pageId,
                'title' => trim((string) ($row['title'] ?? '')),
                'url' => EntityPublicUrlService::buildEntityUrl('page', $pageId, $languageCode),
                'summary' => $this->buildCardSummary((string) ($row['short_description'] ?? ''), (string) ($row['description'] ?? '')),
                'image' => $preview['image'] ?? null,
                'object_type' => trim((string) ($preview['object_type'] ?? '')),
                'distance_to_sea' => trim((string) ($preview['distance_to_sea'] ?? '')),
            ];
        }

        return $cards;
    }

    private function getChildCategoryCards(int $parentCategoryId, string $languageCode, int $limit = 24): array {
        $rows = array_values(array_filter(
            $this->getPublicCategoryRows($languageCode),
            static fn(array $row): bool => (int) ($row['parent_id'] ?? 0) === $parentCategoryId
        ));
        usort($rows, static fn(array $left, array $right): int => strcmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? '')));
        $rows = array_slice($rows, 0, max(1, $limit));
        if (!$rows) {
            return [];
        }

        $categoryIds = array_map(static fn($row): int => (int) ($row['category_id'] ?? 0), $rows);
        $countMap = $this->getRecursiveCategoryPageCountMap($languageCode);

        $previewMap = $this->getCategoryPreviewPropertyMap($categoryIds, $languageCode);
        $cards = [];
        foreach ($rows as $row) {
            $categoryId = (int) ($row['category_id'] ?? 0);
            $preview = $previewMap[$categoryId] ?? [];
            $cards[] = [
                'category_id' => $categoryId,
                'title' => trim((string) ($row['title'] ?? '')),
                'url' => EntityPublicUrlService::buildEntityUrl('category', $categoryId, $languageCode),
                'summary' => $this->buildCardSummary((string) ($row['short_description'] ?? ''), (string) ($row['description'] ?? '')),
                'image' => $preview['image'] ?? null,
                'pages_total' => (int) ($countMap[$categoryId] ?? 0),
            ];
        }

        return $cards;
    }

    private function getCategoryObjectTypeGroups(int $categoryId, string $languageCode): array {
        if ($categoryId <= 0) {
            return [];
        }

        $rows = SafeMySQL::gi()->getAll(
            'SELECT page_id
             FROM ?n
             WHERE category_id = ?i AND status = ?s AND language_code = ?s AND title NOT LIKE ?s',
            Constants::PAGES_TABLE,
            $categoryId,
            'active',
            $languageCode,
            'Запись #%'
        );
        if (!$rows) {
            return [];
        }

        $pageIds = array_map(static fn(array $row): int => (int) ($row['page_id'] ?? 0), $rows);
        $previewMap = $this->getPagePreviewPropertyMap($pageIds, $languageCode);
        $counts = [];
        foreach ($pageIds as $pageId) {
            $objectType = trim((string) ($previewMap[$pageId]['object_type'] ?? ''));
            if ($objectType === '') {
                continue;
            }
            $counts[$objectType] = (int) ($counts[$objectType] ?? 0) + 1;
        }

        if ($counts === []) {
            return [];
        }

        $orderedLabels = [
            'Гостевые дома',
            'Гостиницы и отели',
            'Частный сектор',
            'Коттеджи и дома под ключ',
            'Квартиры',
        ];

        $result = [];
        foreach ($orderedLabels as $label) {
            if (empty($counts[$label])) {
                continue;
            }
            $result[] = [
                'title' => $label,
                'count' => (int) $counts[$label],
            ];
            unset($counts[$label]);
        }

        if ($counts !== []) {
            ksort($counts);
            foreach ($counts as $label => $count) {
                $result[] = [
                    'title' => (string) $label,
                    'count' => (int) $count,
                ];
            }
        }

        return $result;
    }

    private function getPublicCategoryRows(string $languageCode): array {
        $cacheKey = strtoupper(trim($languageCode));
        if (isset($this->categoryRowsCache[$cacheKey])) {
            return $this->categoryRowsCache[$cacheKey];
        }

        $rows = SafeMySQL::gi()->getAll(
            'SELECT category_id, title, parent_id, type_id, short_description, description
             FROM ?n
             WHERE status = ?s AND language_code = ?s
             ORDER BY category_id ASC',
            Constants::CATEGORIES_TABLE,
            'active',
            $languageCode
        );

        $rows = array_values(array_filter($rows, fn(array $row): bool => !$this->isTechnicalCategoryTitle((string) ($row['title'] ?? ''))));
        $this->categoryRowsCache[$cacheKey] = $rows;
        return $rows;
    }

    private function getRecursiveCategoryPageCountMap(string $languageCode): array {
        $cacheKey = strtoupper(trim($languageCode));
        if (isset($this->recursiveCategoryCountCache[$cacheKey])) {
            return $this->recursiveCategoryCountCache[$cacheKey];
        }

        $categories = $this->getPublicCategoryRows($languageCode);
        $categoryMap = [];
        $childrenMap = [];
        foreach ($categories as $category) {
            $categoryId = (int) ($category['category_id'] ?? 0);
            if ($categoryId <= 0) {
                continue;
            }
            $categoryMap[$categoryId] = $category;
            $parentId = (int) ($category['parent_id'] ?? 0);
            $childrenMap[$parentId][] = $categoryId;
        }

        $directCounts = [];
        if ($categoryMap !== []) {
            $countRows = SafeMySQL::gi()->getAll(
                'SELECT category_id, COUNT(*) AS pages_total
                 FROM ?n
                 WHERE category_id IN (?a) AND status = ?s AND language_code = ?s AND title NOT LIKE ?s
                 GROUP BY category_id',
                Constants::PAGES_TABLE,
                array_keys($categoryMap),
                'active',
                $languageCode,
                'Запись #%'
            );
            foreach ($countRows as $countRow) {
                $directCounts[(int) ($countRow['category_id'] ?? 0)] = (int) ($countRow['pages_total'] ?? 0);
            }
        }

        $resolved = [];
        $walk = function (int $categoryId) use (&$walk, &$resolved, $childrenMap, $directCounts): int {
            if (isset($resolved[$categoryId])) {
                return $resolved[$categoryId];
            }
            $total = (int) ($directCounts[$categoryId] ?? 0);
            foreach ($childrenMap[$categoryId] ?? [] as $childId) {
                $total += $walk((int) $childId);
            }
            $resolved[$categoryId] = $total;
            return $total;
        };

        foreach (array_keys($categoryMap) as $categoryId) {
            $walk((int) $categoryId);
        }

        $this->recursiveCategoryCountCache[$cacheKey] = $resolved;
        return $resolved;
    }

    private function getPagePreviewPropertyMap(array $pageIds, string $languageCode): array {
        $pageIds = array_values(array_filter(array_map('intval', $pageIds), static fn(int $id): bool => $id > 0));
        if ($pageIds === []) {
            return [];
        }

        $galleryPropertyId = $this->getPropertyIdByName('Фотографии объекта');
        $typePropertyId = $this->getPropertyIdByName('Тип объекта');
        $distancePropertyId = $this->getPropertyIdByName('Удаленность от моря');
        $pricePropertyId = $this->getPropertyIdByName('Цена от');
        $propertyIds = array_values(array_filter([$galleryPropertyId, $typePropertyId, $distancePropertyId, $pricePropertyId], static fn(int $id): bool => $id > 0));
        if ($propertyIds === []) {
            return [];
        }

        $rows = SafeMySQL::gi()->getAll(
            'SELECT pv.entity_id, p.name, p.default_values, pt.fields AS type_fields, pv.property_values
             FROM ?n AS pv
             JOIN ?n AS p ON p.property_id = pv.property_id
             LEFT JOIN ?n AS pt ON pt.type_id = p.type_id
             WHERE pv.entity_type = ?s AND pv.entity_id IN (?a) AND pv.language_code = ?s AND pv.property_id IN (?a)',
            Constants::PROPERTY_VALUES_TABLE,
            Constants::PROPERTIES_TABLE,
            Constants::PROPERTY_TYPES_TABLE,
            'page',
            $pageIds,
            $languageCode,
            $propertyIds
        );

        $map = [];
        foreach ($rows as $row) {
            $entityId = (int) ($row['entity_id'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));
            if ($entityId <= 0 || $name === '') {
                continue;
            }
            $property = [
                'name' => $name,
                'runtime_fields' => PropertyFieldContract::buildRuntimeFields(
                    $row['default_values'] ?? [],
                    $row['property_values'] ?? [],
                    $row['type_fields'] ?? [],
                    $row
                ),
                'row' => $row,
            ];

            if ($name === 'Фотографии объекта' && empty($map[$entityId]['image'])) {
                $image = $this->extractFirstGalleryItem($property);
                if ($image) {
                    $map[$entityId]['image'] = $image;
                }
            } elseif ($name === 'Тип объекта') {
                $map[$entityId]['object_type'] = $this->extractChoiceSummary($property);
            } elseif ($name === 'Удаленность от моря') {
                $map[$entityId]['distance_to_sea'] = $this->extractScalarValue($property);
            } elseif ($name === 'Цена от') {
                $map[$entityId]['price_from'] = $this->extractScalarValue($property);
            }
        }

        return $map;
    }

    private function getCategoryPreviewPropertyMap(array $categoryIds, string $languageCode): array {
        $categoryIds = array_values(array_filter(array_map('intval', $categoryIds), static fn(int $id): bool => $id > 0));
        if ($categoryIds === []) {
            return [];
        }

        $galleryPropertyId = $this->getPropertyIdByName('Фотографии курорта');
        if ($galleryPropertyId <= 0) {
            return [];
        }

        $map = $this->loadDirectCategoryPreviewPropertyMap($categoryIds, $languageCode, $galleryPropertyId);
        foreach ($categoryIds as $categoryId) {
            if (!empty($map[$categoryId]['image'])) {
                continue;
            }

            $descendantIds = array_values(array_filter(
                $this->collectCategoryDescendantIds($categoryId, $languageCode),
                static fn(int $id): bool => $id > 0 && $id !== $categoryId
            ));
            if ($descendantIds === []) {
                continue;
            }

            $fallbackMap = $this->loadDirectCategoryPreviewPropertyMap($descendantIds, $languageCode, $galleryPropertyId);
            foreach ($descendantIds as $descendantId) {
                if (!empty($fallbackMap[$descendantId]['image'])) {
                    $map[$categoryId]['image'] = $fallbackMap[$descendantId]['image'];
                    break;
                }
            }
        }

        return $map;
    }

    private function loadDirectCategoryPreviewPropertyMap(array $categoryIds, string $languageCode, int $galleryPropertyId): array {
        $categoryIds = array_values(array_filter(array_map('intval', $categoryIds), static fn(int $id): bool => $id > 0));
        if ($categoryIds === [] || $galleryPropertyId <= 0) {
            return [];
        }

        $rows = SafeMySQL::gi()->getAll(
            'SELECT pv.entity_id, p.name, p.default_values, pt.fields AS type_fields, pv.property_values
             FROM ?n AS pv
             JOIN ?n AS p ON p.property_id = pv.property_id
             LEFT JOIN ?n AS pt ON pt.type_id = p.type_id
             WHERE pv.entity_type = ?s AND pv.entity_id IN (?a) AND pv.language_code = ?s AND pv.property_id = ?i',
            Constants::PROPERTY_VALUES_TABLE,
            Constants::PROPERTIES_TABLE,
            Constants::PROPERTY_TYPES_TABLE,
            'category',
            $categoryIds,
            $languageCode,
            $galleryPropertyId
        );

        $map = [];
        foreach ($rows as $row) {
            $entityId = (int) ($row['entity_id'] ?? 0);
            if ($entityId <= 0) {
                continue;
            }
            $property = [
                'name' => trim((string) ($row['name'] ?? '')),
                'runtime_fields' => PropertyFieldContract::buildRuntimeFields(
                    $row['default_values'] ?? [],
                    $row['property_values'] ?? [],
                    $row['type_fields'] ?? [],
                    $row
                ),
                'row' => $row,
            ];
            $image = $this->extractFirstGalleryItem($property);
            if ($image) {
                $map[$entityId]['image'] = $image;
            }
        }

        return $map;
    }

    private function extractGalleryItems(?array $property): array {
        if (!$property) {
            return [];
        }

        $items = [];
        foreach ((array) ($property['runtime_fields'] ?? []) as $field) {
            if (($field['type'] ?? '') !== 'image') {
                continue;
            }
            foreach ($this->extractFileItemsFromField($field) as $item) {
                $items[$item['unique_id']] = $item;
            }
        }

        return array_values($items);
    }

    private function extractFirstGalleryItem(?array $property): ?array {
        $gallery = $this->extractGalleryItems($property);
        return $gallery[0] ?? null;
    }

    private function extractSiteContentImageUrl(?array $property, string $fallback = ''): string {
        $item = $this->extractFirstGalleryItem($property);
        if (!empty($item['file_url'])) {
            return trim((string) $item['file_url']);
        }

        return trim($fallback);
    }

    private function extractFileItemsFromField(array $field): array {
        $references = FileSystem::normalizeFileReferences($field['value'] ?? []);
        $items = [];
        foreach ($references as $reference) {
            $descriptor = FileSystem::describeFileReference($reference);
            if (!$descriptor || empty($descriptor['file_url'])) {
                continue;
            }
            $items[] = $descriptor;
        }
        return $items;
    }

    private function extractChoiceSummary(?array $property): string {
        $items = $this->extractChoiceList($property);
        return $items[0] ?? '';
    }

    private function extractChoiceList(?array $property): array {
        if (!$property) {
            return [];
        }

        $labels = [];
        foreach ((array) ($property['runtime_fields'] ?? []) as $field) {
            $value = $this->extractFieldDisplayValue($field);
            if (is_array($value)) {
                foreach ($value as $item) {
                    $item = trim((string) $item);
                    if ($item !== '') {
                        $labels[] = $item;
                    }
                }
                continue;
            }
            $value = trim((string) $value);
            if ($value !== '') {
                $labels[] = $value;
            }
        }

        return array_values(array_unique($labels));
    }

    private function extractScalarValue(?array $property, string $fallback = ''): string {
        if (!$property) {
            return trim($fallback);
        }

        foreach ((array) ($property['runtime_fields'] ?? []) as $field) {
            if (in_array((string) ($field['type'] ?? ''), ['image', 'file'], true)) {
                continue;
            }

            $value = $this->extractFieldDisplayValue($field);
            if (is_array($value)) {
                $value = implode(', ', array_filter(array_map(static fn($item): string => trim((string) $item), $value)));
            }
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return trim($fallback);
    }

    private function extractStoredScalarValue(?array $property, string $fallback = ''): string {
        if (!$property) {
            return trim($fallback);
        }

        foreach ((array) ($property['runtime_fields'] ?? []) as $field) {
            $value = $field['value'] ?? null;
            if (is_array($value)) {
                foreach ($value as $item) {
                    $itemString = trim((string) $item);
                    if ($itemString !== '') {
                        return $itemString;
                    }
                }
                continue;
            }

            $valueString = trim((string) $value);
            if ($valueString !== '') {
                return $valueString;
            }
        }

        return trim($fallback);
    }

    private function normalizeReviewStatusValue(string $value): string {
        $value = trim(mb_strtolower($value));
        return match ($value) {
            'approved', 'одобрен' => 'approved',
            'rejected', 'отклонён', 'отклонен' => 'rejected',
            'pending-review', 'pending_review', 'на модерации' => 'pending-review',
            'draft', 'черновик' => 'draft',
            default => $value,
        };
    }

    private function normalizeReviewVisibilityValue(string $value): string {
        $value = trim(mb_strtolower($value));
        return match ($value) {
            'yes', 'да' => 'yes',
            'no', 'нет' => 'no',
            default => $value,
        };
    }

    private function extractPhoneEntries(?array $property): array {
        if (!$property) {
            return [];
        }

        $entries = [];
        foreach ((array) ($property['runtime_fields'] ?? []) as $field) {
            $type = trim((string) ($field['type'] ?? ''));
            if (!in_array($type, ['phone', 'text'], true)) {
                continue;
            }

            $fieldValues = $this->expandRoomFieldValues($field);
            if ($fieldValues === []) {
                $fieldValues = [$field['value'] ?? null];
            }

            foreach ($fieldValues as $index => $fieldValue) {
                $value = $this->extractFieldDisplayValueForRoomValue($field, $fieldValue);
                $valueString = is_array($value)
                    ? implode(', ', array_filter(array_map(static fn($item): string => trim((string) $item), $value)))
                    : trim((string) $value);
                if ($valueString === '') {
                    continue;
                }
                if (!isset($entries[$index])) {
                    $entries[$index] = ['phone' => '', 'comment' => ''];
                }
                if ($type === 'phone') {
                    $entries[$index]['phone'] = $valueString;
                } elseif ($entries[$index]['comment'] === '') {
                    $entries[$index]['comment'] = $valueString;
                }
            }
        }

        $normalized = [];
        foreach ($entries as $entry) {
            $finalEntry = $this->finalizePhoneEntry($entry);
            if ($finalEntry['phone'] === '' && $finalEntry['comment'] === '') {
                continue;
            }
            $normalized[] = $finalEntry;
        }

        return $normalized;
    }

    private function finalizePhoneEntry(array $entry): array {
        $phone = trim((string) ($entry['phone'] ?? ''));
        $comment = trim((string) ($entry['comment'] ?? ''));
        return [
            'phone' => $phone,
            'comment' => $comment,
            'tel_href' => $phone !== '' ? ('tel:' . preg_replace('~[^0-9+]~', '', $phone)) : '',
        ];
    }

    private function extractMapData(?array $property, string $title = ''): ?array {
        if (!$property) {
            return null;
        }

        $coords = '';
        $zoom = '';
        foreach ((array) ($property['runtime_fields'] ?? []) as $field) {
            $label = trim((string) ($field['label'] ?? ''));
            $value = trim((string) $this->extractFieldDisplayValue($field));
            if ($value === '') {
                continue;
            }
            if ($coords === '' && (($field['type'] ?? '') === 'text' || stripos($label, 'координат') !== false)) {
                $coords = $value;
                continue;
            }
            if ($zoom === '' && (($field['type'] ?? '') === 'number' || stripos($label, 'масштаб') !== false)) {
                $zoom = $value;
            }
        }

        if ($coords === '') {
            return null;
        }

        $parts = array_map('trim', explode(',', $coords));
        if (count($parts) < 2) {
            return null;
        }

        $lat = (float) str_replace(',', '.', $parts[0]);
        $lng = (float) str_replace(',', '.', $parts[1]);
        if (!$lat && !$lng) {
            return null;
        }

        $zoomValue = (int) preg_replace('~[^0-9]~', '', $zoom);
        if ($zoomValue <= 0) {
            $zoomValue = 15;
        }

        return PublicMapProvider::build($lat, $lng, $zoomValue, $title);
    }

    private function extractRoomOffer(?array $property, string $languageCode = ENV_DEF_LANG): array {
        if (!$property) {
            return [];
        }

        $runtimeFields = (array) ($property['runtime_fields'] ?? []);
        if ($runtimeFields === []) {
            return [];
        }

        $rooms = [];
        foreach ($runtimeFields as $field) {
            $label = trim((string) ($field['label'] ?? ''));
            $fieldValues = $this->expandRoomFieldValues($field);
            if ($fieldValues === []) {
                $fieldValues = [$field['value'] ?? null];
            }

            foreach ($fieldValues as $roomIndex => $roomValue) {
                if (!isset($rooms[$roomIndex])) {
                    $rooms[$roomIndex] = [
                        'name' => '',
                        'facts' => [],
                        'amenities' => [],
                        'description_html' => '',
                        'gallery' => [],
                        'price_mode' => '',
                        'periods' => [],
                    ];
                }
                $value = $this->extractFieldDisplayValueForRoomValue($field, $roomValue);
                $valueString = is_array($value)
                    ? implode(', ', array_filter(array_map(static fn($item): string => trim((string) $item), $value)))
                    : trim((string) $value);

                if (($field['type'] ?? '') === 'image' || $label === 'Фотографии номера') {
                    $gallery = $this->extractFileItemsFromRawValue($roomValue);
                    if ($gallery !== []) {
                        $rooms[$roomIndex]['gallery'] = $gallery;
                    }
                    continue;
                }

                if ($label === '' || $valueString === '') {
                    continue;
                }

                if ($label === 'Название номера') {
                    $rooms[$roomIndex]['name'] = $valueString;
                    continue;
                }

                if ($label === 'Дополнительные данные') {
                    $rooms[$roomIndex]['description_html'] = $this->normalizeRichTextHtml($valueString, $languageCode);
                    continue;
                }

                if ($label === 'Цены указываются') {
                    $rooms[$roomIndex]['price_mode'] = $valueString;
                    continue;
                }

                if (preg_match('/^Период\s+(\d+):\s+дата\s+с$/u', $label, $matches)) {
                    $rooms[$roomIndex]['periods'][(int) $matches[1]]['from'] = $valueString;
                    continue;
                }
                if (preg_match('/^Период\s+(\d+):\s+дата\s+по$/u', $label, $matches)) {
                    $rooms[$roomIndex]['periods'][(int) $matches[1]]['to'] = $valueString;
                    continue;
                }
                if (preg_match('/^Период\s+(\d+):\s+цена$/u', $label, $matches)) {
                    $rooms[$roomIndex]['periods'][(int) $matches[1]]['price'] = $valueString;
                    continue;
                }

                if (in_array($label, ['Спальных мест', 'Доп. места', 'Комнат в номере', 'Площадь номера'], true)) {
                    $rooms[$roomIndex]['facts'][] = ['label' => $label, 'value' => $valueString];
                    continue;
                }

                if (in_array($label, ['Туалет', 'Душ', 'Кондиционер', 'Телевизор', 'Спутниковое или кабельное ТВ', 'Wi‑Fi', 'Сейф', 'Холодильник', 'Кухня', 'Балкон'], true)) {
                    if (mb_strtolower($valueString) !== 'нет') {
                        $rooms[$roomIndex]['amenities'][] = $label . ': ' . $valueString;
                    }
                }
            }
        }

        $normalizedRooms = [];
        ksort($rooms);
        foreach ($rooms as $room) {
            $periods = [];
            if (!empty($room['periods']) && is_array($room['periods'])) {
                ksort($room['periods']);
                foreach ($room['periods'] as $period) {
                    $from = trim((string) ($period['from'] ?? ''));
                    $to = trim((string) ($period['to'] ?? ''));
                    $price = trim((string) ($period['price'] ?? ''));
                    if ($from === '' && $to === '' && $price === '') {
                        continue;
                    }
                    $periods[] = [
                        'from' => $from,
                        'to' => $to,
                        'price' => $price,
                    ];
                }
            }
            $room['periods'] = $periods;
            if ($room['name'] === '' && $room['description_html'] === '' && $room['gallery'] === [] && $room['periods'] === []) {
                continue;
            }
            $normalizedRooms[] = $room;
        }

        return $normalizedRooms;
    }

    private function expandRoomFieldValues(array $field): array {
        $value = $field['value'] ?? null;
        if (!is_array($value)) {
            return $value === null || $value === '' ? [] : [$value];
        }

        if ($value === []) {
            return [];
        }

        return array_values($value);
    }

    private function extractFieldDisplayValueForRoomValue(array $field, mixed $roomValue): mixed {
        $fieldCopy = $field;
        $fieldCopy['value'] = $roomValue;
        $fieldCopy['multiple'] = is_array($roomValue) && ($field['type'] ?? '') === 'checkbox' ? 1 : 0;
        return $this->extractFieldDisplayValue($fieldCopy);
    }

    private function extractFileItemsFromRawValue(mixed $value): array {
        $references = FileSystem::normalizeFileReferences($value);
        $items = [];
        foreach ($references as $reference) {
            $descriptor = FileSystem::describeFileReference($reference);
            if (!$descriptor || empty($descriptor['file_url'])) {
                continue;
            }
            $items[] = $descriptor;
        }
        return $items;
    }

    private function extractFieldDisplayValue(array $field): mixed {
        $type = strtolower(trim((string) ($field['type'] ?? '')));
        $value = $field['value'] ?? ($field['default'] ?? '');
        if (in_array($type, ['select', 'checkbox', 'radio'], true)) {
            $selected = is_array($value) ? $value : ($value === '' ? [] : [$value]);
            $optionMap = [];
            foreach ((array) ($field['options'] ?? []) as $option) {
                $key = trim((string) ($option['key'] ?? $option['value'] ?? ''));
                if ($key === '') {
                    continue;
                }
                $optionMap[$key] = trim((string) ($option['label'] ?? $key));
            }
            $labels = [];
            foreach ($selected as $selectedValue) {
                $selectedKey = trim((string) $selectedValue);
                if ($selectedKey === '') {
                    continue;
                }
                $labels[] = $optionMap[$selectedKey] ?? $selectedKey;
            }
            return !empty($field['multiple']) ? $labels : ($labels[0] ?? '');
        }

        return $value;
    }

    private function normalizeRichTextHtml(string $html, string $languageCode = ENV_DEF_LANG): string {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        $html = WordpressImporter::normalizeImportedRichText($html);
        if ($html === '') {
            return '';
        }
        return $this->sanitizeRichTextHtmlDom($html, $languageCode);
    }

    private function sanitizeRichTextHtmlDom(string $html, string $languageCode): string {
        if (!class_exists(\DOMDocument::class)) {
            return $html;
        }

        $document = new \DOMDocument('1.0', 'UTF-8');
        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="utf-8" ?><div data-allbriz-richtext-root="1">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);
        if (!$loaded) {
            return $html;
        }

        $root = $document->getElementsByTagName('div')->item(0);
        if (!$root instanceof \DOMElement) {
            return $html;
        }

        $this->sanitizeRichTextNode($root, $languageCode);

        return $this->renderDomChildren($root);
    }

    private function sanitizeRichTextNode(\DOMNode $parentNode, string $languageCode): void {
        $childNodes = [];
        foreach ($parentNode->childNodes as $childNode) {
            $childNodes[] = $childNode;
        }

        foreach ($childNodes as $childNode) {
            if (!$childNode instanceof \DOMElement) {
                continue;
            }

            $tagName = mb_strtolower($childNode->tagName);
            if ($this->isRichTextBlockedTag($tagName)) {
                $parentNode->removeChild($childNode);
                continue;
            }

            if (!$this->isRichTextAllowedTag($tagName)) {
                $this->sanitizeRichTextNode($childNode, $languageCode);
                $this->unwrapDomNode($childNode);
                continue;
            }

            if (!$this->sanitizeRichTextElementAttributes($childNode, $tagName, $languageCode)) {
                continue;
            }

            $this->sanitizeRichTextNode($childNode, $languageCode);
        }
    }

    private function isRichTextAllowedTag(string $tagName): bool {
        static $allowedTags = [
            'a' => true,
            'b' => true,
            'blockquote' => true,
            'br' => true,
            'div' => true,
            'em' => true,
            'figcaption' => true,
            'figure' => true,
            'h2' => true,
            'h3' => true,
            'h4' => true,
            'h5' => true,
            'h6' => true,
            'hr' => true,
            'i' => true,
            'img' => true,
            'li' => true,
            'ol' => true,
            'p' => true,
            'span' => true,
            'strong' => true,
            'table' => true,
            'tbody' => true,
            'td' => true,
            'th' => true,
            'thead' => true,
            'tr' => true,
            'u' => true,
            'ul' => true,
        ];

        return isset($allowedTags[$tagName]);
    }

    private function isRichTextBlockedTag(string $tagName): bool {
        static $blockedTags = [
            'audio' => true,
            'base' => true,
            'button' => true,
            'canvas' => true,
            'embed' => true,
            'fieldset' => true,
            'form' => true,
            'frame' => true,
            'frameset' => true,
            'iframe' => true,
            'input' => true,
            'link' => true,
            'map' => true,
            'math' => true,
            'meta' => true,
            'noscript' => true,
            'object' => true,
            'option' => true,
            'script' => true,
            'select' => true,
            'source' => true,
            'style' => true,
            'svg' => true,
            'textarea' => true,
            'video' => true,
        ];

        return isset($blockedTags[$tagName]);
    }

    private function sanitizeRichTextElementAttributes(\DOMElement $element, string $tagName, string $languageCode): bool {
        $attributes = [];
        foreach ($element->attributes as $attributeNode) {
            $attributes[] = $attributeNode;
        }

        foreach ($attributes as $attributeNode) {
            $attributeName = mb_strtolower($attributeNode->nodeName);
            $attributeValue = (string) $attributeNode->nodeValue;

            if (
                str_starts_with($attributeName, 'on')
                || in_array($attributeName, ['style', 'srcset', 'formaction', 'xmlns', 'xlink:href'], true)
            ) {
                $element->removeAttributeNode($attributeNode);
                continue;
            }

            if ($tagName === 'a' && $attributeName === 'href') {
                $resolvedHref = $this->resolveRichTextHref($attributeValue, $languageCode);
                if ($resolvedHref === '') {
                    $this->unwrapDomNode($element);
                    return false;
                }

                $element->setAttribute('href', $resolvedHref);
                continue;
            }

            if ($tagName === 'img' && $attributeName === 'src') {
                $safeSource = $this->sanitizeRichTextResourceUrl($attributeValue);
                if ($safeSource === '') {
                    if ($element->parentNode instanceof \DOMNode) {
                        $element->parentNode->removeChild($element);
                    }
                    return false;
                }

                $element->setAttribute('src', $safeSource);
                continue;
            }

            if (!$this->isRichTextAllowedAttribute($tagName, $attributeName)) {
                $element->removeAttributeNode($attributeNode);
            }
        }

        if ($tagName === 'a') {
            $href = trim((string) $element->getAttribute('href'));
            if ($href === '') {
                $this->unwrapDomNode($element);
                return false;
            }

            $element->setAttribute('rel', 'nofollow noopener noreferrer');
        }

        if ($tagName === 'img') {
            if (!$element->hasAttribute('loading')) {
                $element->setAttribute('loading', 'lazy');
            }
            if (!$element->hasAttribute('decoding')) {
                $element->setAttribute('decoding', 'async');
            }
        }

        return true;
    }

    private function isRichTextAllowedAttribute(string $tagName, string $attributeName): bool {
        static $defaultAttributes = [
            'colspan' => true,
            'rowspan' => true,
        ];

        static $tagAttributes = [
            'a' => [
                'href' => true,
                'title' => true,
                'target' => true,
                'rel' => true,
            ],
            'img' => [
                'src' => true,
                'alt' => true,
                'title' => true,
                'width' => true,
                'height' => true,
                'loading' => true,
                'decoding' => true,
            ],
            'td' => [
                'colspan' => true,
                'rowspan' => true,
            ],
            'th' => [
                'colspan' => true,
                'rowspan' => true,
            ],
        ];

        return isset($defaultAttributes[$attributeName]) || isset($tagAttributes[$tagName][$attributeName]);
    }

    private function sanitizeRichTextResourceUrl(string $resourceUrl): string {
        $resourceUrl = trim(html_entity_decode($resourceUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($resourceUrl === '') {
            return '';
        }

        if (preg_match('~^(javascript:|data:|vbscript:|file:)~iu', $resourceUrl)) {
            return '';
        }

        return $resourceUrl;
    }

    private function resolveRichTextHref(string $href, string $languageCode): string {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($href === '') {
            return '';
        }

        if (str_starts_with($href, '#') || preg_match('~^(mailto:|tel:|sms:)~iu', $href)) {
            return $href;
        }

        if (preg_match('~^(javascript:|data:|vbscript:|file:)~iu', $href)) {
            return '';
        }

        $path = '';
        if (preg_match('~^[a-z][a-z0-9+.-]*://~iu', $href)) {
            $host = (string) parse_url($href, PHP_URL_HOST);
            if (!$this->isSupportedInternalHost($host)) {
                return '';
            }
            $path = (string) parse_url($href, PHP_URL_PATH);
        } elseif (str_starts_with($href, '//')) {
            $host = (string) parse_url('https:' . $href, PHP_URL_HOST);
            if (!$this->isSupportedInternalHost($host)) {
                return '';
            }
            $path = (string) parse_url('https:' . $href, PHP_URL_PATH);
        } else {
            $path = $href;
        }

        $path = $this->normalizeInternalPath($path);
        if ($path === '') {
            return '';
        }

        $resolved = EntityPublicUrlService::resolvePath($path, $languageCode);
        if (is_array($resolved)) {
            $resolvedUrl = trim((string) ($resolved['url'] ?? ''));
            if ($resolvedUrl !== '') {
                return $resolvedUrl;
            }
        }

        $redirect = RedirectService::resolveRequestRedirect(
            $path,
            (string) parse_url((string) ENV_URL_SITE, PHP_URL_HOST),
            $languageCode
        );
        if (is_array($redirect)) {
            $targetUrl = trim((string) ($redirect['target_url'] ?? ''));
            if ($targetUrl !== '') {
                return $targetUrl;
            }
        }

        return '';
    }

    private function isSupportedInternalHost(string $host): bool {
        $host = $this->normalizeHostForComparison($host);
        if ($host === '') {
            return false;
        }

        $allowedHosts = [
            $this->normalizeHostForComparison((string) parse_url((string) ENV_URL_SITE, PHP_URL_HOST)),
            $this->normalizeHostForComparison((string) ENV_CANONICAL_HOST),
            'allbriz.ru',
        ];

        return in_array($host, array_values(array_filter(array_unique($allowedHosts))), true);
    }

    private function normalizeHostForComparison(string $host): string {
        $host = mb_strtolower(trim($host));
        if ($host === '') {
            return '';
        }

        $host = preg_replace('~:\d+$~', '', $host) ?? $host;
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return trim($host);
    }

    private function normalizeInternalPath(string $path): string {
        $path = trim((string) parse_url($path, PHP_URL_PATH));
        if ($path === '') {
            return '';
        }

        $path = '/' . ltrim($path, '/');
        $path = preg_replace('~/+~', '/', $path) ?? $path;
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $path;
    }

    private function unwrapDomNode(\DOMElement $node): void {
        $parent = $node->parentNode;
        if ($parent === null) {
            return;
        }

        while ($node->firstChild !== null) {
            $parent->insertBefore($node->firstChild, $node);
        }
        $parent->removeChild($node);
    }

    private function renderDomChildren(\DOMElement $root): string {
        $html = '';
        foreach ($root->childNodes as $childNode) {
            $html .= $root->ownerDocument?->saveHTML($childNode) ?? '';
        }
        return trim($html);
    }

    private function resolveSummary(string $shortDescription, string $fallbackHtml): string {
        $shortDescription = trim($shortDescription);
        if ($shortDescription !== '') {
            $normalized = mb_strtolower(trim($shortDescription));
            if (!preg_match('~^[a-z0-9\\-_]+$~u', $normalized)) {
                return $this->truncateText($this->extractPlainText($shortDescription), 220);
            }
        }

        return $this->truncateText($this->extractPlainText($fallbackHtml), 220);
    }

    private function buildCardSummary(string $shortDescription, string $fallbackHtml, int $limit = 170): string {
        $summary = $this->resolveSummary($shortDescription, $fallbackHtml);
        if ($summary === '') {
            return '';
        }
        return SysClass::truncateString($summary, $limit);
    }

    private function extractPlainText(string $html): string {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('~\\s+~u', ' ', $text) ?? $text;
        return trim($text);
    }

    private function truncateText(string $text, int $limit): string {
        $text = trim($text);
        if ($text === '' || mb_strlen($text) <= $limit) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, max(1, $limit - 1))) . '…';
    }

    private function extractFirstImageUrlFromHtml(string $html): string {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        if (preg_match('~<img[^>]+src=[\'"]([^\'"]+)[\'"]~iu', $html, $matches)) {
            return trim((string) ($matches[1] ?? ''));
        }

        return '';
    }

    private function removeLeadingImageFromHtml(string $html, string $imageUrl): string {
        $html = trim($html);
        $imageUrl = trim($imageUrl);
        if ($html === '' || $imageUrl === '' || !class_exists(\DOMDocument::class)) {
            return $html;
        }

        $document = new \DOMDocument('1.0', 'UTF-8');
        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="utf-8" ?><div data-allbriz-richtext-root="1">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);
        if (!$loaded) {
            return $html;
        }

        $root = $document->getElementsByTagName('div')->item(0);
        if (!$root instanceof \DOMElement) {
            return $html;
        }

        foreach ($root->childNodes as $childNode) {
            if (!$childNode instanceof \DOMElement) {
                continue;
            }

            $firstImage = $childNode->getElementsByTagName('img')->item(0);
            if (!$firstImage instanceof \DOMElement) {
                continue;
            }

            $src = trim((string) $firstImage->getAttribute('src'));
            if ($src === '' || $src !== $imageUrl) {
                break;
            }

            $root->removeChild($childNode);
            return $this->renderDomChildren($root);
        }

        return $html;
    }

    private function formatPublicDate(string $dateTime, string $languageCode = ENV_DEF_LANG): string {
        $dateTime = trim($dateTime);
        if ($dateTime === '') {
            return '';
        }

        $timestamp = strtotime($dateTime);
        if ($timestamp === false) {
            return '';
        }

        $monthMap = [
            '01' => 'января',
            '02' => 'февраля',
            '03' => 'марта',
            '04' => 'апреля',
            '05' => 'мая',
            '06' => 'июня',
            '07' => 'июля',
            '08' => 'августа',
            '09' => 'сентября',
            '10' => 'октября',
            '11' => 'ноября',
            '12' => 'декабря',
        ];

        if (strtoupper(trim($languageCode)) === 'RU') {
            $monthKey = date('m', $timestamp);
            return date('d ', $timestamp) . ($monthMap[$monthKey] ?? date('m', $timestamp)) . date(' Y', $timestamp);
        }

        return date('d.m.Y', $timestamp);
    }

    private function getCategoryTypeNameById(int $typeId, string $languageCode): string {
        if ($typeId <= 0) {
            return '';
        }

        $cacheKey = $typeId . '|' . strtoupper(trim($languageCode));
        if (isset($this->categoryTypeNameCache[$cacheKey])) {
            return $this->categoryTypeNameCache[$cacheKey];
        }

        $typeName = (string) SafeMySQL::gi()->getOne(
            'SELECT name FROM ?n WHERE type_id = ?i AND language_code = ?s LIMIT 1',
            Constants::CATEGORIES_TYPES_TABLE,
            $typeId,
            $languageCode
        );
        $this->categoryTypeNameCache[$cacheKey] = trim($typeName);
        return $this->categoryTypeNameCache[$cacheKey];
    }

    private function isResortCategoryTypeId(int $typeId, string $languageCode): bool {
        return $this->isResortCategoryTypeName($this->getCategoryTypeNameById($typeId, $languageCode));
    }

    private function isResortCategoryTypeName(string $typeName): bool {
        return in_array(mb_strtolower(trim($typeName)), ['resorts', 'курорты'], true);
    }

    private function isBlogCategoryTypeName(string $typeName): bool {
        return in_array(mb_strtolower(trim($typeName)), ['блог', 'blog', 'информация', 'information'], true);
    }

    private function isSitePageCategoryTypeName(string $typeName): bool {
        return in_array(mb_strtolower(trim($typeName)), ['страницы сайта', 'site pages', 'страницы', 'pages'], true);
    }

    private function isTechnicalCategoryTitle(string $title): bool {
        $normalized = mb_strtolower(trim($title));
        if ($normalized === '') {
            return true;
        }
        if (str_starts_with($normalized, 'без категории')) {
            return true;
        }
        return in_array($normalized, ['отзывы', 'счета', 'страницы сайта'], true);
    }

    private function makeFact(string $label, string $value): ?array {
        $label = trim($label);
        $value = trim($value);
        if ($label === '' || $value === '') {
            return null;
        }
        return ['label' => $label, 'value' => $value];
    }

    private function normalizeWebsite(string $value): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (!preg_match('~^https?://~iu', $value)) {
            $value = 'https://' . ltrim($value, '/');
        }
        return filter_var($value, FILTER_VALIDATE_URL) ? $value : '';
    }

    private function getPropertyIdByName(string $propertyName): int {
        $propertyName = trim($propertyName);
        if ($propertyName === '') {
            return 0;
        }
        if (isset($this->propertyIdCache[$propertyName])) {
            return $this->propertyIdCache[$propertyName];
        }

        $propertyId = (int) SafeMySQL::gi()->getOne(
            'SELECT property_id
             FROM ?n
             WHERE name = ?s
             ORDER BY property_id ASC
             LIMIT 1',
            Constants::PROPERTIES_TABLE,
            $propertyName
        );
        $this->propertyIdCache[$propertyName] = $propertyId;
        return $propertyId;
    }

    private function langLabel(string $key, string $fallback): string {
        return trim((string) (\classes\system\Lang::get($key, $fallback) ?? $fallback));
    }
}
