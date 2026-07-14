<?php

declare(strict_types=1);

namespace LatentKit\Transport;

use LatentKit\Exceptions\TransportException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class Psr18Transport implements TransportInterface
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    public function request(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        float $timeoutSeconds,
    ): HttpResponse {
        unset($timeoutSeconds); // PSR-18 clients own their timeout configuration.

        $request = $this->requestFactory->createRequest(strtoupper($method), $url);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        if ($body !== null) {
            $request = $request->withBody($this->streamFactory->createStream($body));
        }

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw new TransportException('LatentKit request failed through the PSR-18 client.', 0, $exception);
        }

        $responseHeaders = [];
        foreach ($response->getHeaders() as $name => $values) {
            $responseHeaders[strtolower($name)] = implode(', ', $values);
        }

        return new HttpResponse(
            $response->getStatusCode(),
            (string) $response->getBody(),
            $responseHeaders,
        );
    }
}
