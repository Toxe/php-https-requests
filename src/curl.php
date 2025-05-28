<?php
declare(strict_types=1);

function print_curl_error(CurlHandle $ch, string $url): bool
{
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    curl_close($ch);
    echo "$url: $error ($errno)\n";

    return false;
}

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

function print_results(CurlHandle $ch, string $url, array $headers): void
{
    $info = curl_getinfo($ch);
    echo "$url: " . get_http_response_status_line($headers);

    switch ($info["http_code"]) {
        case 301:
            echo " (" . get_http_response_header($headers, "Location") . ")";
            break;
    }

    echo "\n";
}

function request(string $url): bool
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
        return print_curl_error($ch, $url);

    if (!curl_setopt_array($ch, $options))
        return print_curl_error($ch, $url);

    if (($content = curl_exec($ch)) === false)
        return print_curl_error($ch, $url);

    print_results($ch, $url, $headers);
    curl_close($ch);

    return true;
}

request("http://curl.se");
request("http://httpbin.org/ip");
request("http://www.microsoft.com");
request("http://www.mozilla.org");

request("https://curl.se");
request("https://httpbin.org/ip");
request("https://www.microsoft.com");
request("https://www.mozilla.org");
