<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<div class="list-group">
    <a href="/docs/readme" class="list-group-item list-group-item-action" data-doc="readme">
        <i class="fas fa-book-open"></i> <?= htmlspecialchars((string) ($lang['sys.documentation_map'] ?? 'Documentation map')) ?>
    </a>
    <a href="/docs/quick-start" class="list-group-item list-group-item-action" data-doc="quick-start">
        <i class="fas fa-bolt"></i> <?= htmlspecialchars((string) ($lang['sys.quick_start'] ?? 'Quick start')) ?>
    </a>
    <a href="/docs/architecture" class="list-group-item list-group-item-action" data-doc="architecture">
        <i class="fas fa-diagram-project"></i> <?= htmlspecialchars((string) ($lang['sys.architecture'] ?? 'Architecture')) ?>
    </a>
    <a href="/docs/routing" class="list-group-item list-group-item-action" data-doc="routing">
        <i class="fas fa-route"></i> <?= htmlspecialchars((string) ($lang['sys.routing'] ?? 'Routing')) ?>
    </a>
    <a href="/docs/models" class="list-group-item list-group-item-action" data-doc="models">
        <i class="fas fa-database"></i> <?= htmlspecialchars((string) ($lang['sys.models'] ?? 'Models')) ?>
    </a>
    <a href="/docs/content-model" class="list-group-item list-group-item-action" data-doc="content-model">
        <i class="fas fa-network-wired"></i> <?= htmlspecialchars((string) ($lang['sys.content_model'] ?? 'Content model')) ?>
    </a>
    <a href="/docs/views" class="list-group-item list-group-item-action" data-doc="views">
        <i class="fas fa-window-maximize"></i> <?= htmlspecialchars((string) ($lang['sys.views_layouts'] ?? 'Views and layouts')) ?>
    </a>
    <a href="/docs/hooks" class="list-group-item list-group-item-action" data-doc="hooks">
        <i class="fas fa-plug"></i> <?= htmlspecialchars((string) ($lang['sys.hooks'] ?? 'Hooks')) ?>
    </a>
    <a href="/docs/imports" class="list-group-item list-group-item-action" data-doc="imports">
        <i class="fas fa-file-import"></i> <?= htmlspecialchars((string) ($lang['sys.import_structure'] ?? 'Import structure')) ?>
    </a>
    <a href="/docs/auth" class="list-group-item list-group-item-action" data-doc="auth">
        <i class="fas fa-user-shield"></i> <?= htmlspecialchars((string) ($lang['sys.auth_access'] ?? 'Auth and access')) ?>
    </a>
    <a href="/docs/cache" class="list-group-item list-group-item-action" data-doc="cache">
        <i class="fas fa-gauge-high"></i> <?= htmlspecialchars((string) ($lang['sys.caching'] ?? 'Caching')) ?>
    </a>
    <a href="/docs/cron-agents" class="list-group-item list-group-item-action" data-doc="cron-agents">
        <i class="fas fa-clock-rotate-left"></i> <?= htmlspecialchars((string) ($lang['sys.cron_agents'] ?? 'Cron agents')) ?>
    </a>
    <a href="/docs/backup" class="list-group-item list-group-item-action" data-doc="backup">
        <i class="fas fa-box-archive"></i> <?= htmlspecialchars((string) ($lang['sys.backup'] ?? 'Backup')) ?>
    </a>
    <a href="/docs/debug" class="list-group-item list-group-item-action" data-doc="debug">
        <i class="fas fa-bug"></i> <?= htmlspecialchars((string) ($lang['sys.debug'] ?? 'Debug')) ?>
    </a>
    <a href="/docs/api-reference" class="list-group-item list-group-item-action" data-doc="api-reference">
        <i class="fas fa-code"></i> API Reference
    </a>
</div>
