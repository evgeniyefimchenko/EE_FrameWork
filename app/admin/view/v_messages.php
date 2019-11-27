<?php if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
} ?>
<!-- Страница просмотра сообщений -->
<div class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <?php if($count_message) {?>
                    <div class="card-header">
                        <h4 class="card-title">Ваши сообщения</h4>
                    <?php if (!$moderation):?>
                        <div class="float-right">
                            <button id="read_all_message" type="button" class="btn btn-success btn-sm">Отметить всё прочитанным</button>
                            <button id="kill_all_message" type="button" class="btn btn-danger btn-sm">Удалить все сообщения</button>
                        </div>
                    <?php endif;?>
                    </div>                    
                    <div class="card-body">                        				 
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Сообщение</th>
                                        <th>Отправлено</th>
                                        <th>Прочитано</th>
                                        <th>Кем отправлено</th>
                                        <?php if (!$moderation):?><th>Действие</th><?php endif;?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($messages as $message){?>
                                    <tr>
                                        <td><?= $message['message_text'] ?></td>
                                        <td><?= date('d-m-Y', strtotime($message['date_create'])) ?></td>
                                        <td><?php $d = date('d-m-Y', strtotime($message['date_read'])); echo $d > '01-01-1970' ? $d : 'не прочитано';?></td>
                                        <td><?= $message['autor_name'] ? $message['autor_name'] : 'системное' ?></td>
                                        <?php if (!$moderation):?>
                                        <td>
                                            <?=$d > '01-01-1970' ? '' : '<button id="set_readed" type="button" class="btn btn-success btn-sm" data-id="'.$message['id'].'"><i data-toggle="tooltip" title="Прочитано" class="nc-icon nc-check-2"></i></button>'?>
                                            <button id="dell_message" type="button" class="btn btn-danger btn-sm" data-id="<?=$message['id']?>"><i data-toggle="tooltip" title="Удалить" class="nc-icon nc-simple-remove"></i></button>
                                        </td>
                                        <?php endif;?>
                                    </tr>
                                    <?php }?>
                                </tbody>
                            </table>                        
                    </div><?php } else {?>
                    <div class="card-header">
                        <h4 class="card-title">У Вас нет сообщений</h4>
                    </div>                    
                    <?php }?>
                </div>
            </div>
        </div>
    </div>
</div>