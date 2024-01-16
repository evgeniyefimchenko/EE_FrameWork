<!-- Просмотр удалённого пользователя -->
<main>
    <form  id="edit_users_role" action="/admin/users_role_edit/id" method="POST">
        <div class="container-fluid px-4">
            <ol class="breadcrumb mb-4">
                <li class="breadcrumb-item active">
                    breadcrumb-item active
                </li>
            </ol>
            <div class="row">
                <div class="col-md-4">
                    <label for="name-input"><?=$lang['sys.name']?>:</label>
                    <span><?php var_export($deleted_user_data);?></span>
                </div>
            </div>
            <button type="submit" class="btn btn-primary my-3">Сохранить</button>
        </div>		
    </form>
</main>