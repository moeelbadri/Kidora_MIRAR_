<?php
/* ============================================================
   مُرسِل البريد — عميل SMTP صغير بلا مكتبات خارجية.

   المشروع بلا Composer، وحاوية PHP بلا sendmail، فالبريد يخرج عبر SMTP
   مباشرة إلى خادم البريد الذاتي (aaPanel Postfix) باسم no-reply@anivia.site.

   الإعداد: متغيّرات البيئة KIDORA_SMTP_* أولاً، ثم صفوف settings (smtp_*)
   التي يحرّرها الأدمن من لوحة التحكم. الاتصال TLS ضمني على 465 (أو
   STARTTLS على 587)، والمصادقة AUTH PLAIN مع LOGIN احتياطاً.
   ============================================================ */

/** إعدادات البريد الفعلية: البيئة تغلب، ثم القاعدة. */
function mail_config(PDO $pdo): array {
    $rows = [];
    try {
        foreach ($pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'smtp_%'")->fetchAll() as $r) {
            $rows[$r['setting_key']] = (string)$r['setting_value'];
        }
    } catch (Throwable $e) { /* جدول الإعدادات موجود دائماً؛ الاحتياط فقط */ }

    $pick = function (string $env, string $key, string $default = '') use ($rows): string {
        $v = getenv($env);
        if ($v !== false && trim($v) !== '') return trim($v);
        return trim($rows[$key] ?? '') !== '' ? trim($rows[$key]) : $default;
    };

    return [
        'host'      => $pick('KIDORA_SMTP_HOST', 'smtp_host'),
        'port'      => (int)$pick('KIDORA_SMTP_PORT', 'smtp_port', '465'),
        'user'      => $pick('KIDORA_SMTP_USER', 'smtp_user'),
        'pass'      => $pick('KIDORA_SMTP_PASS', 'smtp_pass'),
        'from'      => $pick('KIDORA_SMTP_FROM', 'smtp_from'),
        'from_name' => $pick('KIDORA_SMTP_FROM_NAME', 'smtp_from_name', 'Kidora'),
        // اسم الشهادة إن كان الخادم يُخاطَب بعنوان IP (شهادة Postfix هنا باسم آخر)
        'tls_name'  => $pick('KIDORA_SMTP_TLS_NAME', 'smtp_tls_name'),
        // هل جاء الإعداد من البيئة؟ حتى تُظهر لوحة التحكم أن الحقول للقراءة فقط
        'from_env'  => getenv('KIDORA_SMTP_HOST') !== false && trim((string)getenv('KIDORA_SMTP_HOST')) !== '',
    ];
}

/** هل البريد مُعدّ بما يكفي للإرسال؟ */
function mail_is_configured(PDO $pdo): bool {
    $c = mail_config($pdo);
    return $c['host'] !== '' && $c['user'] !== '' && $c['pass'] !== '' && $c['from'] !== '';
}

/**
 * يرسل رسالة واحدة. يُعيد null عند النجاح، أو نص الخطأ (رد SMTP أو سبب الاتصال)
 * عند الفشل — الصفحات تعرض للمستخدم رسالة عامة وتسجّل التفاصيل.
 */
