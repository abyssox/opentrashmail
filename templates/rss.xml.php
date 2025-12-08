<?php
declare(strict_types=1);

use OpenTrashmail\Services\Mailbox;
use OpenTrashmail\Utils\View;

$url       = isset($url) && is_string($url) ? $url : '';
$email     = isset($email) && is_string($email) ? $email : '';
$emaildata = isset($emaildata) && is_array($emaildata) ? $emaildata : [];
?>
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <atom:link href="<?= View::escape($url) ?>/rss/<?= View::escape($email) ?>"
                   rel="self"
                   type="application/rss+xml" />
        <title>RSS for <?= View::escape($email) ?></title>
        <link><?= View::escape($url) ?>/eml/<?= View::escape($email) ?></link>
        <description>RSS Feed for email address <?= View::escape($email) ?></description>
        <lastBuildDate><?= date(\DateTime::RFC2822, time()) ?></lastBuildDate>
        <image>
            <title>RSS for <?= View::escape($email) ?></title>
            <url>https://raw.githubusercontent.com/abyssox/opentrashmail/master/web/imgs/logo_300.png</url>
            <link>https://github.com/abyssox/opentrashmail</link>
        </image>

        <?php foreach ($emaildata as $id => $d):
            $data   = Mailbox::getEmail($email, (string)$id) ?? [];
            $parsed = $data['parsed'] ?? [];

            $subject     = $parsed['subject']   ?? '';
            $body        = $parsed['body']      ?? '';
            $htmlbody    = $parsed['htmlbody']  ?? '';
            $attachments = isset($parsed['attachments']) && is_array($parsed['attachments'])
                    ? $parsed['attachments']
                    : [];
            $rcpts       = isset($data['rcpts']) && is_array($data['rcpts'])
                    ? $data['rcpts']
                    : [];
            $from        = $d['from'] ?? '';

            $time = (int) substr((string)$id, 0, -3);

            $attLinks = [];
            foreach ($attachments as $filename) {
                $filename = (string)$filename;

                $parts = explode('-', $filename);
                $fn    = $parts[1] ?? $filename;

                $attUrl = $url . '/api/attachment/' . rawurlencode($email) . '/' . rawurlencode($filename);

                $attLinks[] = sprintf(
                        '<a href="%s" target="_blank">%s</a>',
                        View::escape($attUrl),
                        View::escape($fn)
                );
            }
            ?>
            <item>
                <title><![CDATA[<?= $subject ?>]]></title>
                <pubDate><?= date(\DateTime::RFC2822, $time) ?></pubDate>
                <link><?= View::escape($url) ?>/eml/<?= View::escape($email) ?>/<?= View::escape((string)$id) ?></link>
                <description><![CDATA[
Email from: <?= View::escape($from) ?><br/>
Email to: <?= View::escape(implode(';', $rcpts)) ?><br/>
<?= (count($attLinks) > 0)
                            ? 'Attachments:<br/>' . View::arrayToUnorderedList($attLinks) . '<br/>'
                            : '' ?>
<a href="<?= View::escape($url) ?>/api/raw/<?= View::escape($email) ?>/<?= View::escape((string)$id) ?>">View raw email</a><br/>
<br/>---------<br/><br/>
<?= ($htmlbody !== '' ? $htmlbody : nl2br(View::escape($body))) ?>

                ]]></description>
            </item>
        <?php endforeach; ?>
    </channel>
</rss>
