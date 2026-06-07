<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LibraryItem;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Support\Collection;

#[Group(name: 'Community', weight: 7)]
class BindleController extends Controller
{
    /**
     * Bindles (Bibliotheks-Einträge) auflisten
     *
     * Liefert die Bibliothekseinträge vom Typ 'bindle' mit id, name, link und image.
     *
     * @return Collection<int, array{id: int, name: string, link: string, image: string}>
     */
    public function __invoke(): Collection
    {
        return LibraryItem::query()
            ->where('type', 'bindle')
            ->with([
                'media',
            ])
            ->orderByDesc('id')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'link' => strtok($item->value, '?'),
                'image' => $item->getFirstMediaUrl('main'),
            ]);
    }
}
