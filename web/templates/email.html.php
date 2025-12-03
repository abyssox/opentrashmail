<?php
$parsed = $emaildata['parsed'] ?? [];
$subject = $parsed['subject'] ?? '';
$htmlbody = $parsed['htmlbody'] ?? '';
$body = $parsed['body'] ?? '';
$rcpts = isset($emaildata['rcpts']) && is_array($emaildata['rcpts']) ? $emaildata['rcpts'] : [];
$attachments = isset($parsed['attachments']) && is_array($parsed['attachments']) ? $parsed['attachments'] : [];
?>

<nav aria-label="breadcrumb" class="uk-margin-small-bottom">
    <ul class="uk-breadcrumb">
        <li>
            <a href="/address/<?= $email ?>"
               hx-get="/api/address/<?= $email ?>"
               hx-target="#main">
                <?= escape($email) ?>
            </a>
        </li>
        <li><span><?= escape($subject) ?></span></li>
    </ul>
</nav>

<div class="uk-grid-small" uk-grid>
    <div class="uk-width-1-1@m">
        <article class="uk-card uk-card-default uk-card-body uk-margin-small-bottom">

            <header class="uk-margin-small-bottom">
                <p class="uk-margin-remove">
                    <span class="uk-text-bold">Subject:</span>
                    <?= escape($subject) ?>
                </p>

                <p class="uk-margin-remove">
                    <span class="uk-text-bold">Received:</span>
                    <span id="date2-<?= $mailid ?>"></span>
                </p>
                <script>
                    document.getElementById('date2-<?= $mailid ?>').innerHTML =
                        moment.unix(<?= $mailid ?> / 1000).format('<?= $dateformat; ?>');
                </script>

                <?php if (!empty($rcpts)) : ?>
                    <p class="uk-margin-small-top">
                        <span class="uk-text-bold">Recipients:</span>
                        <?php foreach ($rcpts as $to) : ?>
                            <span class="uk-label uk-margin-small-left uk-margin-small-top">
                    <?= escape($to) ?>
                </span>
                        <?php endforeach; ?>
                    </p>
                <?php endif; ?>
            </header>

            <div id="emailbody" class="uk-margin-top">
                <?php if (!empty($htmlbody)) : ?>
                    <button type="button" id="renderHtmlTrigger"
                            class="uk-button uk-button-secondary uk-button-small uk-margin-small-bottom">
                        Render email in HTML
                    </button>
                <?php endif; ?>

                <hr class="uk-margin-small">

                <div class="uk-overflow-auto">
                    <pre class="uk-text-small uk-text-break"><?= nl2br(escape($body)) ?></pre>
                </div>
            </div>

            <footer class="uk-margin-top">
                <h4 class="uk-heading-bullet uk-margin-small-bottom">Attachments</h4>

                <?php if (count($attachments) === 0) : ?>
                    <p class="uk-text-meta uk-margin-remove">No attachments</p>
                <?php else : ?>
                    <ul class="uk-list uk-list-divider uk-margin-small-top">
                        <?php foreach ($attachments as $attachment) : ?>
                            <li>
                                <a target="_blank"
                                   href="/api/attachment/<?= $email ?>/<?= $attachment ?>">
                                    <?= escape($attachment) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </footer>
        </article>
    </div>

    <div class="uk-width-1-1@m">
        <article class="uk-card uk-card-default uk-card-body uk-margin-small-top">
            <header class="uk-margin-small-bottom">
                <h3 class="uk-card-title uk-margin-remove">Raw email</h3>
            </header>

            <p class="uk-margin-small-bottom otm-blue-hover">
                <a href="/api/raw/<?= $email ?>/<?= $mailid ?>" target="_blank">
                    Open in new window
                </a>
            </p>

            <div class="uk-overflow-auto">
                <button
                        class="uk-button uk-button-default uk-button-small uk-margin-small-bottom otm-blue-hover"
                        hx-get="/api/raw/<?= $email ?>/<?= $mailid ?>"
                        hx-target="#raw-email-<?= $mailid ?>"
                        hx-swap="innerHTML">
                    Load Raw Email
                </button>

                <pre id="raw-email-<?= $mailid ?>"
                     class="uk-text-small uk-text-break uk-padding-small">
            </pre>
            </div>
        </article>
    </div>
    <div id="htmlWarningModal" uk-modal>
        <div class="uk-modal-dialog uk-modal-body">

            <button class="uk-modal-close-default" type="button" uk-close></button>

            <h3 class="uk-modal-title">Warning: Render HTML email</h3>
            <div class="uk-alert-danger" uk-alert>
                HTML emails may contain tracking pixels or scripts.
                Do you still want to render the HTML version?
            </div>

            <div class="uk-text-right">
                <button class="uk-button uk-button-default uk-modal-close" type="button">
                    Cancel
                </button>
                <button id="htmlRenderConfirmBtn" class="uk-button uk-button-primary" type="button">
                    Render HTML
                </button>
            </div>
        </div>
    </div>

</div>

<script>
    history.pushState(
        {email: "<?= $email ?>", id: "<?= $mailid ?>"},
        "",
        "/read/<?= $email ?>/<?= $mailid ?>"
    );

    (function () {
        var trigger = document.getElementById('renderHtmlTrigger');
        var modalEl = document.getElementById('htmlWarningModal');
        var confirmEl = document.getElementById('htmlRenderConfirmBtn');

        if (!trigger || !modalEl || !confirmEl || typeof UIkit === 'undefined' || typeof htmx === 'undefined') {
            return;
        }

        var modal = UIkit.modal(modalEl);

        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            modal.show();
        });

        confirmEl.addEventListener('click', function () {
            modal.hide();

            var targetEl = document.getElementById('emailbody');
            if (!targetEl) return;

            htmx.ajax('GET', '/api/raw-html/<?= $email ?>/<?= $mailid ?>', {
                target: targetEl,
                swap: 'innerHTML'
            });
        });
    })();
</script>
