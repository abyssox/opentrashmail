<?php
$emails = isset($emails) && is_array($emails) ? $emails : [];
?>

<div class="uk-margin-small-bottom">
    <a href="/json/listaccounts"
       target="_blank"
       class="uk-button uk-button-default otm-blue-hover">
        <i class="fa-solid fa-file-code"></i>
        <span class="uk-margin-small-left">JSON API</span>
    </a>
</div>

<div class="uk-overflow-auto">
    <table class="uk-table uk-table-divider uk-table-hover uk-table-small uk-table-middle">
        <thead>
        <tr>
            <th scope="col">Email Address</th>
            <th scope="col">Emails in Inbox</th>
            <th scope="col" class="uk-table-shrink">Action</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($emails)): ?>
            <tr>
                <td colspan="3" class="uk-text-center uk-text-meta">
                    No accounts found
                </td>
            </tr>
        <?php endif; ?>

        <?php foreach ($emails as $email): ?>
            <tr>
                <td>
                    <a href="/address/<?= $email; ?>"
                       hx-get="/api/address/<?= $email; ?>"
                       hx-push-url="/address/<?= $email; ?>"
                       hx-target="#main">
                        <?= escape($email) ?>
                    </a>
                </td>
                <td><?= countEmailsOfAddress($email); ?></td>
                <td>
                    <div class="otm-row-actions">
                        <a href="/address/<?= $email; ?>"
                           hx-get="/api/address/<?= $email; ?>"
                           hx-push-url="/address/<?= $email; ?>"
                           hx-target="#main"
                           class="uk-button uk-button-primary uk-button-small">
                            Show
                        </a>
                        <a href="#"
                           class="uk-button uk-button-danger uk-button-small otm-delete-btn"
                           data-delete-url="/api/deleteaccount/<?= $email ?>">
                            Delete
                        </a>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="deleteConfirmModal" uk-modal>
    <div class="uk-modal-dialog uk-modal-body">

        <button class="uk-modal-close-default" type="button" uk-close></button>

        <h3 class="uk-modal-title">Delete account</h3>
        <p>Are you sure you want to delete this account and all its emails?</p>

        <div class="uk-text-right">
            <button class="uk-button uk-button-default uk-modal-close" type="button">
                Cancel
            </button>
            <button id="deleteConfirmBtn" class="uk-button uk-button-danger" type="button">
                Delete
            </button>
        </div>
    </div>
</div>

<script>
    (function () {
        if (window.otmDeleteModalInitialized) return;
        window.otmDeleteModalInitialized = true;

        var modalEl    = document.getElementById('deleteConfirmModal');
        var confirmBtn = document.getElementById('deleteConfirmBtn');

        if (!modalEl || !confirmBtn || typeof UIkit === 'undefined') {
            console.warn('Delete confirmation modal not initialized');
            return;
        }

        var modal            = UIkit.modal(modalEl);
        var pendingDeleteBtn = null;

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.otm-delete-btn');
            if (!btn) return;

            e.preventDefault();
            pendingDeleteBtn = btn;
            modal.show();
        });

        confirmBtn.addEventListener('click', function () {
            if (!pendingDeleteBtn || typeof htmx === 'undefined') {
                modal.hide();
                return;
            }

            var url = pendingDeleteBtn.getAttribute('data-delete-url');
            if (!url) {
                modal.hide();
                return;
            }

            var row = pendingDeleteBtn.closest('tr');
            modal.hide();

            htmx.ajax('GET', url, {
                target: row,
                swap: 'outerHTML swap:1s'
            });
        });
    })();
</script>