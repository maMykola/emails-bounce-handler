<?php

/**
 * Return email message headers and read lines from given file handler.
 * All header keys changed to lowercase.
 *
 * @param  resource  $fh
 * @return array
 * @author Mykola Martynov
 **/
function getMessageHeaders($fh)
{
    $all_lines = [];
    while (!feof($fh)) {
        $line = rtrim(fgets($fh));
        if (empty($line)) {
            break;
        }

        $all_lines[] = $line;
    }

    return parseHeaderLines($all_lines);
}

/**
 * Return message headers parsed from the given lines.
 *
 * @param  array  $all_lines
 * @return array
 * @author Mykola Martynov
 **/
function parseHeaderLines($all_lines)
{
    $headers = [];
    $key = '';

    foreach ($all_lines as $line) {
        # add value to previously added header
        if (preg_match('#^[\s\t]+(?P<data>.*)$#', $line, $match)) {
            $headers[$key] .= ' ' . $match['data'];
            continue;
        }

        # otherwise get new key/value pair
        if (preg_match('#^(?P<key>.*?):\s*(?P<value>.*)$#', $line, $match)) {
            $key = strtolower($match['key']);
            $headers[$key] = $match['value'];
            continue;
        }

        # if message header invalid, clear previous key
        $key = '';
    }

    return $headers;
}

/**
 * Return body of the message.
 *
 * ASSUMPTION: getMessageHeaders() must be invoked before.
 *
 * @param  resource  $fh
 * @return string
 * @author Mykola Martynov
 **/
function getMessageBody($fh)
{
    $body = '';
    while (!feof($fh)) {
        $body .= fgets($fh);
    }
    return $body;
}

/**
 * Extract email from the given text.
 *
 * @param  string  $text
 * @return string
 * @author Mykola Martynov
 **/
function extractEmail($text)
{
    $email = filter_var(trim($text), FILTER_VALIDATE_EMAIL);
    if (!empty($email)) {
        return $email;
    }

    if (!preg_match('#<(?P<email>.*?)>#', $text, $match)) {
        return '';
    }

    return $match['email'];
}

/**
 * Return value from the headers list with given key.
 *
 * ASSUMPTION: key must be lowercase.
 *
 * @param  array   $headers
 * @param  string  $key
 * @param  string  $default
 * @return string
 * @author Mykola Martynov
 **/
function getHeaderValue($headers, $key, $default = '')
{
    return array_key_exists($key, $headers) ? $headers[$key] : $default;
}

/**
 * Get content parts from the message body.
 *
 * @param  string  $body
 * @param  array   $headers
 * @return array
 * @author Mykola Martynov
 **/
function fetchBodyParts($body, $headers)
{
    if (empty($headers['content-type']) || !preg_match('#boundary="(?P<boundary>.*?)"#', $headers['content-type'], $match)) {
        return [];
    }

    $boundary = preg_quote($match['boundary']);
    preg_match_all("#--{$boundary}\R+(?P<parts>.*?)(?=--{$boundary})#s", $body, $match);
    
    $parts = [];
    foreach ($match['parts'] as $text) {
        list($content_header, $content) = preg_split("#\R{2}#", $text, 2);
        $current_headers = parseHeaderLines(splitHeader($content_header));
        $key = getHeaderValue($current_headers, 'content-description');

        $parts[$key] = $content;
    }

    return $parts;
}

/**
 * Split header text to lines.
 *
 * @param  string  $header_text
 * @return array
 * @author Mykola Martynov
 **/
function splitHeader($header_text)
{
    return preg_split("#\R+#", $header_text);
}

/**
 * Get message status report.
 *
 * @param  array  $parts
 * @return string
 * @author Mykola Martynov
 **/
function getMessageReport($parts)
{
    $report = [];

    # fetch status from delivery report
    if (array_key_exists(PART_DELIVERY_REPORT, $parts)) {
        $delivery_report = parseHeaderLines(splitHeader($parts[PART_DELIVERY_REPORT]));
        $report['status'] = getHeaderValue($delivery_report, 'status');
        $report['diagnostic'] = getHeaderValue($delivery_report, 'diagnostic-code');
    }

    # fetch recipient from original message headers
    if (array_key_exists(PART_ORIGNAL_MESSAGE_HEADERS, $parts)) {
        $message_headers = parseHeaderLines(splitHeader($parts[PART_ORIGNAL_MESSAGE_HEADERS]));
        $report['email'] = extractEmail(getHeaderValue($message_headers, 'to'));
        $report['mail-id'] = getHeaderValue($message_headers, 'x-mail-id');
    }

    return $report;
}
