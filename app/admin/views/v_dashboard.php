<?= $top_bar ?>
<div id="layoutSidenav">
    <!-- General menu -->
<?= $main_menu ?>                  
    <!-- Content -->
    <div id="layoutSidenav_content">
        <?= $body_view ?>
        <!-- End Content -->
<?= $page_footer ?>
    </div><!-- Закрывает крайний div из $body_view -->
</div>