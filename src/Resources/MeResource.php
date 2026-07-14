<?php

declare(strict_types=1);

namespace LatentKit\Resources;

final class MeResource extends Resource
{
    /** @return array<string, mixed> */
    public function retrieve(): array
    {
        return $this->request('GET', '/me');
    }
}
