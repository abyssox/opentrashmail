<?php
$url       = $url ?? '';
$email     = $email ?? '';
$emaildata = isset($emaildata) && is_array($emaildata) ? $emaildata : [];
?>
<?xml version="1.0" ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <atom:link href="<?= $url ?>/rss/<?= $email ?>" rel="self" type="application/rss+xml" />
        <title>RSS for <?= $email ?></title>
        <link><?= $url ?>/eml/<?= $email ?></link>
        <description>RSS Feed for email address <?= $email ?></description>
        <lastBuildDate><?= date(DateTime::RFC2822, time()) ?></lastBuildDate>
        <image>
            <title>RSS for <?= $email ?></title>
            <url>https://raw.githubusercontent.com/HaschekSolutions/opentrashmail/master/web/imgs/logo_300.png</url>
            <link>https://github.com/HaschekSolutions/opentrashmail</link>
        </image>
        <?php foreach ($emaildata as $id => $d):
            $data   = getEmail($email, $id) ?? [];
            $parsed = $data['parsed'] ?? [];

            $subject     = $parsed['subject']   ?? '';
            $body        = $parsed['body']      ?? '';
            $htmlbody    = $parsed['htmlbody']  ?? '';
            $attachments = isset($parsed['attachments']) && is_array($parsed['attachments']) ? $parsed['attachments'] : [];
            $rcpts       = isset($data['rcpts']) && is_array($data['rcpts']) ? $data['rcpts'] : [];
            $from        = $d['from'] ?? '';

            $time = substr($id, 0, -3);
            $att_text = [];
            if (is_array($attachments)) {
                foreach ($attachments as $filename) {
                    $filepath = ROOT . DS . '..' . DS . 'data' . DS . $email . DS . 'attachments' . DS . $filename;
                    $parts = explode('-', $filename);
                    $fid = $parts[0] ?? '';
                    $fn  = $parts[1] ?? $filename;
                    $att_url = $url . '/api/attachment/' . $email . '/' . $filename;
                    $att_text[] = "<a href='$att_url' target='_blank'>$fn</a>";
                }
            }
            ?>
            <item>
                <title><![CDATA[<?= $subject ?>]]></title>
                <pubDate><?= date(DateTime::RFC2822, (int)$time) ?></pubDate>
                <link><?= $url ?>/eml/<?= $email ?>/<?= $id ?></link>
                <description>
                    <![CDATA[
            Email from: <?= escape($from) ?><br/>
            Email to: <?= escape(implode(';', $rcpts)) ?><br/>
            <?= ((count($att_text) > 0) ? 'Attachments:<br/>' . array2ul($att_text) . '<br/>' : '') ?>
            <a href="<?= $url ?>/api/raw/<?= $email ?>/<?= $id ?>">View raw email</a> <br/>
            <br/>---------<br/><br/>
            <?= ($htmlbody ? $htmlbody : nl2br(htmlentities($body))) ?>
            ]]>
                </description>
            </item>
        <?php endforeach; ?>
    </channel>
</rss>
