<?php
$to = 'info@ihlcon.com';
$subject_prefix = '[IHL Website Inquiry]';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: contact.html');
    exit;
}

// ── ANTI-SPAM LAYER 1: Honeypot ──
// If the hidden "website" field is filled, it's a bot
if (!empty($_POST['website'])) {
    // Silently pretend success so bots don't retry
    header('Location: contact.html?status=success');
    exit;
}

// ── ANTI-SPAM LAYER 2: Time-based check ──
// Reject submissions faster than 3 seconds (bots submit instantly)
$ts = isset($_POST['_ts']) ? intval($_POST['_ts']) : 0;
$now = time();
if ($ts === 0 || ($now - $ts) < 3) {
    header('Location: contact.html?status=error');
    exit;
}
// Also reject if timestamp is more than 1 hour old (stale/replayed form)
if (($now - $ts) > 3600) {
    header('Location: contact.html?status=error');
    exit;
}

// ── ANTI-SPAM LAYER 3: JavaScript token verification ──
// Bots that POST directly without rendering JS won't have the correct token
$token = isset($_POST['_token']) ? trim($_POST['_token']) : '';
$raw = 'ihl_' . $ts . '_contact';
$hash = 0;
for ($i = 0; $i < strlen($raw); $i++) {
    $hash = (($hash << 5) - $hash) + ord($raw[$i]);
    $hash = $hash & 0xFFFFFFFF; // Keep as 32-bit
    if ($hash > 0x7FFFFFFF) $hash -= 0x100000000; // Convert to signed
}
$expected = base_convert(abs($hash), 10, 36);
// Check both positive and negative hash representations
$expected_neg = '-' . $expected;
if ($token !== $expected && $token !== $expected_neg && $token !== base_convert(abs($hash), 10, 36)) {
    // Recalculate with JS-matching signed integer behavior
    $jsHash = 0;
    for ($i = 0; $i < strlen($raw); $i++) {
        $jsHash = (($jsHash << 5) - $jsHash) + ord($raw[$i]);
        // Simulate JS 32-bit signed integer overflow
        $jsHash = $jsHash | 0;
        if ($jsHash > 2147483647) $jsHash -= 4294967296;
        if ($jsHash < -2147483648) $jsHash += 4294967296;
    }
    $jsExpected = ($jsHash < 0 ? '-' : '') . base_convert(abs($jsHash), 10, 36);
    if ($token !== $jsExpected) {
        header('Location: contact.html?status=error');
        exit;
    }
}

// ── ANTI-SPAM LAYER 4: Content checks ──
// Collect and sanitize fields
$name    = isset($_POST['name'])    ? trim(strip_tags($_POST['name']))    : '';
$phone   = isset($_POST['phone'])   ? trim(strip_tags($_POST['phone']))   : '';
$email   = isset($_POST['email'])   ? trim(strip_tags($_POST['email']))   : '';
$message = isset($_POST['message']) ? trim(strip_tags($_POST['message'])) : '';

// Basic validation
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: contact.html?status=error');
    exit;
}

// Reject messages with excessive URLs (common in spam)
$url_count = preg_match_all('/https?:\/\//i', $message, $m);
if ($url_count > 3) {
    header('Location: contact.html?status=success'); // fake success
    exit;
}

// Reject common spam phrases (case-insensitive)
$spam_phrases = [
    'buy now', 'click here', 'free money', 'act now', 'limited time',
    'dear sir/madam', 'SEO services', 'web traffic', 'casino',
    'cryptocurrency investment', 'bitcoin profit', 'make money fast',
    'work from home', 'viagra', 'cialis', 'order now',
    'earn extra cash', 'double your income', 'no obligation',
    'as seen on', 'web developer available', 'link building'
];
$combined = strtolower($name . ' ' . $message);
foreach ($spam_phrases as $phrase) {
    if (strpos($combined, strtolower($phrase)) !== false) {
        header('Location: contact.html?status=success'); // fake success
        exit;
    }
}

// Reject if name or message contains Cyrillic/Chinese/Arabic in excessive amounts
// (adjust if your legitimate audience uses these scripts)
if (preg_match('/[\x{0400}-\x{04FF}]{5,}/u', $name . $message) ||
    preg_match('/[\x{4E00}-\x{9FFF}]{5,}/u', $name . $message)) {
    header('Location: contact.html?status=success'); // fake success
    exit;
}

// Build the email
$subject = $subject_prefix . ' New inquiry from ' . ($name ?: 'Website visitor');

$body  = "You have received a new inquiry from the IHL website contact form.\n\n";
$body .= "────────────────────────────────────\n\n";
$body .= "Name:    " . ($name ?: '(not provided)') . "\n";
$body .= "Email:   " . $email . "\n";
$body .= "Phone:   " . ($phone ?: '(not provided)') . "\n\n";
$body .= "Message:\n" . ($message ?: '(no message)') . "\n\n";
$body .= "────────────────────────────────────\n";
$body .= "Sent from: ihlcon.com/contact.html\n";
$body .= "Time:      " . date('Y-m-d H:i:s T') . "\n";

$headers  = "From: IHL Website <noreply@ihlcon.com>\r\n";
$headers .= "Reply-To: " . $email . "\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

// Send
$sent = mail($to, $subject, $body, $headers);

if ($sent) {
    header('Location: contact.html?status=success');
} else {
    header('Location: contact.html?status=error');
}
exit;
