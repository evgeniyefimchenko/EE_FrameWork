<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">
            <article class="card shadow-sm">
                <div class="card-body p-4 p-lg-5">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
                        <div>
                            <h1 class="h2 mb-1"><?= htmlspecialchars((string) $legal_document_title) ?></h1>
                            <?php if (!empty($legal_document_version)) { ?>
                                <div class="text-muted small">
                                    <?= htmlspecialchars((string) ($lang['sys.document_version'] ?? 'Версия документа')) ?>:
                                    <?= htmlspecialchars((string) $legal_document_version) ?>
                                </div>
                            <?php } ?>
                        </div>
                        <a href="/" class="btn btn-outline-secondary"><?= htmlspecialchars((string) ($lang['sys.to_main'] ?? 'На главную')) ?></a>
                    </div>
                    <div class="ee-legal-document">
                        <?= $legal_document_html ?>
                    </div>
                </div>
            </article>
        </div>
    </div>
</main>
