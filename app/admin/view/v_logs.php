<?php
if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}
?>
<!-- Страница просмотра сообщений -->
<div class="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><?=$lang['activity.logs'] ?></h4>
                    </div>                    
                    <div class="card-body">
                        <!-- Tab links -->
                        <ul class="nav nav-tabs customtab" role="tablist">
							<li class="nav-item"> <a class="nav-link active" data-toggle="tab" href="#general_log" role="tab" aria-selected="true"><span class="hidden-sm-up"><i class=""></i></span> <span class="hidden-xs-down">General logs</span></a> </li>
							<li class="nav-item"> <a class="nav-link" data-toggle="tab" href="#logs_site_api" role="tab" aria-selected="false"><span class="hidden-sm-up"><i class=""></i></span> <span class="hidden-xs-down">API logs</span></a> </li>
							<li class="nav-item"> <a class="nav-link" data-toggle="tab" href="#logs_bank_api" role="tab" aria-selected="false"><span class="hidden-sm-up"><i class=""></i></span> <span class="hidden-xs-down">R/R logs</span></a> </li>
                        </ul>					
						<div class="tab-content mt-3">
							<!-- General log -->
							<div id="general_log" class="tab-pane active show" role="tabpanel">
								<div class="row">
									<div class="col-12 text-center">
										<h4>Last operations</h4>										
										<table id="general_log_table" class="table table-bordered m-b-0 toggle-arrow-tiny" data-toggle-column="first" data-filtering="true" data-paging="true" data-sorting="true" data-paging-size="15">
											<thead>
												<th data-filterable="false">ID</th>
												<th data-filterable="false">Date</th>
												<th>Status</th>
												<th>Details</th>
												<th>Initiator</th>
											</thead>
											<tbody>
												<?php foreach ($log_items as $item) { ?>
													<tr class="footable-filtering">
														<td data-filterable="false"><?= $item['id']; ?></td>
														<td data-filterable="false"><?= $item['date'] ?></td>
														<td><?= $item['flag'] ?></td>
														<td><?= $item['changes'] ?></td>
														<td><?= $item['who'] ?></td>
													</tr>
												<?php } ?>
											</tbody>
										</table>
										<div class="mt-3" style="float: left;">
											<button type="button" class="btn btn-info" data-page-size="10">10</button>
											<button type="button" class="btn btn-info" data-page-size="50">50</button>
											<button type="button" class="btn btn-info" data-page-size="100">100</button>
										</div>	
									</div>
								</div>            
							</div>
							<!-- API logs-->
							<div id="logs_site_api" class="tab-pane" role="tabpanel">
								<div class="row">
									<div class="col-12 text-center">
										<h4>Last operations</h4>										
										<table id="general_log_table" class="table table-bordered m-b-0 toggle-arrow-tiny" data-toggle-column="first" data-filtering="true" data-paging="true" data-sorting="true" data-paging-size="15">
											<thead>
												<th data-filterable="false">ID</th>
												<th data-filterable="false">Date</th>
												<th>API key</th>
												<th>Domain</th>
												<th>Request</th>
												<th>Request params</th>
												<th>transactID</th>
												<th>bank_id</th>
												<th>user_id</th>
											</thead>
											<tbody>
												<?php foreach ($get_API_logs as $item) { ?>
													<tr class="footable-filtering">
														<td data-filterable="false"><?= $item['id']; ?></td>
														<td data-filterable="false"><?= $item['date_create'] ?></td>
														<td><?= $item['api_key'] ?></td>
														<td><?= $item['domain_name'] ?></td>
														<td><?= $item['request'] ?></td>
														<td><?= $item['request_params'] ?></td>
														<td><?= $item['transactID'] ?></td>
														<td><?= $item['bank_id'] ?></td>
														<td><?= $item['user_id'] ?></td>
													</tr>
												<?php } ?>
											</tbody>
										</table>
										<div class="mt-3" style="float: left;">
											<button type="button" class="btn btn-info" data-page-size="10">10</button>
											<button type="button" class="btn btn-info" data-page-size="50">50</button>
											<button type="button" class="btn btn-info" data-page-size="100">100</button>
										</div>	
									</div>
								</div>            
							</div>
							<!-- R/R logs-->
							<div id="logs_bank_api" class="tab-pane" role="tabpanel">
								<div class="row">
									<div class="col-12 text-left">
										<h4>Last operations(billtechservices)</h4>										
											<?foreach ($text_logs as $item) { 
											echo $item;
											} ?>
									</div>
								</div>            
							</div>						
						</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
