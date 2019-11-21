<?php if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
} ?>
<div class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-4">
                <div class="card ">
                    <div class="card-header ">
                        <h4 class="card-title">Подписки на рассылку</h4>
                    </div>
                    <div class="card-body ">
                        <div id="chartPreferences" class="ct-chart ct-perfect-fourth"></div>
                    </div>
                    <div class="card-footer ">
                        <div class="legend">
                            <i class="fa fa-circle text-info"></i> Подписано
                            <i class="fa fa-circle text-danger"></i> Не получили письма
                            <i class="fa fa-circle text-warning"></i> Отписались
                        </div>
                        <hr>
                        <div class="stats">
                            <i class="fa fa-clock-o"></i> Компания стартовала 2 дня назад
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Поведение пользователей</h4>
                        <p class="card-category">за 24 часа (администраторы и менеджеры исключены)</p>
                    </div>
                    <div class="card-body">
                        <div id="chartHours" class="ct-chart"></div>
                    </div>
                    <div class="card-footer">
                        <div class="legend">
                            <i class="fa fa-circle text-info"></i> Заходы
                            <i class="fa fa-circle text-danger"></i> Страниц просмотрено
                            <i class="fa fa-circle text-warning"></i> Отказы
                        </div>
                        <hr>
                        <div class="stats">
                            <i class="fa fa-history"></i> Обновлено 3 мин назад
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="card ">
                    <div class="card-header ">
                        <h4 class="card-title">Сравнительный оборот компании</h4>
                        <p class="card-category">Вычислен по формуле ...</p>
                    </div>
                    <div class="card-body ">
                        <div id="chartActivity" class="ct-chart"></div>
                    </div>
                    <div class="card-footer ">
                        <div class="legend">
                            <i class="fa fa-circle text-info"></i> Продукт 1
                            <i class="fa fa-circle text-danger"></i> Продукт 2
                        </div>
                        <hr>
                        <div class="stats">
                            <i class="fa fa-check"></i> Информация проверена
                        </div>
                    </div>
                </div>
            </div>
            <!-- ЗАДАЧИ -->
            <div class="col-md-6">
                <div class="card  card-tasks">
                    <div class="card-header ">
                        <h4 class="card-title">Задачи</h4>
                        <p class="card-category"><?= $name . ' (' . $user_role . ')' ?></p>
                    </div>
                    <div class="card-body ">
                        <div class="table-full-width">
                            <table class="table">
                                <tbody>
                                    <tr>
                                        <td>
                                            <div class="form-check">
                                                <label class="form-check-label">
                                                    <input class="form-check-input" type="checkbox" value="">
                                                    <span class="form-check-sign"></span>
                                                </label>
                                            </div>
                                        </td>
                                        <td>Срубить бабла заказчика!"</td>
                                        <td class="td-actions text-right">
                                            <button type="button" rel="tooltip" title="" class="btn btn-info btn-simple btn-link" data-original-title="Edit Task">
                                                <i class="fa fa-edit"></i>
                                            </button>
                                            <button type="button" rel="tooltip" title="" class="btn btn-danger btn-simple btn-link" data-original-title="Remove">
                                                <i class="fa fa-times"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="form-check">
                                                <label class="form-check-label">
                                                    <input class="form-check-input" type="checkbox" value="" checked="">
                                                    <span class="form-check-sign"></span>
                                                </label>
                                            </div>
                                        </td>
                                        <td>Письмо босу?</td>
                                        <td class="td-actions text-right">
                                            <button type="button" rel="tooltip" title="" class="btn btn-info btn-simple btn-link" data-original-title="Редактировать">
                                                <i class="fa fa-edit"></i>
                                            </button>
                                            <button type="button" rel="tooltip" title="" class="btn btn-danger btn-simple btn-link" data-original-title="Удалить">
                                                <i class="fa fa-times"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer ">
                        <hr>
                        <div class="stats">
                            <i class="now-ui-icons loader_refresh spin"></i> Обновлено 3 минуты агоу
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>