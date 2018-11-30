#!/usr/bin/env php
<?php

if (file_exists(__DIR__ . '/config/parameters.php')) {
    require_once __DIR__ . '/config/parameters.php';
}

require_once __DIR__ . '/include/constants.php';
require_once __DIR__ . '/include/messages.php';
require_once __DIR__ . '/include/logs.php';

# set default permission for log files
umask(027);

# get message headers
$headers = getMessageHeaders(STDIN);
$body = getMessageBody(STDIN);

$parts = fetchBodyParts($body, $headers);
$report = getMessageReport($parts);

if (!empty($report)) {
    $report['date'] = getHeaderValue($headers, 'date', date('Y-m-d H:i:s'));
    logBounceEmail($report);
}
