<!-- Просмотр удалённого пользователя -->
<main>
    <div class="container-fluid px-4">
        <ol class="breadcrumb mb-4">
            <li class="breadcrumb-item active">
                <?= htmlspecialchars($deleted_user_data['name']) ?>
            </li>
        </ol>
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header"><?= $lang['sys.user_info'] ?></div>
                    <div class="card-body">
                        <table class="table">
                            <tbody>
                                <tr>
                                    <th><?= $lang['sys.email'] ?>:</th>
                                    <td><?= htmlspecialchars($deleted_user_data['email']) ?></td>
                                </tr>
                                <tr>
                                    <th><?= $lang['sys.role'] ?>:</th>
                                    <td><?= htmlspecialchars($deleted_user_data['user_role_text']) ?></td>
                                </tr>
                                <tr>
                                    <th><?= $lang['sys.last_ip'] ?>:</th>
                                    <td><?= htmlspecialchars($deleted_user_data['last_ip']) ?></td>
                                </tr>
                                <tr>
                                    <th><?= $lang['sys.subscribed'] ?>:</th>
                                    <td><?= $deleted_user_data['subscribed'] == 1 ? $lang['sys.yes'] : $lang['sys.no'] ?></td>
                                </tr>
                                <tr>
                                    <th><?= $lang['sys.sign_up_text'] ?>:</th>
                                    <td><?= htmlspecialchars($deleted_user_data['created_at']) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><?= $lang['sys.messages'] ?></div>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($deleted_user_data['messages'] as $message): ?>
                            <li class="list-group-item">
                                <?= htmlspecialchars($message['message_text']) ?> - 
                                <small><?= htmlspecialchars($message['created_at']) ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>  
</main>
