<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$memberId = 'OK1988589';
$pin = '7761';
$passwordH2H = '@jkn1234';

$formats = [
    'msg_S' => 'S',
    'msg_S_pin' => "S.{$pin}",
    'msg_SAL' => 'SAL',
    'msg_SAL_pin' => "SAL.{$pin}",
    'msg_SALDO' => 'SALDO',
    'msg_SALDO_pin' => "SALDO.{$pin}",
];

foreach ($formats as $name => $cmd) {
    // We send msg along with other tebakan parameters
    $params = [
        'id'       => $memberId,
        'uid'      => $memberId,
        'memberid' => $memberId,
        'memberID' => $memberId,
        'pass'     => $passwordH2H,
        'password' => $passwordH2H,
        'pin_ip'   => $passwordH2H,
        'key'      => $passwordH2H,
        'perintah' => $cmd,
        'pesan'    => $cmd,
        'q'        => $cmd,
        'sms'      => $cmd,
        'msg'      => $cmd, // <-- Important!
        'pin'      => $pin,
    ];

    $queryParts = [];
    foreach ($params as $key => $value) {
        $queryParts[] = $key . '=' . str_replace('#', '%23', $value);
    }
    $urlTarget = "https://h2h.okeconnect.com/trx?" . implode('&', $queryParts);

    $context = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);
    $responseBody = file_get_contents($urlTarget, false, $context);

    echo "Format: {$name} (command: {$cmd})\n";
    echo "Response: {$responseBody}\n\n";
}
