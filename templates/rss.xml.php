<?php
declare(strict_types=1);

use OpenTrashmail\Services\Mailbox;
use OpenTrashmail\Utils\View;

$url = isset($url) && is_string($url) ? $url : '';
$email = isset($email) && is_string($email) ? $email : '';
$emailData = isset($emailData) && is_array($emailData) ? $emailData : [];

$baseUrl = rtrim($url, '/');

$escape = static fn (string $value): string => View::escape($value);
$escapeCdata = static fn (string $value): string => str_replace(']]>', ']]]]><![CDATA[>', $value);
$formatRfc2822 = static fn (int $timestamp): string => new \DateTimeImmutable()
        ->setTimestamp($timestamp)
        ->format(\DateTimeInterface::RFC2822);

?>
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <atom:link href="<?= $escape($baseUrl) ?>/rss/<?= $escape($email) ?>" rel="self" type="application/rss+xml"/>
        <title>RSS for <?= $escape($email) ?></title>
        <link><?= $escape($baseUrl) ?>/eml/<?= $escape($email) ?></link>
        <description>RSS Feed for email address <?= $escape($email) ?></description>
        <lastBuildDate><?= $formatRfc2822(time()) ?></lastBuildDate>
        <image>
            <title>RSS for <?= $escape($email) ?></title>
            <url>https://raw.githubusercontent.com/abyssox/opentrashmail/master/web/imgs/logo_300.png</url>
            <link>https://github.com/abyssox/opentrashmail</link>
        </image>

        <?php foreach ($emailData as $id => $d): ?>
            <?php
            $id = (string) $id;

            $data = Mailbox::getEmail($email, $id) ?? [];
            $parsed = is_array($data['parsed'] ?? null) ? $data['parsed'] : [];

            $subject = (string) ($parsed['subject'] ?? '');
            $body = (string) ($parsed['body'] ?? '');
            $htmlBody = (string) ($parsed['htmlbody'] ?? '');

            $attachments = is_array($parsed['attachments'] ?? null) ? $parsed['attachments'] : [];
            $rcpts = is_array($data['rcpts'] ?? null) ? $data['rcpts'] : [];
            $from = (string) ($d['from'] ?? '');

            $timestamp = 0;
            if ($id !== '' && ctype_digit($id) && strlen($id) > 3) {
                $timestamp = (int) substr($id, 0, -3);
            }

            $attLinks = [];
            foreach ($attachments as $filenameRaw) {
                $filename = (string) $filenameRaw;

                $parts = explode('-', $filename, 2);
                $displayName = $parts[1] ?? $filename;

                $attUrl = $baseUrl
                        . '/api/attachment/'
                        . rawurlencode($email)
                        . '/'
                        . rawurlencode($filename);

                $attLinks[] = sprintf(
                        '<a href="%s" target="_blank">%s</a>',
                        $escape($attUrl),
                        $escape($displayName)
                );
            }

            $recipientList = implode(';', array_map('strval', $rcpts));
            ?>

            <item>
                <title><![CDATA[<?= $escapeCdata($subject) ?>]]></title>
                <pubDate><?= $formatRfc2822($timestamp) ?></pubDate>
                <link><?= $escape($baseUrl) ?>/eml/<?= $escape($email) ?>/<?= $escape($id) ?></link>
                <description><![CDATA[
Email from: <?= $escapeCdata($escape($from)) ?><br/>
Email to: <?= $escapeCdata($escape($recipientList)) ?><br/>
<?= count($attLinks) > 0
                            ? 'Attachments:<br/>' . View::arrayToUnorderedList($attLinks) . '<br/>'
                            : '' ?>
<a href="<?= $escape($baseUrl) ?>/api/raw/<?= $escape($email) ?>/<?= $escape($id) ?>">View raw email</a><br/>
<br/>---------<br/><br/>
<?= $escapeCdata($htmlBody !== '' ? $htmlBody : nl2br($escape($body))) ?>

                ]]></description>
            </item>
        <?php endforeach; ?>
    </channel>
</rss>
