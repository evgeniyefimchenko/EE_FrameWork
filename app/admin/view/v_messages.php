<?php if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
} ?>
<!-- Страница просмотра сообщений -->
<div class="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <?php if($count_messages) {?>
                    <div class="card-header">
                        <h4 class="card-title"><?=$lang['notifications']?></h4>
                    <?php if (!$moderation):?>
                        <div class="float-right">
                            <button id="read_all_message" type="button" data-return="/admin/messages" class="btn btn-success btn-sm">Read all</button>
                            <button id="kill_all_message" type="button" data-return="/admin/messages" class="btn btn-danger btn-sm">Delete all</button>
                        </div>
                    <?php endif;?>
                    </div>                    
                    <div class="card-body">                        				 
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Notifications</th>
                                        <th>Sent</th>
                                        <th>Read</th>
                                        <th>Sender</th>
                                        <?php if (!$moderation):?><th>Action</th><?php endif;?>
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
                                            <?= $d > '01-01-1970' ? '' : '<button id="set_readed" type="button" class="btn btn-success btn-sm" data-return="/admin/messages" data-id="'.$message['id'].'"><i data-toggle="tooltip" title="Прочитано" class="nc-icon nc-check-2"></i></button>'?>
                                            <button id="dell_message" type="button" class="btn btn-danger btn-sm" data-return="/admin/messages" data-id="<?=$message['id']?>"><i data-toggle="tooltip" title="Удалить" class="nc-icon nc-simple-remove"></i></button>
                                        </td>
                                        <?php endif;?>
                                    </tr>
                                    <?php }?>
                                </tbody>
                            </table>                        
                    </div><?php } else {?>
                    <div class="card-header">
                        <h4 class="card-title">You have no messages</h4>
                    </div>                    
                    <?php }?>
                </div>
            </div>
        </div>
    </div>
</div>