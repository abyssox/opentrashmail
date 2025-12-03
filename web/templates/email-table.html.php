<!-- Nav -->
<nav aria-label="breadcrumb" class="uk-margin-small-bottom">
    <ul class="uk-breadcrumb">
        <li><span aria-current="page"><?= escape($email) ?></span></li>

        <?php if (!empty($expiresAt) && $expiresAt > time()): ?>
            <li><span id="address-expiry" data-expires-at="<?= (int)$expiresAt ?>"></span></li>
        <?php endif; ?>
    </ul>
</nav>

<!-- Toolbar -->
<div class="uk-margin-small-bottom uk-flex uk-flex-wrap uk-flex-left">
    <a href="#" id="copyemailbtn"
       class="uk-button uk-button-default uk-margin-small-right uk-margin-small-bottom otm-blue-hover"
       onclick="copyEmailToClipboard();return false;">
        <i class="fa-solid fa-clipboard"></i>
        <span class="uk-margin-small-left">Copy address to clipboard</span>
    </a>

    <a href="/rss/<?= $email ?>" target="_blank"
       class="uk-button uk-button-default uk-margin-small-right uk-margin-small-bottom otm-blue-hover">
        <i class="fa-solid fa-rss"></i>
        <span class="uk-margin-small-left">RSS Feed</span>
    </a>

    <a href="/json/<?= $email ?>" target="_blank"
       class="uk-button uk-button-default uk-margin-small-right uk-margin-small-bottom otm-blue-hover">
        <i class="fa-solid fa-file-code"></i>
        <span class="uk-margin-small-left">JSON API</span>
    </a>

    <a href="#"
       class="uk-button uk-button-default uk-margin-small-right uk-margin-small-bottom otm-blue-hover"
       onclick="openWebhookModal();return false;">
        <i class="fa-solid fa-plug"></i>
        <span class="uk-margin-small-left">Configure Webhook</span>
    </a>
</div>

<!-- Email Table  -->
<div class="uk-overflow-auto">
    <table class="uk-table uk-table-striped uk-table-hover uk-table-small uk-table-middle" role="grid">
        <thead>
        <tr>
            <th scope="col">#</th>
            <th scope="col">Date</th>
            <th scope="col">From</th>
            <?php if ($isadmin): ?>
                <th scope="col">To</th><?php endif; ?>
            <th scope="col">Subject</th>
            <th scope="col" class="uk-table-shrink">Action</th>
        </tr>
        </thead>
        <tbody id="email-rows">

        <?php if (count($emails) == 0): ?>
            <tr>
                <td colspan="<?= $isadmin ? 6 : 5 ?>" class="uk-text-center">
                    <span uk-spinner="ratio: 0.7" aria-label="Waiting for emails" role="status"></span>
                </td>
            </tr>
        <?php endif; ?>

        <?php $i = 0; ?>

        <?php foreach ($emails as $unixtime => $ed): ?>
            <tr>
                <th scope="row" class="otm-row-index"><?= ++$i ?></th>
                <td id="date-td-<?= $i ?>">
                    <script>
                        document.getElementById('date-td-<?= $i ?>').innerHTML =
                            moment.unix(parseInt(<?= $unixtime ?> / 1000)).format('<?= $dateformat ?>');
                    </script>
                </td>
                <td><?= escape($ed['from']) ?></td>
                <?php if ($isadmin): ?>
                    <td><?= $ed['email'] ?></td><?php endif; ?>
                <td><?= escape($ed['subject']) ?></td>
                <td>
                    <div class="otm-row-actions">
                        <?php if ($isadmin == true): ?>
                            <a href="/read/<?= $ed['email'] ?>/<?= $ed['id'] ?>"
                               hx-get="/api/read/<?= $ed['email'] ?>/<?= $ed['id'] ?>"
                               hx-target="#main"
                               class="uk-button uk-button-primary uk-button-small">
                                Open
                            </a>
                            <a href="#"
                               class="uk-button uk-button-danger uk-button-small otm-delete-btn"
                               data-delete-url="/api/delete/<?= $ed['email'] ?>/<?= $ed['id'] ?>">
                                Delete
                            </a>
                        <?php else: ?>
                            <a href="/read/<?= $email ?>/<?= $ed['id'] ?>"
                               hx-get="/api/read/<?= $email ?>/<?= $ed['id'] ?>"
                               hx-target="#main"
                               class="uk-button uk-button-primary uk-button-small">
                                Open
                            </a>
                            <a href="#"
                               class="uk-button uk-button-danger uk-button-small otm-delete-btn"
                               data-delete-url="/api/delete/<?= $email ?>/<?= $ed['id'] ?>">
                                Delete
                            </a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Delete confirmation modal -->
<div id="deleteConfirmModal" uk-modal>
    <div class="uk-modal-dialog uk-modal-body">

        <button class="uk-modal-close-default" type="button" uk-close></button>

        <h3 class="uk-modal-title">Delete email</h3>
        <p>Are you sure you want to delete this email?</p>

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

