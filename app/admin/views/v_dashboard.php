<?= $top_bar ?>
<div id="layoutSidenav">
    <!-- General menu -->
<?= $main_menu ?>                  
    <!-- Content -->
    <div id="layoutSidenav_content" class="pt-5">
        <?= $body_view ?>
        <!-- End Content -->
<?= $page_footer ?>
        <input type="hidden" id="ee_tour_storage">
    </div><!-- Закрывает крайний div из $body_view -->
</div>