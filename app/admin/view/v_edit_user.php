<?php
if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}
?>
<!-- Кабинет пользователя сайта -->
<div class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><?= $get_user_context['new_user'] ? 'Добавление профиля' : 'Редактирование профиля' ?></h4>
						<span class = "card-category" id="id_user" data-id="<?= $get_user_context['id'] ?>">id = <?php echo $get_user_context['new_user'] ? 'Не присвоен' : $get_user_context['id'] ?></span>
                    </div>
                    <div class="card-body">
                        <form id="edit_users">
                            <div class="row">
                                <div class="col-md-6 pr-1">
                                    <div class="form-group has-success">
                                        <label>ФИО</label>
                                        <input type="text" name="name" autocomplete="off" class="form-control" placeholder="Имя пользователя" required data-validator="string" value="<?= $get_user_context['name'] ?>"/>
                                    </div>
                                </div>
                                <div class="col-md-6 pl-1">
                                    <div class="form-group">
                                        <label>Email</label>
                                        <input type="email" name="email" autocomplete="off" class="form-control" data-validator="email" placeholder="Почта пользователя" value="<?= $get_user_context['email'] ?>">
                                        <small class="text-muted"></small>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Телефон</label>
                                        <input type="tel" name="phone" autocomplete="off" id="phone" class="form-control" data-validator="phone" placeholder="Телефон пользователя" value="<?= $get_user_context['phone'] ?>">
                                        <small class="text-muted"></small>
                                    </div>
                                </div>
                            </div>
                            <?php if ($user_role <= 2):?>
							<!-- Посмотреть статус и роль пользователя может администратор и модератор -->
                            <div class="row">
                                <div class="col-md-6 pr-1">
                                    <div class="form-group">
                                        <!-- Роль пользователя может сменить только администратор -->
										<label>Роль</label>
                                        <?php if ($user_role == 1) {?>
                                        <select name="user_role" class="selectpicker form-control">
                                            <option value="<?= $get_user_context['user_role'] ?>"><?= $get_user_context['user_role_text'] ?></option>
                                            <?php foreach ($get_free_roles as $role){?>
                                            <option value="<?= $role['id'] ?>"><?= $role['name'] ?></option>
                                            <?php } ?>
                                        </select>
                                        <?php } else {?>
                                        <input name="user_role" type="hidden" value="<?= $get_user_context['user_role']?>" />
                                        <input class="form-control" readonly="TRUE" value="<?= $get_user_context['user_role_text'] ? $get_user_context['user_role_text'] : "Пользователь" ?>" />
                                        <?php } ?>
                                    </div>
                                </div>
                                <div class="col-md-6 pl-1">
                                    <div class="form-group">
                                        <label>Статус</label>
                                        <select name="active" class="selectpicker form-control">
                                            <option value="<?= $get_user_context['active'] ?>"><?= $get_user_context['active_text'] ?></option>
                                            <? foreach ($free_active_status as $key => $val){?>
                                            <option value="<?= $key ?>"><?= $val ?></option>
                                            <?}?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <?php endif;?>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label>Последний ip</label>
                                        <input type="text" class="form-control" placeholder="Нет посещений" disabled value="<?= $get_user_context['last_ip'] ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 pr-1">
                                    <div class="form-group">
                                        <label>Рассылка</label>
                                        <select name="subscribed" class="selectpicker form-control">
                                            <option value="<?= $get_user_context['subscribed'] ?>"><?= $get_user_context['subscribed_text'] ?></option>
                                            <option value="<?= $get_user_context['subscribed'] == 1 ? 0 : 1 ?>"><?= $get_user_context['subscribed'] == 1 ? 'Отписать' : 'Подписать' ?></option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4 px-1">
                                    <div class="form-group">
                                        <label>Зарегистрирован</label>
                                        <input type="text" class="form-control" disabled placeholder="Нет регистрации" value="<?= $get_user_context['reg_date'] ?>">
                                    </div>
                                </div>
                                <div class="col-md-4 pl-1">
                                    <div class="form-group">
                                        <label>Поледняя активность</label>
                                        <input type="text" class="form-control" disabled placeholder="Нет посещений" value="<?= $get_user_context['last_activ'] ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label>Комментарий</label>
                                        <textarea rows="4" cols="80" name="comment" data-toggle="tooltip" title="Максимальная длинна 255 символов" class="form-control" placeholder="Оставьте тут свой дивиз!"><?= $get_user_context['comment'] ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-control-label" for="new_pass">Введите пароль для его смены</label>
                                        <input type="password" class="form-control" name="pwd" id="new_pass">
                                        <small class="text-muted"></small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-control-label" for="new_pass_conf">Повторите пароль</label>
                                        <input type="password" class="form-control" name="new_pass_conf" id="new_pass_conf">
                                        <small class="text-muted"></small>
                                    </div>
                                </div>
                            </div>
                            <?php echo $get_user_context['new_user'] ? '<input type="hidden" name="new" value="1"/>' : ''; ?>
                            <input type="submit" id="submit" value="Записать данные" class="btn btn-info btn-fill pull-right"/>
                            <div class="clearfix"></div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-user">
                    <div class="card-image">
                        <img src="<?= ENV_URL_SITE ?>/uploads/images/backgrounds/user-<?= rand(0, 7) ?>.jpg" alt="...">
                    </div>                    
                    <form class="form form-vertical" action="/admin/avatarupload" method="post" enctype="multipart/form-data">
                        <div class="card-body">
                            <div class="author">
                                <img class="avatar border-gray" src="<?= ENV_URL_SITE . $get_user_context['options']['user_img'] ?>" alt="<?= $get_user_context['name'] ?>">
                                <h5 class="title"><?= $get_user_context['name'] ?></h5>
                                <p class="description">
                                    <?= $get_user_context['email'] ?>
                                </p>
                            </div>
                            <p class="description text-center">
                                <?= $get_user_context['comment'] ?>
                            </p>
                        </div>
                    </form>
                    <hr>
                    <div class="button-container mr-auto ml-auto">
                        <a href="/admin/messages/<?=$get_user_context['id']?>" class="btn btn-simple btn-link btn-icon">
                            <?=$get_user_context['id'] == $user_id ? 'Мои сообщения' : "Сообщения пользователя"?>
                            <i class="fa fa-commenting-o"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>