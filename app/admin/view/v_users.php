<?php
if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}
?>   
<!-- Таблица пользователей-->
<div class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card strpied-tabled-with-hover">
                    <div class="card-header ">
                        <h4 class="card-title text-center">Зарегистрированные пользователи</h4>
                        <button type="button" id="add_user" data-toggle="tooltip" title="Добавить пользователя" class="btn btn-primary btn-sm float-right"><i class="fa fa-plus"></i></button>
                    </div>
                    <div class="card-body table-responsive">
                        <table class="table table-hover table-striped" id="users_table">
                            <thead>
                            <th>id</th>
                            <th>ФИО</th>
                            <th>Почта</th>
                            <th>Роль</th>
                            <th>Статус</th>
                            <th>Подписка</th>
                            <th>Зарегистрирован</th>
                            <th>Активность</th>
                            <th>Действие</th>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user):?>	
                                <tr>
                                    <td><?= $user['id'] ?></td>
                                    <td><?= $user['name'] ?></td>
                                    <td><?= $user['email'] ?></td>
                                    <td><?= $user['user_role_text']?></td>
                                    <td><?= $user['active_text'] ?></td>
                                    <td><?= $user['subscribed'] == 1 ? 'Подписан' : 'Нет подписки' ?></td>
                                    <td><?= date('d-m-Y', strtotime($user['reg_date'])) ?></td>
                                    <td><?php $d = date('d-m-Y', strtotime($user['last_activ'])); echo $d > '01-01-1970' ? $d : 'не было'?></td> 
                                    <td>
										<?php if ($user_role <= $user['user_role']) { ?>
											<a href="/admin/user_edit/id/<?= $user['id'] ?>" class="alert alert-info"><i data-toggle="tooltip" title="Редактировать" class="nc-icon nc-settings-90"></i></a>
										<?php } else { ?>
											<span class="text-danger">Нет доступа</span>
										<?php } ?>
                                    </td>                                               
                                </tr>
                                <?php endforeach;?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>