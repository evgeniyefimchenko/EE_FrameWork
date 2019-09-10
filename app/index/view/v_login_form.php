<?php
if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}
?>

<!-- Всплывающие формы авторизации и регистрации -->
<div class="modal fade login" id="loginModal">    
    <div class="modal-dialog login animated">
        <div class="modal-content">
            <div class="modal-header">                     
                <h4 class="modal-title text-center col-sm"></h4>
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
            </div>
            <div class="modal-body">  
                <div class="box-login">
                    <div class="content">
                        <div class="error"></div>
                        <div class="form loginBox">
                            <form id="log_form" method="post" accept-charset="UTF-8">
                                <div class="form-group"><input id="log_email" class="form-control" type="text" placeholder="Почта" data-validator="email" required="true" name="email">
                                </div>
                                <div class="form-group"><input id="log_password" class="form-control" type="password" placeholder="Пароль" name="password" data-validator="password" required="true">
                                    <span class="stars"></span></div>
                                <div class="form-group"><input class="btn btn-default btn-login" type="submit" value="Войти">
                                </div>                                
                            </form>
                        </div>
                    </div>
                </div>
                <div class="box-login">
                    <div class="content registerBox" style="display:none;">
                        <div class="form">
                            <form id="reg_form" method="post" data-remote="true" accept-charset="UTF-8">
                                <div class="form-group"><input id="reg_email" class="form-control" autocomplete="off" type="text" placeholder="Почта" name="email" data-validator="email" required="true" value="">
                                </div>
                                <div class="form-group"><input id="reg_password" class="form-control" type="password" autocomplete="off" placeholder="Пароль" name="password" data-validator="password_strength" required="true" value="">
                                </div>
                                <div class="form-group"><input id="reg_password_confirmation" class="form-control" autocomplete="off" type="password" placeholder="Повторите пароль" name="password_confirmation" data-validator="confirm_password" required="true">
                                </div>
                                <input class="btn btn-default btn-register" type="submit" value="Создать аккаунт" name="commit">
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="forgot login-footer">
                    <span>У Вас нет регистрации
                        <a href="javascript: showRegisterForm();">создать аккаунт?</a>
                        <a href="/recovery"><span>Забыли пароль?</span></a>
                    </span>
                </div>
                <div class="forgot register-footer" style="display:none">
                    <span>У Вас есть аккаунт?</span>
                    <a href="javascript: showLoginForm();">Вход</a>
                </div>
            </div>        
        </div>
    </div>
</div>