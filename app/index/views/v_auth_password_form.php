<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5 col-md-7">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h4 text-center mb-3"><?= htmlspecialchars($auth_form_title) ?></h1>
                    <p class="text-muted text-center mb-4"><?= htmlspecialchars($auth_form_intro) ?></p>
                    <?php if (!empty($auth_form_error)) { ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($auth_form_error) ?></div>
                    <?php } ?>
                    <form method="post" action="<?= htmlspecialchars($auth_form_action) ?>">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($auth_form_token) ?>">
                        <div class="form-group mb-3">
                            <input class="form-control" type="password" name="password" placeholder="<?= htmlspecialchars($lang['sys.new_password']) ?>" required minlength="5" autocomplete="new-password">
                        </div>
                        <div class="form-group mb-4">
                            <input class="form-control" type="password" name="password_confirmation" placeholder="<?= htmlspecialchars($lang['sys.confirm_password']) ?>" required minlength="5" autocomplete="new-password">
                        </div>
                        <button class="btn btn-primary w-100" type="submit"><?= htmlspecialchars($auth_form_submit) ?></button>
                    </form>
                    <div class="text-center mt-3">
                        <a href="/show_login_form"><?= htmlspecialchars($lang['sys.log_in']) ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
