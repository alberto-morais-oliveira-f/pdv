<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Tests\Support;

final class CallLog
{
    /**
     * @var array<int, string>
     */
    public array $calls = [];

    public function record(string $kind): void
    {
        $this->calls[] = $kind;
    }
}