<!-- Email auto check -->
<div id="email-poller" hx-get="/api/address/<?= escape($email) ?>" hx-trigger="load, every 15s"
     hx-select="tbody#email-rows > tr" hx-target="#email-rows" hx-swap="innerHTML">
</div>

<!-- Webhook Configuration Modal -->
<div id="webhookModal" uk-modal>
    <div class="uk-modal-dialog uk-modal-body">

        <button class="uk-modal-close-default" type="button" uk-close></button>

        <h3 class="uk-modal-title">Webhook Configuration for <?= escape($email) ?></h3>

        <form id="webhookForm" class="uk-form-stacked uk-margin-top">

            <div class="uk-margin">
                <label class="uk-form-label">
                    <input class="uk-checkbox" type="checkbox" id="webhookEnabled" name="enabled"/>
                    <span class="uk-margin-small-left">Enable webhook for this email address</span>
                </label>
            </div>

            <div class="uk-margin">
                <label for="webhookUrl" class="uk-form-label">
                    Webhook URL
                </label>
                <div class="uk-form-controls">
                    <input type="url"
                           class="uk-input"
                           id="webhookUrl"
                           name="webhook_url"
                           placeholder="https://api.example.com/webhook"/>
                </div>
            </div>

            <div class="uk-margin">
                <label for="payloadTemplate" class="uk-form-label">
                    JSON Payload Template
                </label>
                <div class="uk-form-controls">
                    <textarea id="payloadTemplate"
                              name="payload_template"
                              rows="10"
                              class="uk-textarea"
                              placeholder='{"email": "{{to}}", "from": "{{from}}", "subject": "{{subject}}", "body": "{{body}}"}'>{
  "email": "{{to}}",
  "from": "{{from}}",
  "subject": "{{subject}}",
  "body": "{{body}}",
  "attachments": {{attachments}}
}</textarea>
                </div>
                <div class="uk-text-meta uk-margin-small-top">
                    Available placeholders: {{to}}, {{from}}, {{subject}}, {{body}}, {{htmlbody}}, {{sender_ip}},
                    {{attachments}}
                </div>
            </div>

            <ul uk-accordion class="uk-margin">
                <li>
                    <a class="uk-accordion-title" href="#">Advanced Settings</a>
                    <div class="uk-accordion-content">

                        <div class="uk-margin">
                            <label for="maxAttempts" class="uk-form-label">
                                Max Retry Attempts
                            </label>
                            <div class="uk-form-controls">
                                <input type="number" class="uk-input" id="maxAttempts" name="max_attempts" min="1"
                                       max="10" value="3"/>
                            </div>
                        </div>

                        <div class="uk-margin">
                            <label for="backoffMultiplier" class="uk-form-label">
                                Backoff Multiplier
                            </label>
                            <div class="uk-form-controls">
                                <input type="number" class="uk-input" id="backoffMultiplier" name="backoff_multiplier"
                                       min="1" max="5" step="0.5" value="2"/>
                            </div>
                        </div>

                        <div class="uk-margin">
                            <label for="secretKey" class="uk-form-label">
                                Secret Key (for HMAC signing)
                            </label>
                            <div class="uk-form-controls">
                                <input type="text" class="uk-input" id="secretKey" name="secret_key"
                                       placeholder="Optional secret key for payload signing"/>
                            </div>
                            <div class="uk-text-meta uk-margin-small-top">
                                If provided, webhook requests will include
                                <code>X-Webhook-Signature</code> header with HMAC-SHA256 signature.
                            </div>
                        </div>

                    </div>
                </li>
            </ul>
        </form>

        <div class="uk-margin-top uk-text-right">
            <button class="uk-button uk-button-default uk-modal-close" type="button">
                Cancel
            </button>
            <button class="uk-button uk-button-primary" type="button" onclick="saveWebhookConfig()">
                Save Configuration
            </button>
        </div>
    </div>
</div>

<?php if (isset($expiresAt) && is_int($expiresAt) && $expiresAt > time()): ?>
    <script>
        (function () {
            const el = document.getElementById('address-expiry');
            if (!el) return;

            const expiresAtSeconds = parseInt(el.dataset.expiresAt, 10);
            if (!expiresAtSeconds || Number.isNaN(expiresAtSeconds)) return;

            const expiresAtMs = expiresAtSeconds * 1000;

            function formatRemaining(ms) {
                const totalSeconds = Math.max(0, Math.floor(ms / 1000));
                const minutes = Math.floor(totalSeconds / 60);
                const seconds = totalSeconds % 60;
                return minutes + ':' + String(seconds).padStart(2, '0');
            }

            function tick() {
                const now = Date.now();
                const remaining = expiresAtMs - now;

                if (remaining <= 0) {
                    el.textContent = 'expired';
                    el.classList.add('uk-text-danger');
                    clearInterval(timer);

                    return;
                }
                el.textContent = 'expires in ' + formatRemaining(remaining);
            }

            tick();
            const timer = setInterval(tick, 1000);
        })();
    </script>
