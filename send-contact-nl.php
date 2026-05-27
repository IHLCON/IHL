<?php
$to = 'info@ihlcon.com';
$subject_prefix = '[IHL Website Aanvraag]';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: nl/contact.html');
    exit;
}

// Collect and sanitize fields
$name    = isset($_POST['name'])    ? trim(strip_tags($_POST['name']))    : '';
$phone   = isset($_POST['phone'])   ? trim(strip_tags($_POST['phone']))   : '';
$email   = isset($_POST['email'])   ? trim(strip_tags($_POST['email']))   : '';
$message = isset($_POST['message']) ? trim(strip_tags($_POST['message'])) : '';

// Basic validation
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: nl/contact.html?status=error');
    exit;
}

// Build the email
$subject = $subject_prefix . ' Nieuwe aanvraag van ' . ($name ?: 'websitebezoeker');

$body  = "U heeft een nieuwe aanvraag ontvangen via het IHL contactformulier (NL).\n\n";
$body .= "────────────────────────────────────\n\n";
$body .= "Naam:      " . ($name ?: '(niet opgegeven)') . "\n";
$body .= "E-mail:    " . $email . "\n";
$body .= "Telefoon:  " . ($phone ?: '(niet opgegeven)') . "\n\n";
$body .= "Bericht:\n" . ($message ?: '(geen bericht)') . "\n\n";
$body .= "────────────────────────────────────\n";
$body .= "Verzonden via: ihlcon.com/nl/contact.html\n";
$body .= "Tijdstip:      " . date('Y-m-d H:i:s T') . "\n";

$headers  = "From: IHL Website <noreply@ihlcon.com>\r\n";
$headers .= "Reply-To: " . $email . "\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

// Send
$sent = mail($to, $subject, $body, $headers);

if ($sent) {
    header('Location: nl/contact.html?status=success');
} else {
    header('Location: nl/contact.html?status=error');
}
exit;
