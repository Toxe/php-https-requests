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

function get_http_version_text(int $version): string
{
    switch ($version) {
        case CURL_HTTP_VERSION_1_1:
            return "HTTP/1.1";
        case CURL_HTTP_VERSION_2:
            return "HTTP/2";
        default:
            return "???";
    }
}

function print_curl_error(CLImate $terminal, CurlHandle $ch): bool
{
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    curl_close($ch);
    $terminal->inline(str_repeat(' ', 7 + 2 + 11 + 2 + 2 + 8 + 2));
    $terminal->red()->out("(curl error $errno) $error");

    return false;
}

function print_results(CLImate $terminal, int $version, CurlHandle $ch, array $headers): void
{
    $info = curl_getinfo($ch);
    $status = (int) $info["http_code"];
    $status_line = get_http_response_status_line($headers);
    $total_size = ($info["header_size"] + $info["size_download"]) / 1024.0;
    $total_time = (int) round($info["total_time"] * 1000.0);

    $terminal->inline(str_pad("$total_time ms", 7 + 2, ' ', STR_PAD_LEFT));
    $terminal->inline(str_pad(sprintf("%.02f KB", $total_size), 11 + 2, ' ', STR_PAD_LEFT));
    $terminal->inline("  ");
    $terminal->lightBlue()->inline(str_pad(get_http_version_text($version), 8 + 2));

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

function request(CLImate $terminal, string $url, int $version): bool
{
    $headers = [];

    $options = [
        CURLOPT_URL => $url,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_HEADER => false,
        CURLOPT_HTTP_VERSION => $version,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADERFUNCTION => function (CurlHandle $ch, string $header) use (&$headers): int {
            $headers[] = trim($header);
            return strlen($header);
        },
    ];

    // HTTPS options
    if (stripos($url, "https://") === 0) {
        $options[CURLOPT_SSL_VERIFYHOST] = 2;
        $options[CURLOPT_SSL_VERIFYPEER] = true;
    }

    if (($ch = curl_init()) === false)
        return print_curl_error($terminal, $ch);

    if (!curl_setopt_array($ch, $options))
        return print_curl_error($terminal, $ch);

    if (($content = curl_exec($ch)) === false)
        return print_curl_error($terminal, $ch);

    print_results($terminal, $version, $ch, $headers);
    curl_close($ch);

    return true;
}

$urls = require("config.php");
$terminal = new CLImate;

$longest_url_length = (int) array_reduce($urls, fn($length, $url) => max($length, strlen($url)), 0);

foreach ($urls as $url) {
    foreach (["http", "https"] as $scheme) {
        foreach ([CURL_HTTP_VERSION_1_1, CURL_HTTP_VERSION_2] as $version) {
            $terminal->inline(str_pad(strtoupper($scheme), 5 + 2));
            $terminal->bold()->inline(str_pad($url, $longest_url_length));
            request($terminal, "$scheme://$url", $version);
        }
    }
}
