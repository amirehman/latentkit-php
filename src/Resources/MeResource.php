<?php

declare(strict_types=1);

namespace LatentKit\Resources;

use LatentKit\Responses\ConnectionContext;

final class MeResource extends Resource
{
    /** @return array<string, mixed> */
    public function retrieve(): array
    {
        return $this->request('GET', '/me');
    }

    public function retrieveContext(): ConnectionContext
    {
        return ConnectionContext::fromArray($this->retrieve());
    }
}
