<?php

namespace custom\docs;

class ProjectDocsPlugin {

    public static function filterCatalog(array $catalog, ...$context): array {
        unset($context);
        $repository = new ProjectDocsRepository();
        $manifest = $repository->loadManifest();
        return $manifest !== [] ? $manifest : $catalog;
    }

    public static function filterDocument(array $document, string $slug, array $catalog, ...$context): array {
        unset($slug, $catalog, $context);

        $fileName = trim((string) ($document['file'] ?? ''));
        if ($fileName === '') {
            return $document;
        }

        $repository = new ProjectDocsRepository();
        $html = $repository->readDocumentHtml($fileName);
        if ($html !== '') {
            $document['html'] = $html;
        }
        if (empty($document['updated_at'])) {
            $document['updated_at'] = $repository->getDocumentUpdatedAt($fileName);
        }

        return $document;
    }
}
