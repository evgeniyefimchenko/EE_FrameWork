<?php if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
} ?>
    <div class="wrapper">
        <div class="sidebar" <?php if ($options['show_image_in_sidebar'] == 'yes'): ?>data-image = "<?= ENV_URL_SITE ?>/uploads/images/backgrounds/<?= $options['sidebar_img'] ?>"<?php endif; ?> data-color="<?= $options['color_filter'] ?>">
            <div class="sidebar-wrapper">
                <div class="logo">
                    <a href="<?= ENV_URL_SITE ?>" target="_blank" class="simple-text">
                        Перейти на сайт
                    </a>
                </div>
		<?= $main_menu ?>
            </div>
            <div class="sidebar-background" style="background-image: url(<?= ENV_URL_SITE ?>/uploads/images/backgrounds/<?= $options['sidebar_img'] ?>)"></div>
        </div>
        <div class="main-panel">
            <!-- Navbar -->
            <nav class="navbar navbar-expand-lg">
                <div class="container">
                    <div class="collapse navbar-collapse justify-content-end" id="navigation">
                        <ul class="navbar-nav ml-auto">
                            <li class="nav-item">
                                <a class="nav-link" href="<?= ENV_URL_SITE ?>/admin/user_edit/id/<?= $id ?>">
                                    <span class="no-icon">Ваш профиль</span>
                                </a>
                            </li>
                            <?= $message_user ?>							
                            <li class="nav-item">
                                <a class="nav-link" href="<?= ENV_URL_SITE ?>/exit_login">
                                    <span class="no-icon">Выход</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
            <!-- End Navbar -->
            <!-- Content -->
				<?= $body_view ?>
            <!-- End Content -->
            <!-- Footer -->
            <footer class="footer">                
                <div class="container">
					<nav>
                        <ul class="footer-menu">
                            <li>
                                <a href="/admin#">
                                    Домой
                                </a>
                            </li>
                            <li>
                                <a href="/admin#">
                                    Компания
                                </a>
                            </li>
                            <li>
                                <a href="/admin#">
                                    Портфолио
                                </a>
                            </li>
                            <li>
                                <a href="/admin#">
                                    Блог
                                </a>
                            </li>
                        </ul>
                        <p class="copyright">
                            ©&nbsp;<?php $get_year = date('Y', strtotime(ENV_SITE_CREATE)); if ($get_year == date('Y')) {echo date('Y');} else {echo $get_year . ' - ' . date('Y') . 'г.';} ?>
                            <a href="<?= ENV_URL_SITE ?>"><?= ENV_SITE_NAME ?></a>
                        </p>
                    </nav>
				</div>
            </footer>
			<!-- END Footer -->
        </div>
    </div>
<?=$menu_options?>