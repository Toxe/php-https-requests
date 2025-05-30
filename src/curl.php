<?php
declare(strict_types=1);
use League\CLImate\CLImate;

require_once("vendor/autoload.php");

function get_http_response_status_line(array $headers): ?string
{
    $s = array_find($headers, fn($value, $key) => stripos($value, "HTTP/") === 0);
    return $s;
}

function get_http_response_header(array $headers, string $name): ?string
{
    if (($line = array_find($headers, fn($value, $key) => stripos($value, "$name:") === 0)) === null)
        return null;

    $s = substr($line, strlen("$name:"));
    return trim($s);
}

function print_curl_error(CLImate $terminal, CurlHandle $ch): bool
{
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    curl_close($ch);
    $terminal->red()->out("(curl error $errno) $error");

    return false;
}

function print_results(CLImate $terminal, CurlHandle $ch, array $headers): void
{
    $info = curl_getinfo($ch);
    $status = (int) $info["http_code"];
    $status_line = get_http_response_status_line($headers);

    if ($status >= 200 && $status < 300)
        $terminal->green()->inline($status_line);
    else if ($status >= 300 && $status < 400)
        $terminal->yellow()->inline($status_line);
    else
        $terminal->red()->inline($status_line);

    switch ($status) {
        case 301:
            $terminal->yellow()->inline(" --> " . get_http_response_header($headers, "Location"));
            break;
    }

    $terminal->br();
}

function request(CLImate $terminal, string $url): bool
{
    $headers = [];

    $options = [
        CURLOPT_URL => $url,
        CURLOPT_HEADER => false,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADERFUNCTION => function (CurlHandle $ch, string $header) use (&$headers): int {
            $headers[] = trim($header);
            return strlen($header);
        },
    ];

    if (($ch = curl_init()) === false)
        return print_curl_error($terminal, $ch);

    if (!curl_setopt_array($ch, $options))
        return print_curl_error($terminal, $ch);

    if (($content = curl_exec($ch)) === false)
        return print_curl_error($terminal, $ch);

    print_results($terminal, $ch, $headers);
    curl_close($ch);

    return true;
}

$urls = require("config.php");
$terminal = new CLImate;

$longest_url_length = (int) array_reduce($urls, fn($length, $url) => max($length, strlen($url)), 0) + 1;

foreach ($urls as $url) {
    foreach (["HTTP", "HTTPS"] as $protocol) {
        $terminal->inline(str_pad($protocol, 6));
        $terminal->bold()->inline(str_pad($url, $longest_url_length + 1));
        request($terminal, "$protocol://$url");
    }
}
