<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-7">
            <div class="card shadow-sm">
                <div class="card-body p-4 p-lg-5">
                    <h1 class="h3 mb-3"><?= htmlspecialchars((string) $legal_form_title) ?></h1>
                    <p class="text-muted mb-4"><?= htmlspecialchars((string) $legal_form_intro) ?></p>

                    <?php if (!empty($legal_form_error)) { ?>
                        <div class="alert alert-danger"><?= htmlspecialchars((string) $legal_form_error) ?></div>
                    <?php } ?>

                    <form method="post" action="<?= htmlspecialchars((string) $legal_form_action, ENT_QUOTES) ?>">
                        <?php if (!empty($legal_form_return)) { ?>
                            <input type="hidden" name="return" value="<?= htmlspecialchars((string) $legal_form_return, ENT_QUOTES) ?>">
                        <?php } ?>
                        <?php if (!empty($legal_form_provider)) { ?>
                            <input type="hidden" name="provider" value="<?= htmlspecialchars((string) $legal_form_provider, ENT_QUOTES) ?>">
                        <?php } ?>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="privacy_policy_accepted" name="privacy_policy_accepted" value="1" <?= !empty($legal_form_privacy_policy_accepted) ? 'checked' : '' ?> required>
                            <label class="form-check-label" for="privacy_policy_accepted">
                                <?= htmlspecialchars((string) ($lang['sys.accept_privacy_policy'] ?? 'Я ознакомлен(а) и принимаю Политику в отношении обработки персональных данных')) ?>
                                <a href="/privacy_policy" target="_blank" rel="noopener"><?= htmlspecialchars((string) ($lang['sys.open_document'] ?? 'Открыть документ')) ?></a>
                            </label>
                        </div>

                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="personal_data_consent_accepted" name="personal_data_consent_accepted" value="1" <?= !empty($legal_form_personal_data_consent_accepted) ? 'checked' : '' ?> required>
                            <label class="form-check-label" for="personal_data_consent_accepted">
                                <?= htmlspecialchars((string) ($lang['sys.accept_personal_data_consent'] ?? 'Я даю согласие на обработку персональных данных')) ?>
                                <a href="/consent_personal_data" target="_blank" rel="noopener"><?= htmlspecialchars((string) ($lang['sys.open_document'] ?? 'Открыть документ')) ?></a>
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary"><?= htmlspecialchars((string) $legal_form_submit) ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
