<?php if (!defined('ENV_SITE')) exit(header("Location: http://" . $_SERVER['HTTP_HOST'], true, 301)); ?>
<!-- Всплывающая форма авторизации и регистрации -->
<div class="container">
    <div class="modal fade login" id="loginModal">    
        <div class="modal-dialog login animated modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">                     
                    <h4 class="modal-title text-center col-sm pl-5"></h4>                    
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="close_button"></button>
                </div>
                <div class="modal-body">
                    <div class="box-login">
                        <div class="content">
                            <div class="error"></div>
                            <div class="form loginBox">
                                <form id="log_form" method="post" accept-charset="UTF-8">
                                    <div class="form-group">
                                        <input id="log_email" class="form-control" type="text" placeholder="<?= $lang['sys.email'] ?>" data-validator="email" required="true" name="email" data-toggle="tooltip" title="<?= $lang['sys.your_email'] ?>">
                                    </div>
                                    <div class="form-group">
                                        <input id="log_password" class="form-control" type="password" placeholder="<?= $lang['sys.password'] ?>" name="password" data-validator="password" required="true" data-toggle="tooltip" title="<?= $lang['sys.your_password'] ?>">
                                    </div>
                                    <div class="form-group">
                                        <input class="btn btn-default btn-login" type="submit" value="<?= $lang['sys.log_in'] ?>">
                                    </div>                                
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="box-login">
                        <div class="content registerBox" style="display:none;">
                            <div class="form">
                                <form id="reg_form" method="post" accept-charset="UTF-8">
                                    <div class="form-group">
                                        <input id="reg_email" class="form-control" autocomplete="off" type="text" placeholder="<?= $lang['sys.email'] ?>" name="email" data-validator="email" required="true" value="" data-toggle="tooltip" title="<?= $lang['sys.your_email'] ?>">
                                    </div>
                                    <div class="form-group">
                                        <input id="reg_password" class="form-control" type="password" autocomplete="off" placeholder="<?= $lang['sys.password'] ?>" name="password" data-validator="password_strength" required="true" value="" data-toggle="tooltip" title="<?= $lang['sys.your_password'] ?>">
                                    </div>
                                    <div class="form-group">
                                        <input id="reg_password_confirmation" class="form-control" autocomplete="off" type="password" placeholder="<?= $lang['sys.confirm_password'] ?>" name="password_confirmation" data-validator="confirm_password" required="true" data-toggle="tooltip" title="<?= $lang['sys.confirm_password'] ?>">
                                    </div>
                                    <input class="btn btn-default btn-register" type="submit" value="<?= $lang['sys.sign_up'] ?>" name="commit">
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="box-login">
                        <div class="content PasswordRecoveryBox" style="display:none;">
                            <div class="form">
                                <form id="recovery_form" method="post" accept-charset="UTF-8">
                                    <div class="form-group">
                                        <input id="rec_email" class="form-control" autocomplete="off" type="text" placeholder="<?= $lang['sys.your_email'] ?>" name="email" data-validator="email" required="true" value="" data-toggle="tooltip" title="<?= $lang['sys.your_email'] ?>">
                                    </div>
                                    <input class="btn btn-default btn-recovery" type="submit" value="<?= $lang['sys.restore_password'] ?>" name="commit">
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="forgot login-footer">
                        <span>
                            <a href="javascript: openRegisterModal();"><?= $lang['sys.sign_up'] ?></a><br/>
                            <a href="javascript: openRecoveryModal();"><?= $lang['sys.restore_password'] ?></a>
                        </span>
                    </div>
                    <div class="forgot register-footer" style="display:none;">
                        <a href="javascript: openLoginModal();"><?= $lang['sys.log_in'] ?></a>
                        <br/>
                        <a href="javascript: openRecoveryModal();"><?= $lang['sys.restore_password'] ?></a>
                    </div>
                    <div class="forgot recovery-footer" style="display:none;">
                        <a href="javascript: openRegisterModal();"><?= $lang['sys.sign_up'] ?></a>
                        <br/>
                        <a href="javascript: openLoginModal();"><?= $lang['sys.log_in'] ?></a>
                    </div>
                </div>        
            </div>
        </div>
    </div>
    <form id="return_general" method="get" action="/" accept-charset="UTF-8" ></form>
</div>