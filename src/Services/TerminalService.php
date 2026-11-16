<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Services;

use Am2tec\Pdv\Models\Terminal;
use Illuminate\Database\Eloquent\Collection;

class TerminalService
{
    /**
     * @return Collection<int, Terminal>
     */
    public function list(): Collection
    {
        return Terminal::query()->orderBy('id')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Terminal
    {
        return Terminal::query()->create($data)->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Terminal $terminal, array $data): Terminal
    {
        $terminal->update($data);

        return $terminal->refresh();
    }

    /**
     * Soft deletes, so future payments keep their terminal reference.
     */
    public function delete(Terminal $terminal): void
    {
        $terminal->delete();
    }
}