<?php endif; ?>

<script>
    history.pushState({urlpath: "/address/<?= $email ?>"}, "", "/address/<?= $email ?>");

    function copyEmailToClipboard() {
        navigator.clipboard.writeText("<?= $email ?>");
        document.getElementById('copyemailbtn').innerHTML =
            '<i class="fa-solid fa-circle-check" style="color: green;"></i> Copied!';
    }

    // Delete Email Modal
    (function () {
        if (window.otmDeleteModalInitialized) return;
        window.otmDeleteModalInitialized = true;

        var modalEl   = document.getElementById('deleteConfirmModal');
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

            if (!row) {
                return;
            }

            var tbody = row.closest('tbody');
            if (tbody) {
                var dataRows = tbody.querySelectorAll('tr:not(.otm-spinner-row)');
                var isLastRow = dataRows.length === 1;

                if (isLastRow && !tbody.querySelector('.otm-spinner-row')) {
                    var spinnerRow = document.createElement('tr');
                    spinnerRow.className = 'otm-spinner-row';
                    spinnerRow.innerHTML =
                        '<td colspan="<?= $isadmin ? 6 : 5 ?>" class="uk-text-center">' +
                        '<span uk-spinner="ratio: 0.7" aria-label="Waiting for emails" role="status"></span>' +
                        '</td>';

                    tbody.appendChild(spinnerRow);

                    if (window.UIkit && typeof UIkit.update === 'function') {
                        UIkit.update(tbody);
                    }
                }
            }

            var previousId = row.id;
            var tempId = previousId || ('otm-delete-' + Date.now());
            if (!previousId) {
                row.id = tempId;
            }

            htmx.ajax('GET', url, {
                target: '#' + tempId,
                swap: 'outerHTML swap:1s'
            });
        });
    })();

    document.body.addEventListener('htmx:afterSwap', function (evt) {
        var target = evt.detail && evt.detail.target;
        if (!target) return;

        if (!(target.matches && target.matches('tr'))) {
            return;
        }

        var tbody = document.getElementById('email-rows');
        if (!tbody) return;

        var dataRows = tbody.querySelectorAll('tr:not(.otm-spinner-row)');
        var spinnerRow = tbody.querySelector('.otm-spinner-row');

        if (dataRows.length === 0 && !spinnerRow) {
            var tr = document.createElement('tr');
            tr.className = 'otm-spinner-row';
            tr.innerHTML =
                '<td colspan="<?= $isadmin ? 6 : 5 ?>" class="uk-text-center">' +
                '<span uk-spinner="ratio: 0.7" aria-label="Waiting for emails" role="status"></span>' +
                '</td>';

            tbody.appendChild(tr);

            if (window.UIkit && typeof UIkit.update === 'function') {
                UIkit.update(tbody);
            }
        }
    });

    if (typeof currentWebhookConfig === 'undefined') {
        var currentWebhookConfig = null;
    }

    async function openWebhookModal() {
        try {
            const response = await fetch('/api/webhook/get/<?= $email ?>');
            if (response.ok) {
                currentWebhookConfig = await response.json();

                document.getElementById('webhookEnabled').checked =
                    currentWebhookConfig.enabled || false;
                document.getElementById('webhookUrl').value =
                    currentWebhookConfig.webhook_url || '';
                document.getElementById('payloadTemplate').value =
                    currentWebhookConfig.payload_template || '{\n  "email": "{{to}}",\n  "from": "{{from}}",\n  "subject": "{{subject}}",\n  "body": "{{body}}",\n  "attachments": {{attachments}}\n}';
                document.getElementById('maxAttempts').value =
                    currentWebhookConfig.retry_config?.max_attempts || 3;
                document.getElementById('backoffMultiplier').value =
                    currentWebhookConfig.retry_config?.backoff_multiplier || 2;
                document.getElementById('secretKey').value =
                    currentWebhookConfig.secret_key || '';
            }
        } catch (error) {
            console.error('Error loading webhook config:', error);
        }

        UIkit.modal('#webhookModal').show();
    }

    async function saveWebhookConfig() {
        const formData = new FormData(document.getElementById('webhookForm'));
        const config = {
            email: '<?= $email ?>',
            enabled: formData.get('enabled') === 'on',
            webhook_url: formData.get('webhook_url'),
            payload_template: formData.get('payload_template'),
            max_attempts: parseInt(formData.get('max_attempts')),
            backoff_multiplier: parseFloat(formData.get('backoff_multiplier')),
            secret_key: formData.get('secret_key')
        };

        try {
            const response = await fetch('/api/webhook/save/<?= $email ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(config)
            });

            const result = await response.json();

            if (result.success) {
                alert('Webhook configuration saved successfully!');
                UIkit.modal('#webhookModal').hide();
            } else {
                alert('Error saving webhook configuration: ' + result.message);
            }
        } catch (error) {
            console.error('Error saving webhook config:', error);
            alert('Error saving webhook configuration');
        }
    }
</script>
