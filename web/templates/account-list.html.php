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
                    <a href="/address/<?= $email; ?>" hx-get="/api/address/<?= $email; ?>"
                       hx-push-url="/address/<?= $email; ?>" hx-target="#main">
                        <?= escape($email) ?>
                    </a>
                </td>
                <td><?= countEmailsOfAddress($email); ?></td>
                <td>
                    <div class="otm-row-actions">
                        <a href="/address/<?= $email; ?>" hx-get="/api/address/<?= $email; ?>"
                           hx-push-url="/address/<?= $email; ?>" hx-target="#main"
                           class="uk-button uk-button-primary uk-button-small">
                            Show
                        </a>
                        <a href="#" hx-get="/api/deleteaccount/<?= $email ?>"
                           hx-confirm="Are you sure to delete this account and all its emails?" hx-target="closest tr"
                           hx-swap="outerHTML swap:1s" class="uk-button uk-button-danger uk-button-small">
                            Delete
                        </a>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
