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
                    <div class="card-header">
                        <h4 class="card-title">Журнал логирования действий</h4>
                    </div>                    
                    <div class="card-body">                        				 
						<table class="table table-bordered" id="logs_table">
							<thead>
								<tr>
									<th>ID</th>
									<th>Сообщение</th>
									<th>Кем инициированно</th>
									<th>Маркер</th>
									<th>Дата события</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($log_items as $log) { 								
								switch($log['flag']){
									case "info" : $flag_color = 'text-info'; break;
									case 'error' : $flag_color = 'text-danger'; break;
									case 'success' : $flag_color = 'text-primary'; break;
									default : $flag_color = 'text-muted'; break;
								}
								?>
								<tr>
									<td><?= $log['id'] ?></td>
									<td><?= $log['changes'] ?></td>
									<td><?= $log['who'] ?></td>
									<td class="<?= $flag_color ?>"><?= $log['flag'] ?></td>
									<td><?= date('d-m-Y', strtotime($log['date']))?></td>
								</tr>
								<?php } ?>
							</tbody>
						</table>                        
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
