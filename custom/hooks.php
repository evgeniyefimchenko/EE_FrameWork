<?php

// Регистрируйте project-specific hooks только здесь.
// Пример:
// ee_add_custom_hook('afterUpdatePageData', [\custom\ProjectExtension::class, 'onAfterUpdatePageData'], 50);

ee_add_custom_hook('C_docsCatalog', [\custom\docs\ProjectDocsPlugin::class, 'filterCatalog'], 10);
ee_add_custom_hook('C_docsResolveDocument', [\custom\docs\ProjectDocsPlugin::class, 'filterDocument'], 10);
