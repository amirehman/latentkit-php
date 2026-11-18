<?php

declare(strict_types=1);

namespace LatentKit\Transport;

use LatentKit\Exceptions\TransportException;

final class CurlTransport implements TransportInterface, StreamingTransportInterface
{
    public function __construct()
    {
        if (!extension_loaded('curl')) {
            throw new TransportException(
                'The cURL extension is required by CurlTransport. Install ext-curl or inject Psr18Transport.',
            );
        }
    }

    public function request(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        float $timeoutSeconds,
    ): HttpResponse {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new TransportException('Unable to initialize cURL.');
        }

        /** @var array<string, string> $responseHeaders */
        $responseHeaders = [];
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = sprintf('%s: %s', $name, $value);
        }

        $options = [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT_MS => max(1, (int) round(min(10.0, $timeoutSeconds) * 1000)),
            CURLOPT_TIMEOUT_MS => max(1, (int) round($timeoutSeconds * 1000)),
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $length = strlen($line);
                $trimmed = trim($line);
                if ($trimmed === '') {
                    return $length;
                }
                if (str_starts_with(strtoupper($trimmed), 'HTTP/')) {
                    $responseHeaders = [];
                    return $length;
                }
                $separator = strpos($line, ':');
                if ($separator === false) {
                    return $length;
                }
                $name = strtolower(trim(substr($line, 0, $separator)));
                $value = trim(substr($line, $separator + 1));
                if ($name !== '') {
                    $responseHeaders[$name] = isset($responseHeaders[$name])
                        ? $responseHeaders[$name] . ', ' . $value
                        : $value;
                }
                return $length;
            },
        ];
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($handle, $options);
        $responseBody = curl_exec($handle);
        if ($responseBody === false) {
            $message = curl_error($handle);
            $errorCode = curl_errno($handle);
            throw new TransportException(
                $message !== '' ? "LatentKit request failed: {$message}" : 'LatentKit request failed.',
                $errorCode,
            );
        }

        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        return new HttpResponse($statusCode, (string) $responseBody, $responseHeaders);
    }

    public function stream(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        float $timeoutSeconds,
    ): StreamedHttpResponse {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new TransportException('Unable to initialize cURL.');
        }
        $multi = curl_multi_init();

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = sprintf('%s: %s', $name, $value);
        }

        $responseHeaders = [];
        $statusCode = 0;
        $headersComplete = false;
        /** @var list<string> $pending */
        $pending = [];
        $options = [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT_MS => max(1, (int) round(min(10.0, $timeoutSeconds) * 1000)),
            // The whole stream shares the request deadline the gateway was told about.
            CURLOPT_TIMEOUT_MS => max(1, (int) round($timeoutSeconds * 1000)),
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (
                &$responseHeaders,
                &$statusCode,
                &$headersComplete,
            ): int {
                $length = strlen($line);
                $trimmed = trim($line);
                if ($trimmed === '') {
                    // An interim response (100 Continue) is followed by the real one.
                    $headersComplete = $statusCode >= 200;
                    return $length;
                }
                if (str_starts_with(strtoupper($trimmed), 'HTTP/')) {
                    $responseHeaders = [];
                    $parts = explode(' ', $trimmed, 3);
                    $statusCode = (int) ($parts[1] ?? 0);
                    return $length;
                }
                $separator = strpos($line, ':');
                if ($separator === false) {
                    return $length;
                }
                $name = strtolower(trim(substr($line, 0, $separator)));
                $value = trim(substr($line, $separator + 1));
                if ($name !== '') {
                    $responseHeaders[$name] = isset($responseHeaders[$name])
                        ? $responseHeaders[$name] . ', ' . $value
                        : $value;
                }
                return $length;
            },
            CURLOPT_WRITEFUNCTION => static function ($curl, string $data) use (&$pending): int {
                $pending[] = $data;
                return strlen($data);
            },
        ];
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($handle, $options);
        curl_multi_add_handle($multi, $handle);

        $running = true;
        $release = static function () use ($multi, $handle): void {
            curl_multi_remove_handle($multi, $handle);
            curl_multi_close($multi);
        };
        // Advances the transfer one step and reports a failed transfer as an exception.
        $pump = static function () use ($multi, $handle, &$running): void {
            do {
                $status = curl_multi_exec($multi, $active);
            } while ($status === CURLM_CALL_MULTI_PERFORM);
            $running = $active > 0;
            if ($running) {
                curl_multi_select($multi, 1.0);
                return;
            }
            while (($info = curl_multi_info_read($multi)) !== false) {
                if ($info['handle'] === $handle && $info['result'] !== CURLE_OK) {
                    $message = curl_error($handle);
                    throw new TransportException(
                        $message !== '' ? "LatentKit request failed: {$message}" : 'LatentKit request failed.',
                        (int) $info['result'],
                    );
                }
            }
        };

        try {
            // Wait until the status and headers are known, so the caller can tell an
            // error response from a stream before reading any of the body.
            while ($running && !$headersComplete && $pending === []) {
                $pump();
            }
        } catch (\Throwable $exception) {
            $release();
            throw $exception;
        }

        $chunks = (static function () use (&$pending, &$running, $pump, $release): \Generator {
            try {
                while (true) {
                    while ($pending !== []) {
                        yield array_shift($pending);
                    }
                    if (!$running) {
                        return;
                    }
                    $pump();
                }
            } finally {
                // Runs when the stream ends, fails, or the caller stops reading early.
                $release();
            }
        })();

        return new StreamedHttpResponse($statusCode, $chunks, $responseHeaders);
    }
}
