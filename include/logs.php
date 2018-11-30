<?php

/**
 * Save given information to process bounced emails later.
 *
 * ASSUMPTION: $info must include at least email, status report,
 *             and date when bounced email was received.
 *
 * @param  array  $info
 * @return void
 * @author Mykola Martynov
 **/
function logBounceEmail($info)
{
    $log_file = LOG_DIR . date('Ymd-H') . '.log';
    $fh = fopen($log_file, 'a');
    fputs($fh, serialize($info) . PHP_EOL);
    fclose($fh);
}