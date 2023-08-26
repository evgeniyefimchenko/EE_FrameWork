<?php
if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}
?>   
<!-- Таблица пользователей-->
<div class="page-wrapper">
    <div class="container-fluid">
		<!-- ============================================================== -->
		<!-- Bread crumb and right sidebar toggle -->
		<!-- ============================================================== -->
		<div class="row page-titles">
			<div class="col-md-5 align-self-center">
				<h4 class="text-themecolor"><?=$lang['registered.users']?></h4>
			</div>
			<div class="col-md-7 align-self-center text-right">
				<div class="d-flex justify-content-end align-items-center">
					<button id="add_user" data-toggle="tooltip" title="<?=$lang['sys.add']?>" type="button" class="btn btn-info d-none d-lg-block m-l-15"><i class="fa fa-plus-circle"></i><?=$lang['sys.add']?></button>
				</div>
			</div>			
		</div>
		<!-- ============================================================== -->
		<!-- End Bread crumb and right sidebar toggle -->
		<!-- ============================================================== -->		
        <div class="row">
            <div class="col-md-12">
                <div class="card strpied-tabled-with-hover">
                    <div class="card-body table-responsive">
                        <table class="table table-hover table-striped" id="users_table">
                            <thead>
                            <th>id</th>
                            <th><?=$lang['sys.name']?></th>
                            <th><?=$lang['sys.email']?></th>
                            <th><?=$lang['sys.role']?></th>
                            <th><?=$lang['sys.status']?></th>
                            <th><?=$lang['sys.sign_up_text']?></th>
                            <th><?=$lang['sys.activity']?></th>
                            <th><?=$lang['sys.action']?></th>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>	
                                    <tr>
                                        <td><?= $user['id'] ?></td>
                                        <td><?= $user['name'] ?></td>
                                        <td><?= $user['email'] ?></td>
                                        <td><?= $user['user_role_text'] ?></td>
                                        <td><?= $user['active_text'] ?></td>                                    
                                        <td><?= date('d-m-Y', strtotime($user['reg_date'])) ?></td>
                                        <td><?php
                                            $d = date('d-m-Y', strtotime($user['last_activ']));
                                            echo $d > '01-01-1970' ? $d : $lang['sys.unknown']
                                            ?>
										</td> 
                                        <td class="text-center">
                                            <?php if ($user_role <= $user['user_role']) { ?>
                                                <a href="/admin/user_edit/id/<?= $user['id'] ?>" class="btn btn-info">
													<i data-toggle="tooltip" title="<?=$lang['sys.edit']?>" class="fas fa-edit"></i>
												</a>
                                                <a href="javascript:void(0);" data-user_id="<?= $user['id'] ?>" class="btn btn-danger">
													<i data-toggle="tooltip" title="<?=$lang['sys.delete']?>" class="fas fa-window-close"></i>
												</a>										
                                            <?php } else { ?>
                                                <span class="text-danger"><?=$lang['sys.no_access']?></span>
                                            <?php } ?>
                                        </td>                                               
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>