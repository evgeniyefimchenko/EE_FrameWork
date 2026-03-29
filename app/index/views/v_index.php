<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<div class="ee-welcome-page">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-xl-11">
                <div class="ee-welcome-top-panel mb-4">
                    <?=$top_panel?>
                </div>

                <section class="ee-welcome-hero shadow-sm">
                    <div class="row g-4 align-items-center">
                        <div class="col-lg-7">
                            <span class="ee-welcome-badge"><?= htmlspecialchars((string) ($lang['sys.welcome_kicker'] ?? 'Open-source PHP framework + CMS'), ENT_QUOTES, 'UTF-8') ?></span>
                            <h1 class="ee-welcome-title">
                                <?= htmlspecialchars((string) ($lang['sys.welcome_hero_title'] ?? 'Framework-first CMS core for structured content projects'), ENT_QUOTES, 'UTF-8') ?>
                            </h1>
                            <p class="ee-welcome-subtitle">
                                <?= htmlspecialchars((string) ($lang['sys.welcome_hero_subtitle'] ?? 'A PHP platform for content architecture, imports, routing, properties, and operational automation.'), ENT_QUOTES, 'UTF-8') ?>
                            </p>
                            <div class="ee-welcome-proof-row">
                                <span class="ee-proof-pill"><?= htmlspecialchars((string) ($lang['sys.welcome_proof_open_source'] ?? 'Open source'), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="ee-proof-pill"><?= htmlspecialchars((string) ($lang['sys.welcome_proof_structured_content'] ?? 'Structured content model'), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="ee-proof-pill"><?= htmlspecialchars((string) ($lang['sys.welcome_proof_wordpress_import'] ?? 'WordPress migration'), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="ee-proof-pill"><?= htmlspecialchars((string) ($lang['sys.welcome_proof_operations'] ?? 'Queues, cron, backups'), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <div class="d-flex flex-wrap gap-2 mt-4">
                                <a class="btn btn-primary btn-lg" href="https://github.com/evgeniyefimchenko/EE_FrameWork" target="_blank" rel="noopener noreferrer">
                                    <i class="fa-brands fa-github me-2"></i><?= htmlspecialchars((string) ($lang['sys.github_repository'] ?? 'GitHub repository'), ENT_QUOTES, 'UTF-8') ?>
                                </a>
                                <a class="btn btn-outline-dark btn-lg" href="/about">
                                    <i class="fa-solid fa-compass me-2"></i><?= htmlspecialchars((string) ($lang['sys.about_project'] ?? 'About project'), ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="ee-welcome-summary">
                                <h2><?= htmlspecialchars((string) ($lang['sys.welcome_summary_title'] ?? 'What is inside'), ENT_QUOTES, 'UTF-8') ?></h2>
                                <p><?= htmlspecialchars((string) ($lang['sys.welcome_summary_text'] ?? 'Entity model, categories, pages, custom properties, imports, background agents, backups, and operational tooling for evolving projects.'), ENT_QUOTES, 'UTF-8') ?></p>
                                <div class="ee-welcome-checklist">
                                    <div class="ee-welcome-check">
                                        <i class="fa-solid fa-diagram-project"></i>
                                        <div>
                                            <strong><?= htmlspecialchars((string) ($lang['sys.welcome_side_item_model_title'] ?? 'Model the domain'), ENT_QUOTES, 'UTF-8') ?></strong>
                                            <span><?= htmlspecialchars((string) ($lang['sys.welcome_side_item_model_text'] ?? 'Types, categories, pages, properties, translations, and route paths.'), ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                    </div>
                                    <div class="ee-welcome-check">
                                        <i class="fa-solid fa-arrow-right-arrow-left"></i>
                                        <div>
                                            <strong><?= htmlspecialchars((string) ($lang['sys.welcome_side_item_import_title'] ?? 'Migrate without chaos'), ENT_QUOTES, 'UTF-8') ?></strong>
                                            <span><?= htmlspecialchars((string) ($lang['sys.welcome_side_item_import_text'] ?? 'Import content, mirror media, and keep donor URL value under control.'), ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                    </div>
                                    <div class="ee-welcome-check">
                                        <i class="fa-solid fa-shield-heart"></i>
                                        <div>
                                            <strong><?= htmlspecialchars((string) ($lang['sys.welcome_side_item_ops_title'] ?? 'Operate calmly'), ENT_QUOTES, 'UTF-8') ?></strong>
                                            <span><?= htmlspecialchars((string) ($lang['sys.welcome_side_item_ops_text'] ?? 'Use health diagnostics, cron agents, queues, and backup plans in one platform.'), ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="ee-welcome-story mt-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <article class="ee-welcome-card ee-welcome-card-accent h-100">
                                <span class="ee-card-step">01</span>
                                <h3><?= htmlspecialchars((string) ($lang['sys.welcome_card_architecture_title'] ?? 'Content architecture'), ENT_QUOTES, 'UTF-8') ?></h3>
                                <p><?= htmlspecialchars((string) ($lang['sys.welcome_card_architecture_text'] ?? 'Flexible content model with categories, pages, translations, route paths, and structured property sets.'), ENT_QUOTES, 'UTF-8') ?></p>
                            </article>
                        </div>
                        <div class="col-md-4">
                            <article class="ee-welcome-card ee-welcome-card-accent h-100">
                                <span class="ee-card-step">02</span>
                                <h3><?= htmlspecialchars((string) ($lang['sys.welcome_card_import_title'] ?? 'Migration-ready imports'), ENT_QUOTES, 'UTF-8') ?></h3>
                                <p><?= htmlspecialchars((string) ($lang['sys.welcome_card_import_text'] ?? 'WordPress import pipeline, media mirroring, donor-link rewriting, and lifecycle fields for real project migrations.'), ENT_QUOTES, 'UTF-8') ?></p>
                            </article>
                        </div>
                        <div class="col-md-4">
                            <article class="ee-welcome-card ee-welcome-card-accent h-100">
                                <span class="ee-card-step">03</span>
                                <h3><?= htmlspecialchars((string) ($lang['sys.welcome_card_operations_title'] ?? 'Operations and automation'), ENT_QUOTES, 'UTF-8') ?></h3>
                                <p><?= htmlspecialchars((string) ($lang['sys.welcome_card_operations_text'] ?? 'Health monitoring, cron agents, backup plans, queues, and diagnostics for stable production maintenance.'), ENT_QUOTES, 'UTF-8') ?></p>
                            </article>
                        </div>
                    </div>
                </section>

                <section class="ee-welcome-why mt-4">
                    <div class="ee-welcome-section-head">
                        <span><?= htmlspecialchars((string) ($lang['sys.welcome_section_kicker'] ?? 'Why it feels product-grade'), ENT_QUOTES, 'UTF-8') ?></span>
                        <h2><?= htmlspecialchars((string) ($lang['sys.welcome_section_title'] ?? 'A cleaner path from data model to production'), ENT_QUOTES, 'UTF-8') ?></h2>
                        <p><?= htmlspecialchars((string) ($lang['sys.welcome_section_text'] ?? 'The platform is built not only to store content, but to survive imports, route changes, background jobs, and operational growth.'), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <div class="row g-3">
                        <div class="col-lg-3 col-sm-6">
                            <article class="ee-feature-tile h-100">
                                <i class="fa-solid fa-route"></i>
                                <h3><?= htmlspecialchars((string) ($lang['sys.welcome_tile_routes_title'] ?? 'Public routes'), ENT_QUOTES, 'UTF-8') ?></h3>
                                <p><?= htmlspecialchars((string) ($lang['sys.welcome_tile_routes_text'] ?? 'Slug and route_path contracts for public URLs, donor-path migration, and future SEO growth.'), ENT_QUOTES, 'UTF-8') ?></p>
                            </article>
                        </div>
                        <div class="col-lg-3 col-sm-6">
                            <article class="ee-feature-tile h-100">
                                <i class="fa-solid fa-layer-group"></i>
                                <h3><?= htmlspecialchars((string) ($lang['sys.welcome_tile_properties_title'] ?? 'Property model'), ENT_QUOTES, 'UTF-8') ?></h3>
                                <p><?= htmlspecialchars((string) ($lang['sys.welcome_tile_properties_text'] ?? 'Structured property sets with lifecycle fields for content, operations, and future product logic.'), ENT_QUOTES, 'UTF-8') ?></p>
                            </article>
                        </div>
                        <div class="col-lg-3 col-sm-6">
                            <article class="ee-feature-tile h-100">
                                <i class="fa-solid fa-server"></i>
                                <h3><?= htmlspecialchars((string) ($lang['sys.welcome_tile_ops_title'] ?? 'Operational core'), ENT_QUOTES, 'UTF-8') ?></h3>
                                <p><?= htmlspecialchars((string) ($lang['sys.welcome_tile_ops_text'] ?? 'Health screen, queues, rate guard, cron agents, and backup plans for routine production work.'), ENT_QUOTES, 'UTF-8') ?></p>
                            </article>
                        </div>
                        <div class="col-lg-3 col-sm-6">
                            <article class="ee-feature-tile h-100">
                                <i class="fa-solid fa-code-branch"></i>
                                <h3><?= htmlspecialchars((string) ($lang['sys.welcome_tile_extensibility_title'] ?? 'Extensibility'), ENT_QUOTES, 'UTF-8') ?></h3>
                                <p><?= htmlspecialchars((string) ($lang['sys.welcome_tile_extensibility_text'] ?? 'Custom docs, hooks, import maps, front controllers, and room to evolve the product without rebuilding the core.'), ENT_QUOTES, 'UTF-8') ?></p>
                            </article>
                        </div>
                    </div>
                </section>

                <section class="ee-welcome-cta mt-4">
                    <div class="row align-items-center g-3">
                        <div class="col-lg-8">
                            <span class="ee-welcome-cta-kicker"><?= htmlspecialchars((string) ($lang['sys.welcome_cta_kicker'] ?? 'Open source and ready for experiments'), ENT_QUOTES, 'UTF-8') ?></span>
                            <h2><?= htmlspecialchars((string) ($lang['sys.welcome_cta_title'] ?? 'Explore the codebase and current project baseline'), ENT_QUOTES, 'UTF-8') ?></h2>
                            <p><?= htmlspecialchars((string) ($lang['sys.welcome_cta_text'] ?? 'Use the repository to inspect the architecture and continue building your catalog, migration, and front-end layer.'), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <div class="col-lg-4">
                            <div class="ee-welcome-cta-actions">
                                <a class="btn btn-dark btn-lg" href="https://github.com/evgeniyefimchenko/EE_FrameWork" target="_blank" rel="noopener noreferrer">
                                    <i class="fa-brands fa-github me-2"></i><?= htmlspecialchars((string) ($lang['sys.github_repository'] ?? 'GitHub repository'), ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="ee-welcome-links mt-4">
                    <a href="/about"><?= htmlspecialchars((string) ($lang['sys.about_project'] ?? 'About project'), ENT_QUOTES, 'UTF-8') ?></a>
                    <span>·</span>
                    <a href="/contact"><?= htmlspecialchars((string) ($lang['sys.contacts'] ?? 'Contacts'), ENT_QUOTES, 'UTF-8') ?></a>
                </section>
            </div>
        </div>
    </div>
</div>
