<?php
if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}
?>
<!-- Кабинет пользователя сайта -->
<div class="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><?= $get_user_context['new_user'] ? 'Add profile' : 'Edit profile' ?></h4>
                        <span <?= $user_role > 2 ? 'style="display:none;"' : '' ?> class="card-category" id="id_user" data-id="<?= $get_user_context['id'] ?>">id = <?php echo $get_user_context['new_user'] ? 'Not added' : $get_user_context['id'] ?></span>
                    </div>
					
                    <div class="card-body">
                        <!-- Tab links -->
                        <ul class="nav nav-tabs customtab" role="tablist">
                            <li class="nav-item"> <a class="nav-link active" data-toggle="tab" href="#profile" role="tab" aria-selected="true"><span class="hidden-sm-up"><i class=""></i></span> <span class="hidden-xs-down">Profile</span></a> </li>
                            <?php if (isset($get_user_context['id']) && $get_user_context['user_role'] > 2) { ?>
                                <li class="nav-item"> <a class="nav-link" data-toggle="tab" href="#conditions_rates" role="tab" aria-selected="false"><span class="hidden-sm-up"><i class=""></i></span> <span class="hidden-xs-down">Conditions & Rates</span></a> </li>
                                <li class="nav-item"> <a class="nav-link" data-toggle="tab" href="#security" role="tab" aria-selected="false"><span class="hidden-sm-up"><i class=""></i></span> <span class="hidden-xs-down">Security</span></a> </li>
                                <li class="nav-item"> <a class="nav-link" data-toggle="tab" href="#notification_center" role="tab" aria-selected="false"><span class="hidden-sm-up"><i class=""></i></span> <span class="hidden-xs-down">Notification Center</span></a> </li>
                                <li class="nav-item"> <a class="nav-link" data-toggle="tab" href="#login_stats" role="tab" aria-selected="false"><span class="hidden-sm-up"><i class=""></i></span> <span class="hidden-xs-down">Login Statistics</span></a> </li>
                                <li class="nav-item"> <a class="nav-link" data-toggle="tab" href="#integration" role="tab" aria-selected="false"><span class="hidden-sm-up"><i class=""></i></span> <span class="hidden-xs-down">Integration</span></a> </li>
                                <li class="nav-item"> <a class="nav-link" data-toggle="tab" href="#my_banks" role="tab" aria-selected="false"><span class="hidden-sm-up"><i class=""></i></span> <span class="hidden-xs-down">My banks</span></a> </li>
                            <?php } ?>
                        </ul>
						<div class="tab-content">
							<div id="profile" class="tab-pane" role="tabpanel">
								<form id="edit_users">
									<div class="row">
										<div class="col-md-6 pr-1">
											<div class="form-group has-success">
												<label><?=$lang['sys.name']?></label>
												<input type="text" name="name" autocomplete="off" class="form-control" placeholder="<?=$lang['sys.name']?>" required data-validator="string" value="<?= $get_user_context['name'] ?>"/>
											</div>
										</div>
										<div class="col-md-6 pl-1">
											<div class="form-group">
												<label><?=$lang['sys.email']?></label>
												<input type="email" name="email" autocomplete="off" class="form-control" data-validator="email" placeholder="Почта пользователя" value="<?= $get_user_context['email'] ?>">
												<small class="text-muted"></small>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-6">
											<div class="form-group">
												<label><?=$lang['sys.phone']?></label>
												<input type="tel" name="phone" autocomplete="off" <?= $get_user_context['phone'] && $user_role > 2 ? 'disabled' : '' ?> id="phone" class="form-control" data-validator="phone" placeholder="<?=$lang['sys.phone']?>" value="<?= $get_user_context['phone'] ?>">
												<small class="text-muted"></small>
											</div>
										</div>							
										<div class="col-md-6" <?= $get_user_context['new_user'] == 1 || $get_user_context['user_role'] <= 2 ? 'style="display: none;"' : '' ?>>
											<div class="form-group">
												<label><?=$lang['sys.site']?></label>
												<input type="site" name="domain_names" <?= $domain_names && $user_role > 2 ? '' : '' ?> autocomplete="off" id="domain_names" class="form-control" placeholder="<?=$lang['sys.site']?>" value="<?= $domain_names ?>">
												<small class="text-muted"></small>
											</div>
										</div>							
									</div>
									<div class="row" <?= $get_user_context['new_user'] == 1 || $get_user_context['user_role'] <= 2 ? 'style="display: none;"' : '' ?>>
										<div class="col-md-4">
											<div class="form-group">
												<label>Result URL</label>
												<input name="result_url" autocomplete="off" <?= $get_user_context['result_url'] && $user_role > 2 ? '' : '' ?> id="result_url" class="form-control" placeholder="https://examlpe.com/result_url" value="<?= $get_user_context['result_url'] ?>">
												<small class="text-muted"></small>
											</div>
										</div>							
										<div class="col-md-4">
											<div class="form-group">
												<label>Success URL</label>
												<input name="success_url" autocomplete="off" <?= $get_user_context['success_url'] && $user_role > 2 ? '' : '' ?> id="success_url" class="form-control" placeholder="https://examlpe.com/success_url" value="<?= $get_user_context['success_url'] ?>">
												<small class="text-muted"></small>
											</div>
										</div>							
										<div class="col-md-4">
											<div class="form-group">
												<label>Fail URL</label>
												<input type="site" name="fail_url" <?= $get_user_context['fail_url'] && $user_role > 2 ? '' : '' ?> autocomplete="off" id="fail_url" class="form-control" placeholder="https://examlpe.com/fail_url" value="<?= $get_user_context['fail_url'] ?>">
												<small class="text-muted"></small>
											</div>
										</div>							
									</div>
									<?php if ($user_role <= 2): ?>
										<!-- Посмотреть статус и роль пользователя может администратор и модератор -->
										<div class="row">
											<div class="col-md-6 pr-1">
												<div class="form-group">
													<!-- Роль пользователя может сменить только администратор -->
													<label><?=$lang['sys.role']?></label>
													<?php if ($user_role == 1) { ?>
														<select <?= $get_user_context['id'] == 1 ? 'disabled' : '' ?> name="user_role" class="selectpicker form-control">
															<option value="<?= $get_user_context['user_role'] ?>"><?= $get_user_context['user_role_text'] ?></option>
															<?php foreach ($get_free_roles as $role) { ?>
																<option value="<?= $role['id'] ?>"><?= $role['name'] ?></option>
															<?php } ?>
														</select>
													<?php } else { ?>
														<input name="user_role" type="hidden" value="<?= $get_user_context['user_role'] ?>" />
														<input class="form-control" readonly="TRUE" value="<?= $get_user_context['user_role_text'] ? $get_user_context['user_role_text'] : "Пользователь" ?>" />
													<?php } ?>
												</div>
											</div>
											<div class="col-md-6 pl-1">
												<div class="form-group">
													<label><?=$lang['sys.status']?></label>
													<select <?= $get_user_context['id'] == 1 ? 'disabled' : '' ?> name="active" class="selectpicker form-control">
														<option value="<?= $get_user_context['active'] ?>"><?= $get_user_context['active_text'] ?></option>
														<? foreach ($free_active_status as $key => $val){?>
														<option value="<?= $key ?>"><?= $val ?></option>
														<?}?>
													</select>
												</div>
											</div>
										</div>
									<?php endif; ?>
									<div class="row">
										<div class="col-md-4">
											<div class="form-group">
												<label><?=$lang['sys.last_ip']?></label>
												<input type="text" class="form-control" placeholder="<?=$lang['sys.unknown']?>" disabled value="<?= $get_user_context['last_ip'] ?>">
											</div>
										</div>
										<div class="col-md-4">
											<div class="form-group">
												<label><?=$lang['sys.sign_up_text']?></label>
												<input type="text" class="form-control" disabled placeholder="<?=$lang['sys.unknown']?>" value="<?= $get_user_context['reg_date'] ?>">
											</div>
										</div>
										<div class="col-md-4 pl-1">
											<div class="form-group">
												<label><?=$lang['sys.activity']?></label>
												<input type="text" class="form-control" disabled placeholder="<?=$lang['sys.unknown']?>" value="<?= $get_user_context['last_activ'] ?>">
											</div>
										</div>
									</div>
									<?php echo $get_user_context['new_user'] ? '<input type="hidden" name="new" value="1"/>' : ''; ?>
									<input type="submit" id="submit" value="SAVE" class="btn btn-info btn-fill pull-right"/>
									<div class="clearfix"></div>
								</form>
							</div>
							<!-- Conditions & Rates -->
							<div id="conditions_rates" class="tab-pane" role="tabpanel">
								<h3>Conditions &#38; Rates</h3>
								<hr/>
								<?= $user_role <= 2 ? '<form action="/admin/edit_conditions_rates" method="post">' : '' ?>
								<input type="hidden" name="user_id" value="<?= $get_user_context['id'] ?>">
								<h4>Fees</h4>
								<div class="row">
									<div class="col-lg-3 col-xl-2 col-sm-3 col-md-2">
										<div class="form-group has-success">
											<label>Processing rate</label>
											<div class="input-group mb-3">
												<input <?= $user_role > 2 ? 'disabled' : '' ?> type="number" min="0" max="100" name="processing_rate" autocomplete="off" class="form-control"
																							   data-validator="digital" required placeholder="" value="<?= $Conditions_Rates['processing_rate'] ?>">
												<div class="input-group-append">
													<span class="input-group-text"><i class="fa fa-percent" aria-hidden="true"></i></span>
												</div>                                            
											</div>
										</div>
									</div>							
								</div>
								<h4>Chargeback</h4>
								<div class="row">
									<div class="col-lg-3 col-xl-2 col-sm-3 col-md-2">
										<div class="form-group has-success">
											<label>Monthly Chargeback Limit</label>
											<div class="input-group mb-3">
												<input <?= $user_role > 2 ? 'disabled' : '' ?> type="text" name="chargeback_limit_month" autocomplete="off" class="form-control"
																							   data-validator="digital" required placeholder="" value="<?= $Conditions_Rates['chargeback_limit_month'] ?>">
												<div class="input-group-append">
													<span class="input-group-text"><i class="fa fa-<?= $merchant_accounts['default_currency_lowercase'] ?>" aria-hidden="true"></i></span>
												</div>
											</div>
										</div>
									</div>
									<div class="col-lg-3 col-xl-2 col-sm-3 col-md-2">
										<div class="form-group has-success">
											<label>Chargeback Penalty</label>
											<div class="input-group mb-3">
												<input <?= $user_role > 2 ? 'disabled' : '' ?> type="text" name="chargeback_penalty" autocomplete="off" class="form-control"
																							   placeholder="" required data-validator="digital" value="<?= $Conditions_Rates['chargeback_penalty'] ?>"/>
												<div class="input-group-append">
													<span class="input-group-text"><i class="fa fa-<?= $merchant_accounts['default_currency_lowercase'] ?>" aria-hidden="true"></i></span>
												</div>
											</div>
										</div>
									</div>								
								</div>
								<h4>Payouts</h4>
								<div class="row">
									<div class="col-lg-3 col-xl-2 col-sm-3 col-md-2">
										<div class="form-group has-success">
											<label>Minimum Payout</label>
											<div class="input-group mb-3">
												<input <?= $user_role > 2 ? 'disabled' : '' ?> type="text" name="min_payout_limit" autocomplete="off" class="form-control"
																							   placeholder="" required data-validator="digital" value="<?= $Conditions_Rates['min_payout_limit'] ?>"/>
												<div class="input-group-append">
													<span class="input-group-text"><i class="fa fa-<?= $merchant_accounts['default_currency_lowercase'] ?>" aria-hidden="true"></i></span>
												</div>
											</div>
										</div>
									</div>
									<div class="col-lg-3 col-xl-2 col-sm-3 col-md-2">
										<div class="form-group has-success">
											<label>Payout in Arrears</label>
											<div class="input-group mb-3">
												<input <?= $user_role > 2 ? 'disabled' : '' ?> type="text" name="payout_in_arrears" autocomplete="off" class="form-control"
																							   data-validator="digital" required placeholder="" value="<?= $Conditions_Rates['payout_in_arrears'] ?>">
												<div class="input-group-append">
													<span class="input-group-text">day</span>
												</div>
											</div>                                        
										</div>
									</div>
									<div class="col-lg-3 col-xl-2 col-sm-3 col-md-2">
										<div class="form-group has-success">
											<label>Payout Schedule</label>
											<div class="input-group mb-3">
												<div class="input-group">
													<?php if ($user_role <= 2) { ?>
														<div class="input-group-prepend">
															<button type="button" class="btn btn-outline-secondary dropdown-toggle" data-toggle="dropdown">
																<i class="fa fa-calendar-o" aria-hidden="true"></i>
															</button>
															<div class="dropdown-menu">
																<a class="dropdown-item" id="every_day" href="javascript:void(0)">every day</a>
																<a class="dropdown-item" id="once_a_week" href="javascript:void(0)">once a week</a>
																<a class="dropdown-item" id="two_times_a_week" href="javascript:void(0)">two times a week</a>
															</div>
														</div>
													<?php } ?>
													<input <?= $user_role > 2 ? 'disabled' : '' ?> type="text" name="payout_schedule" autocomplete="off" class="form-control"
																								   placeholder="" required data-validator="digital" value="<?= $Conditions_Rates['payout_schedule'] ?>"/>
													<div class="input-group-append">
														<span class="input-group-text">day</span>
													</div>
												</div>
											</div>                                        
										</div>
									</div>
								</div>
								<h4>Default currency</h4>
								<div class="row">
									<div class="col-md-3">
										<div class="form-group">
											<?php if ($user_role > 2) { ?>
												<input type="text" disabled class="form-control" value="<?= $merchant_accounts['default_currency'] ?>">
											<?php } else { ?>
												<label for="default_currency">Default currency:</label>
												<select class="form-control" name="default_currency" id="default_currency">
													<?php foreach (array('USD', 'EUR', 'GBP') as $currency) { ?>
														<option value="<?= $currency ?>" <?= $currency == $merchant_accounts['default_currency'] ? 'selected' : '' ?>><?= $currency ?></option>          
													<?php } ?>
												</select>
											<?php } ?>
										</div>
									</div>
								</div>                            
								<h4>Turnover</h4>							
								<div class="row">
									<div class="col-lg-3 col-xl-2 col-sm-3 col-md-2">
										<div class="form-group has-success">
											<label>Min Monthly Volume</label>
											<div class="input-group mb-3">
												<input <?= $user_role > 2 ? 'disabled' : '' ?> type="text" name="min_turnover_month" autocomplete="off" class="form-control"
																							   placeholder="" required data-validator="digital" value="<?= $Conditions_Rates['min_turnover_month'] ?>"/>
												<div class="input-group-append">
													<span class="input-group-text"><i class="fa fa-<?= $merchant_accounts['default_currency_lowercase'] ?>" aria-hidden="true"></i></span>
												</div>
											</div>
										</div>
									</div>
									<div class="col-lg-3 col-xl-2 col-sm-3 col-md-2">
										<div class="form-group has-success">
											<label>Max Monthly Volume</label>
											<div class="input-group mb-3">
												<input <?= $user_role > 2 ? 'disabled' : '' ?> type="text" name="max_turnover_month" autocomplete="off" class="form-control"
																							   placeholder="" required data-validator="digital" value="<?= $Conditions_Rates['max_turnover_month'] ?>"/>
												<div class="input-group-append">
													<span class="input-group-text"><i class="fa fa-<?= $merchant_accounts['default_currency_lowercase'] ?>" aria-hidden="true"></i></span>
												</div>
											</div>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-lg-4 col-xl-3 col-sm-5 col-md-4 col-12">
										<div class="form-group has-success">
											<label>Last Update</label>
											<input <?= $user_role > 2 ? 'disabled' : '' ?> type="text" class="text-center form-control" readonly value="<?= $Conditions_Rates['date_update'] ?>">
										</div>
									</div>							
								</div>
								<?= $user_role <= 2 ? '<input type="submit" value="SUBMIT" class="btn btn-info btn-fill pull-right mt-3"/>' : '' ?>						
								<?= $user_role <= 2 ? '</form>' : '' ?>
							</div>
							<!-- security -->
							<div id="security" class="tab-pane" role="tabpanel">
								<form action="/admin/edit_security" method="post">
									<input type="hidden" name="user_id" value="<?= $get_user_context['id'] ?>">
									<div class="row">
										<div class="col-md-12">
											<div class="form-group mt-1 text-center">
												<label><?=$lang['sms.confirm'] ?></label><br/>
												<input type="checkbox" name="subscribed" <?= $get_user_context['subscribed'] ? 'checked' : '' ?> />
											</div>
										</div>								
									</div>                                                        
									<div class="row">
										<div class="col-md-6">
											<div class="form-group">
												<label class="form-control-label" for="new_pass"><?=$lang['change.password.type'] ?></label>
												<input type="password" class="form-control" name="pwd" id="new_pass">
												<small class="text-muted"></small>
											</div>
										</div>
										<div class="col-md-6">
											<div class="form-group">
												<label class="form-control-label" for="new_pass_conf"><?=$lang['change.password.retype'] ?></label>
												<input type="password" class="form-control" name="new_pass_conf" id="new_pass_conf">
												<small class="text-muted"></small>
											</div>
										</div>
									</div>
									<input type="submit" value="SUBMIT" class="btn btn-info btn-fill pull-right mt-3"/>
								</form>
							</div>
							<!-- my_banks -->
							<div id="my_banks" class="tab-pane" role="tabpanel">
								<? if ($user_role <= 2) { ?>
								<form action="/admin/edit_banks" method="post">
									<input type="hidden" name="user_id" value="<?= $get_user_context['id'] ?>">
								<? } ?>
								
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<?if ($user_role <= 2) {?>
													<button id="add_bank" title="Добавить" type="button" class="btn btn-info d-none d-lg-block mt-3"
													data-original-title="Добавить" data-toggle="modal" data-target="#banksModal"><i class="fa fa-plus-circle"></i>Добавить</button>
												<?}?>										
												<label class="form-control-label mt-3" for="new_pass_conf"><?=$lang['site.my_connected_banks']?></label>
												
												<? if ($user_role > 2) {?>
													<style>
														.kill_bank{
															display: none;
														}
													</style>
												<? } ?>
												<div class="cart" id="banks_container">
													<? foreach($get_user_context['banks'] as $bank) {
														echo '<div class="parent_kill_bank"><input type="hidden" name="banks_id[]" value="' . $bank["bank_id"] . '" /><input type="text" disabled data-bankid="' . $bank["bank_id"] . '" class="form-group" value="' . $bank["bank_name"] . '"/>' . 
														'<a href="javascript:void(0);" class="ml-2 btn btn-danger kill_bank"><i data-toggle="tooltip" title="' . $lang['sys.delete'] . '" class="fas fa-window-close"></i></a><hr></div>';
													} ?>
												</div>
											</div>
										</div>
									</div>
								<? if ($user_role <= 2) { ?>
									<input type="submit" value="SUBMIT" class="btn btn-info btn-fill pull-left mt-3"/>								
								</form>									
								<? } ?>
							</div>							
							<!-- Integration -->
							<div id="integration" class="tab-pane" role="tabpanel">
							<? if ($get_user_context['phone'] && $domain_names) { ?>
								<div class="row mt-3">
									<div class="col-12">
										<div class="h4 form-group mt-1">
											<label><?=$lang['site.your_api_key']?>:</label><span class="ml-3"><?= $user_api_key ?></span>									                                      
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-12">									
										<div class="h4 form-group">
											<label><?=$lang['site.your_merchant_ID']?>:</label><span class="ml-3"><?= $get_user_context['id'] ?></span>											
										</div>
									</div>
								</div>
								<hr/>
								<div class="row">
									<div class="col-12">
										<div class="form-group mt-1 text-left">
											<label class="h3"><?=$lang['site.form_of_payment_on'] . ' ' . $lang['our.company'] ?>:</label><br/>
											<table class="h4" style="width: 100%;">
												<tr>
													<td width="10%">
														<?=$lang['site.example_get_request']?>:
													</td>
													<td><?
															$temp_order_id = rand(1, 1000);
														?>
														<a href="/api2/get_payment_form?api_key=<?= $user_api_key ?>&merchant_id=<?= $get_user_context['id'] ?>&order_id=<?=$temp_order_id?>&amount=10&currency=<?=$merchant_accounts['default_currency']?>
														&phone=<?=$get_user_context['phone']?>&country=test_country&state=test_state&city=test_city&address=test_address&email=<?=$get_user_context['email']?>&zip=518001" target="_blank">
														<?=ENV_URL_SITE?>/api2/get_payment_form?api_key=<?= $user_api_key ?>&#38;merchant_id=<?= $get_user_context['id'] ?>&#38;order_id=<?=$temp_order_id?>&#38;amount=10&#38;currency=<?=$merchant_accounts['default_currency']?>
														&#38;phone=<?=$get_user_context['phone']?>&#38;country=test_country&#38;state=test_state&#38;city=test_city&#38;address=test_address&#38;email=<?=$get_user_context['email']?>&#38;zip=518001</a>
													</td>
												</tr>
												<tr>
												<td>
												&nbsp;
												</td>
												<td>
												&nbsp;
												</td>												
												</tr>
												<tr>
													<td>
														<label class="mt-1" title="На него мы будем отправлять Вам ответ сервеера о результатах транзакций.">Result URL:</label>
													</td>
													<td>
														<span class="text-primary"><?=$get_user_context['result_url'] ? $get_user_context['result_url'] : $domain_names ?></span>														
													</td>
												</tr>
												<tr>
													<td>												
														<label class="mt-1" title="На него будет перенаправлен покупатель после успешного платежа.">Success URL:</label>
													</td>
													<td>												
														<span class="text-primary"><?=$get_user_context['success_url'] ? $get_user_context['success_url'] : $domain_names ?></span>
													</td>
												</tr>
												<tr>
													<td>													
														<label class="mt-1" title="На него будет перенаправлен покупатель после неуспешного платежа, отказа от оплаты.">Fail URL:</label>
													</td>
													<td>
														<span class="text-primary"><?=$get_user_context['fail_url'] ? $get_user_context['fail_url'] : $domain_names ?></span>
													</td>
												</tr>														
											</table>
										</div>
									</div>
								</div>
								<hr/>
								<div class="row hide">
									<div class="col-12 text-center">
										<label class="h3">Public key:</label><br/>
										<textarea disabled style="text-align: center; width: 55%; height: 350px;"><?= $public_key ?></textarea>
										<?php
										//echo $public_key;
										// Encrypt the data to $encrypted using the public key
										//openssl_public_encrypt('Строка замудрёная шифрованием', $encrypted, $pubKey);
										//$encrypted = base64_encode($encrypted);
										// Decrypt the data using the private key and store the results in $decrypted
										//openssl_private_decrypt(base64_decode($encrypted), $decrypted, $privKey);
										//echo '<input type="hidden" value="' . $encrypted . '">encrypted=' . $encrypted . '<br/>decrypted=' . $decrypted;
										?>
									</div>
								</div>
								<div class="row" style="display: none;">
									<div class="col-md-12">
										<div class="form-group mt-1 text-center">
											<label>HTML code of the payment form for the site:</label><br/>
											<textarea rows="22" readonly style="width: 100%;">
												<html>
												<head>
													<meta charset="utf-8" />
													<title>Payment form</title>
												</head>
												<body>
													<form action="Your_script.php" method="post">
														<div>
															<!-- order identifier (this is an example, do not store this information on the client)-->
															<input type="hidden" name="orderId" value="111" />
															<!-- order amount (this is an example, do not store this information on the client)-->
															<input type="hidden" name="amount" value="1000.00" />
														</div>

														<!-- payer information -->
														<div>
															<!-- card number -->
															<input type="text" name="pan" required value="" />
															<!-- cvv2/cvc2 -->
															<input type="text" name="cvv2" required value="" />
															<!-- card owner -->
															<input type="text" name="сardholer_name" required value="" />
															<!-- card validity period - year -->
															<input type="text" name="valid till_year" required value="" />
															<!-- card validity period - month -->
															<input type="text" name="valid till_month" required value="" />
															<!-- payer email -->
															<input type="text" name="email" required value="" />
															<!-- payer phone -->
															<input type="text" name="phone" required value="" />
															<!-- payer address -->
															<input type="text" name="address" required value="" />
															<input type="submit" name="submit" value="Make a payment" />
														</div>
													</form>
												</body>
												</html>
											</textarea><br/>
											<a href="javascript:void(0)" id="download_paymentPhpClass">Download PHP class</a><br/>
											<label>How to use example</label><br/>
											<textarea readonly rows="22" style="width: 100%;">
												/* your file should be called script.php or any other name, but you must change it in the form attribute <form action = "Your_script.php" method = "post"> */

												require_once('paymentClass.php');
												$payment = new Demontford_payment_class();

												$response = $payment->demontford_payment(); // JSON server response
											</textarea>
										</div>
									</div>								
								</div>
								<? }  else {?>
								<div class="row mt-3">
									<div class="col-12">
										<div class="h4 form-group mt-1">
											<label><?=$lang['site.specify_phone_number_domain']?></label>									                                      
										</div>
									</div>
								</div>								
								<? } ?>
							</div>
							<!-- Notifications -->
							<div id="notification_center" class="tab-pane" role="tabpanel">
								<table class="table table-bordered">
									<thead>
										<tr>
											<th>Notification</th>
											<th>Sent</th>
											<th>Read</th>
											<th>Sender</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($user_messages as $user_message) { ?>
											<tr>
												<td><?= $user_message['message_text'] ?></td>
												<td><?= date('d-m-Y', strtotime($user_message['date_create'])) ?></td>
												<td><?php
													$d = date('d-m-Y', strtotime($user_message['date_read']));
													echo $d > '01-01-1970' ? $d : 'not readed';
													?></td>
												<td><?= $user_message['autor_name'] ? $user_message['autor_name'] : 'system' ?></td>
											</tr>
										<?php } ?>
									</tbody>
								</table>                            
							</div>
							<!-- LOGIN STATISTICS-->
							<div id="login_stats" class="tab-pane" role="tabpanel">
								<div class="container-fluid">
									<div class="row">
										<div class="col-md-12">
											<div class="card strpied-tabled-with-hover">
												<div class="card-header">
													<h4 class="card-title text-center">Login Statistics</h4>
												</div>
												<div class="card-body table-responsive">
													<table class="table table-hover table-striped" id="login_statistics_table">
														<thead>
														<th>Login Date</th>
														<th>IP Address</th>
														<th>Browser</th>
														</thead>
														<tbody>	
															<?php foreach ($logs_data as $log) { ?>
																<tr>
																	<td><?= $log['login_date'] ?></td>
																	<td><?= $log['login_ip'] ?></td>
																	<td><?= $log['client_name'] ?></td>                                             
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
						</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
	
	<!-- banks Modal -->
<div class="modal fade" id="banksModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Выбраный банк будет добавлен к списку</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
		<div class="dropdown show">
		  <a class="btn btn-secondary dropdown-toggle" href="javascript:void(0);" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
			Выберите банк
		  </a>
		  <div class="dropdown-menu" aria-labelledby="dropdownMenuLink">
			<? foreach($all_banks as $one_bank) { ?>
				<a class="dropdown-item" data-selectbank="<?=$one_bank['bank_id']?>" href="javascript:void(0);"><?=$one_bank['bank_name']?></a>
			<? } ?>
		  </div>
		</div>        
      </div>
    </div>
  </div>
</div>
		
<?php echo isset($run_script) ? '<input type="hidden" value="' . $run_script . '" id="run_script"/>' : ''; ?>