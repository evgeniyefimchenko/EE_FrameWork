<?php if (!defined('ENV_SITE')) exit(header("Location: http://" . $_SERVER['HTTP_HOST'], true, 301)); ?>
<main>
    <div class="container-fluid px-4">
        
        <h1 class="mt-4"><?=$lang['search.title']?></h1>
        
        <ol class="breadcrumb mb-4">
            <li class="breadcrumb-item"><a href="/admin"><?=$lang['sys.general']?></a></li>
            <li class="breadcrumb-item active"><?=$lang['search.breadcrumb_results']?></li>
        </ol>

        <?php if ($query): ?>
            <div class="alert alert-info" role="alert">
                <?=$lang['search.you_searched_for']?>: "<strong><?= htmlspecialchars($query) ?></strong>". <?=$lang['search.results_found']?>: <?= (int)$totalResults ?>.
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-table me-1"></i>
                <?=$lang['search.results_list_title']?>
            </div>
            <div class="card-body">
                <?php if (!empty($searchResults)): ?>
                    <div class="list-group">
                        <?php foreach ($searchResults as $result): ?>
                            <a href="<?= htmlspecialchars($result['url']) ?>" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h5 class="mb-1"><?= htmlspecialchars($result['title']) ?></h5>
                                    <small class="text-muted"><?=$lang['sys.type']?>: <?= htmlspecialchars($result['type']) ?></small>
                                </div>
                                <p class="mb-1 text-success"><?= htmlspecialchars($result['url']) ?></p>
                                <small><?=$lang['search.result_relevance']?>: <?= htmlspecialchars($result['relevance']) ?></small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($query): ?>
                    <p class="text-center"><?=$lang['search.no_results_found']?></p>
                <?php else: ?>
                    <p class="text-center"><?=$lang['search.enter_query']?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>