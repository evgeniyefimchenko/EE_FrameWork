<?php
use classes\system\SysClass;
use classes\helpers\ClassNotifications;
use classes\system\Plugins;
?>
<!-- Редактирование пользователя сайта -->
<main>
    <form id="edit_users">
        <input type="hidden" name="fake" value="1" />
        <div class="container-fluid px-4">
            <h1 class="mt-4"><?= $get_user_context['new_user'] ? 'Добавить' : 'Редактировать' ?></h1>
            <ol class="breadcrumb mb-4">
                <li class="breadcrumb-item active">
                    <span <?= $user_role > 2 ? 'style="display:none;"' : '' ?> id="id_user" data-id="<?= $get_user_context['id'] ?>">id = <?php echo $get_user_context['new_user'] ? 'Не присвоен' : $get_user_context['id'] ?></span>
                </li>
            </ol>
            <div class="row">
                <div class="col">
                    <ul class="nav nav-tabs" id="myTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic-tab-pane" type="button" role="tab" aria-controls="basic-tab-pane" aria-selected="true"><?=$lang['sys.basics']?></button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security-tab-pane" type="button" role="tab" aria-controls="security-tab-pane" aria-selected="false"><?=$lang['sys.safety']?></button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="comment-tab" data-bs-toggle="tab" data-bs-target="#comment-tab-pane" type="button" role="tab" aria-controls="comment-tab-pane" aria-selected="false"><?=$lang['sys.comment']?></button>
                        </li>
                    </ul>
                    <div class="tab-content" id="myTabContent">
                        <!-- Основное содержимое -->
                        <div class="tab-pane fade show active mt-3" id="basic-tab-pane" role="tabpanel" aria-labelledby="basic-tab">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="name-input"><?=$lang['sys.name']?>:</label>
                                    <input type="text" id="name-input" name="name" class="form-control" placeholder="Введите имя..." value="<?= $get_user_context['name'] ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="email-input"><?=$lang['sys.email']?>:</label>
                                    <input type="email" id="email-input" name="email" class="form-control" placeholder="Введите почту..." value="<?= $get_user_context['email'] ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="phone-input"><?=$lang['sys.phone']?>:</label>
                                    <input type="tel" id="phone-input" name="phone" class="form-control" placeholder="Введите телефон..." value="<?= $get_user_context['phone'] ?>">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label><?= $lang['sys.status'] ?></label>
                                    <select <?= $get_user_context['id'] == 1 ? 'disabled' : '' ?> name="active" class="selectpicker form-control">
                                        <option value="<?= $get_user_context['active'] ?>"><?= $get_user_context['active_text'] ?></option>
                                        <? foreach ($free_active_status as $key => $val){?>
                                        <option value="<?= $key ?>"><?= $val ?></option>
                                        <?}?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <!-- Посмотреть статус и роль пользователя может администратор и модератор -->
                                    <!-- Роль пользователя может сменить только администратор -->
                                    <label><?= $lang['sys.role'] ?></label>
                                    <?php if ($user_role == 1) { ?>
                                        <select <?= $get_user_context['id'] == 1 || $get_user_context['user_role'] == 3 ? 'disabled' : '' ?> name="user_role" class="selectpicker form-control">
                                            <option value="<?= $get_user_context['user_role'] ?>"><?= $get_user_context['user_role_text'] ?></option>
                                            <?php foreach ($get_free_roles as $role) { ?>
                                                <option value="<?= $role['id'] ?>"><?= $role['name'] ?></option>
                                            <?php } ?>
                                        </select>
                                    <?php } else { ?>
                                        <input name="user_role" type="hidden" value="<?= $get_user_context['user_role'] ?>" />
                                        <input class="form-control" readonly="true" value="<?= $get_user_context['user_role_text'] ? $get_user_context['user_role_text'] : "Пользователь" ?>" />
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="registration-date-input">Дата регистрации:</label>
                                    <input type="text" disabled  class="form-control" value="<?= $reg_date ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="update-date-input">Дата обновления:</label>
                                    <input type="text" disabled class="form-control" value="<?= $up_date ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="update-date-input">Дата последней активности:</label>
                                    <input type="text" disabled class="form-control" value="<?= $last_activ ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" name="subscribed" type="checkbox" id="subscription-check" <?= $get_user_context['subscribed'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="subscription-check">
                                        Подписка на рассылку
                                    </label>
                                </div>
                            </div>
                        </div>
                        <!-- Содержимое для безопасности -->
                        <div class="tab-pane fade mt-3" id="security-tab-pane" role="tabpanel" aria-labelledby="security-tab">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="new_pass">Новый пароль:</label>
                                    <input type="password" id="new_pass" name="pwd" class="form-control" placeholder="Введите новый пароль..." autocomplete="new-password">
                                    <small></small>
                                </div>
                                <div class="col-md-6">
                                    <label for="new_pass_conf">Повторить пароль:</label>
                                    <input type="password" id="new_pass_conf" name="new_pass_conf" class="form-control" placeholder="Повторите новый пароль...">
                                    <small></small>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="two-factor-auth-check">
                                    <label class="form-check-label" for="two-factor-auth-check">
                                        Двухфакторная авторизация
                                    </label>
                                </div>
                            </div>
                        </div>
                        <!-- Содержимое для комментария -->
                        <div class="tab-pane fade mt-3 mb-3" id="comment-tab-pane" role="tabpanel" aria-labelledby="comment-tab">
                            <label><?=$lang['sys.comment']?>:</label>
                            <textarea class="form-control" name="comment" placeholder="Оставьте ваш комментарий..."><?= $get_user_context['comment'] ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Сохранить</button>
        </div>		
    </form>
</main>