function send_mail(PDO $pdo, string $to, string $subject, string $html, string $text): ?string {
    $c = mail_config($pdo);
    if ($c['host'] === '' || $c['user'] === '' || $c['pass'] === '' || $c['from'] === '') {
        return 'إعدادات البريد غير مكتملة';
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return 'عنوان المستلم غير صالح';

    $implicitTls = $c['port'] === 465;
    $peerName = $c['tls_name'] !== '' ? $c['tls_name'] : $c['host'];
    $ctx = stream_context_create(['ssl' => [
        'verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $peerName,
        'SNI_enabled' => true, 'SNI_server_name' => $peerName,
    ]]);
    $remote = ($implicitTls ? 'ssl://' : 'tcp://') . $c['host'] . ':' . $c['port'];
    $errno = 0; $errstr = '';
    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) return "تعذّر الاتصال بخادم البريد ({$errstr})";
    stream_set_timeout($fp, 15);

    $read = function () use ($fp): array {
        // رد SMTP قد يمتد على أسطر «250-…» ثم «250 …»
        $lines = [];
        while (($line = fgets($fp, 2048)) !== false) {
            $lines[] = rtrim($line, "\r\n");
            if (strlen($line) < 4 || $line[3] !== '-') break;
        }
        $code = $lines ? (int)substr($lines[0], 0, 3) : 0;
        return [$code, implode("\n", $lines)];
    };
    $send = function (string $cmd) use ($fp, $read): array {
        fwrite($fp, $cmd . "\r\n");
        return $read();
    };
    $fail = function (string $step, array $r) use ($fp): string {
        @fwrite($fp, "QUIT\r\n"); @fclose($fp);
        return "{$step}: " . ($r[1] !== '' ? $r[1] : 'لا ردّ من الخادم');
    };

    [$code, $msg] = $read();
    if ($code !== 220) return $fail('الترحيب', [$code, $msg]);

    $ehloName = preg_replace('/[^a-z0-9.-]/i', '', $_SERVER['SERVER_NAME'] ?? '') ?: 'kidora.local';
    $r = $send("EHLO {$ehloName}");
    if ($r[0] !== 250) return $fail('EHLO', $r);

    if (!$implicitTls) {
        if (stripos($r[1], 'STARTTLS') === false) return $fail('STARTTLS', [0, 'الخادم لا يدعم STARTTLS']);
        $r = $send('STARTTLS');
        if ($r[0] !== 220) return $fail('STARTTLS', $r);
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            return $fail('TLS', [0, 'فشل تفعيل التشفير']);
        }
        $r = $send("EHLO {$ehloName}");
        if ($r[0] !== 250) return $fail('EHLO', $r);
    }

    // AUTH PLAIN أولاً؛ إن رُفضت الآلية نجرّب LOGIN
    $r = $send('AUTH PLAIN ' . base64_encode("\0{$c['user']}\0{$c['pass']}"));
    if ($r[0] !== 235) {
        $r = $send('AUTH LOGIN');
        if ($r[0] === 334) $r = $send(base64_encode($c['user']));
        if ($r[0] === 334) $r = $send(base64_encode($c['pass']));
        if ($r[0] !== 235) return $fail('المصادقة', $r);
    }

    $r = $send("MAIL FROM:<{$c['from']}>");
    if ($r[0] !== 250) return $fail('MAIL FROM', $r);
    $r = $send("RCPT TO:<{$to}>");
    if ($r[0] !== 250 && $r[0] !== 251) return $fail('RCPT TO', $r);
    $r = $send('DATA');
    if ($r[0] !== 354) return $fail('DATA', $r);

    $boundary = 'kidora_' . bin2hex(random_bytes(12));
    $fromDomain = substr(strrchr($c['from'], '@'), 1) ?: 'kidora.local';
    $encHeader = fn(string $s) => '=?UTF-8?B?' . base64_encode($s) . '?=';
    $headers = [
        'Date: ' . date(DATE_RFC2822),
        'From: ' . $encHeader($c['from_name']) . " <{$c['from']}>",
        "To: <{$to}>",
        'Subject: ' . $encHeader($subject),
        'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $fromDomain . '>',
        'MIME-Version: 1.0',
        'Auto-Submitted: auto-generated',
        "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
    ];
    $part = fn(string $type, string $body) => "--{$boundary}\r\nContent-Type: {$type}; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($body), 76, "\r\n");
    $data = implode("\r\n", $headers) . "\r\n\r\n"
        . $part('text/plain', $text)
        . $part('text/html', $html)
        . "--{$boundary}--\r\n";
    // نقطة في أول سطر تُضاعَف (RFC 5321 §4.5.2) — لا تظهر في base64 لكن الاحتياط أرخص
    $data = preg_replace('/^\./m', '..', $data);

    fwrite($fp, $data . "\r\n.\r\n");
    $r = $read();
    if ($r[0] !== 250) return $fail('الإرسال', $r);
    $send('QUIT');
    fclose($fp);
    return null;
}

/**
 * قالب رسالة HTML بسيط بألوان المنصة، يُقرأ من اليمين لليسار.
 * $ctaUrl و$ctaLabel اختياريان.
 */
function mail_template(string $title, string $bodyHtml, string $ctaUrl = '', string $ctaLabel = ''): string {
    $cta = $ctaUrl !== ''
        ? '<p style="text-align:center;margin:28px 0;"><a href="' . h($ctaUrl) . '" style="background:linear-gradient(135deg,#FF7A50,#FF6FA5);color:#fff;text-decoration:none;font-weight:800;padding:14px 30px;border-radius:40px;display:inline-block;font-size:16px;">' . h($ctaLabel) . '</a></p>'
        : '';
    // Gmail وأمثاله يتجاهلون dir على <html>/<body> ويحتفظون بالأنماط المضمّنة فقط،
    // لذا الاتجاه والمحاذاة يُكرَّران مضمّنَين على كل حاوية وعلى الجدول الخارجي.
    $rtl = 'direction:rtl;text-align:right;unicode-bidi:embed;';
    return '<!doctype html><html dir="rtl" lang="ar"><head><meta charset="utf-8"></head>'
        . '<body dir="rtl" style="margin:0;background:#1B1035;padding:24px;font-family:Cairo,Tahoma,Arial,sans-serif;' . $rtl . '">'
        . '<table role="presentation" dir="rtl" width="100%" cellpadding="0" cellspacing="0" style="' . $rtl . '"><tr><td align="center">'
        . '<div dir="rtl" style="max-width:560px;margin:0 auto;background:#fff;border-radius:22px;padding:30px;color:#241645;line-height:1.9;font-size:16px;' . $rtl . '">'
        . '<div style="text-align:center;font-size:30px;font-weight:900;color:#6C63FF;">Kidora ✨</div>'
        . '<h2 dir="rtl" style="text-align:center;margin:12px 0 18px;color:#241645;">' . h($title) . '</h2>'
        . '<div dir="rtl" style="' . $rtl . '">' . $bodyHtml . '</div>' . $cta
        . '<p dir="rtl" style="color:#8b7aa8;font-size:12px;text-align:center;margin-top:24px;">هذه رسالة تلقائية من منصة Kidora — لا حاجة للرد عليها.</p>'
        . '</div></td></tr></table></body></html>';
}
