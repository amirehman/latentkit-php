<?php

declare(strict_types=1);

namespace LatentKit\Transport;

use LatentKit\Exceptions\TransportException;

final class CurlTransport implements TransportInterface
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
            curl_close($handle);
            throw new TransportException(
                $message !== '' ? "LatentKit request failed: {$message}" : 'LatentKit request failed.',
                $errorCode,
            );
        }

        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return new HttpResponse($statusCode, (string) $responseBody, $responseHeaders);
    }
}
