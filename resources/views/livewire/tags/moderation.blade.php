<?php

use App\Models\Tag;
use App\Support\TagEditorGate;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Review queue for tags suggested by users without the editor permission.
 *
 * Without this screen the suggestion path is a one-way street: a proposed tag works on
 * its author's own event and is invisible to everyone else, forever. Approving it is
 * what turns one person's word into shared vocabulary.
 */
new class extends Component {
    public function mount(): void
    {
        abort_unless(TagEditorGate::allows(auth()->user()), 403);
    }

    public function getPendingProperty(): Collection
    {
        return Tag::query()
            ->pending()
            ->with('creator')
            ->orderBy('created_at')
            ->get();
    }

    public function approve(int $id): void
    {
        $tag = Tag::findOrFail($id);

        $this->authorize('approve', $tag);

        $tag->approve();

        session()->flash('status', __('Tag freigegeben.'));
    }

    /**
     * Rejecting deletes the tag, which also detaches it from the event it was proposed
     * on. That is deliberate: an unapproved tag only ever hung on its author's own
     * event, so nothing shared is lost.
     */
    public function reject(int $id): void
    {
        $tag = Tag::findOrFail($id);

        $this->authorize('delete', $tag);

        $tag->delete();

        session()->flash('status', __('Tag abgelehnt und entfernt.'));
    }
}; ?>

<div class="mx-auto w-full max-w-3xl p-4">
    <flux:heading size="xl">{{ __('Tag-Vorschläge') }}</flux:heading>

    <flux:text class="mt-2">
        {{ __('Vorschläge von Nutzern ohne Redaktionsrecht. Bis zur Freigabe sind sie nur am eigenen Event des Vorschlagenden sichtbar.') }}
    </flux:text>

    @if (session('status'))
        <flux:callout variant="success" class="mt-4">{{ session('status') }}</flux:callout>
    @endif

    @if ($this->pending->isEmpty())
        <flux:callout class="mt-6" data-testid="moderation-empty">
            {{ __('Keine offenen Vorschläge.') }}
        </flux:callout>
    @else
        <div class="mt-6 flex flex-col gap-3" data-testid="moderation-list">
            @foreach ($this->pending as $tag)
                <div class="flex items-center justify-between gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700"
                     wire:key="pending-{{ $tag->id }}">
                    <div class="min-w-0">
                        <div class="font-medium">{{ $tag->displayName() }}</div>
                        <div class="mt-1 text-xs opacity-60">
                            {{ $tag->type ?? '—' }}
                            &middot;
                            {{ $tag->creator?->name ?? __('unbekannt') }}
                            &middot;
                            {{ $tag->created_at?->diffForHumans() }}
                        </div>
                    </div>

                    <div class="flex shrink-0 gap-2">
                        <flux:button size="sm" variant="primary"
                                     wire:click="approve({{ $tag->id }})"
                                     data-testid="approve-{{ $tag->id }}">
                            {{ __('Freigeben') }}
                        </flux:button>
                        <flux:button size="sm" variant="danger"
                                     wire:click="reject({{ $tag->id }})"
                                     data-testid="reject-{{ $tag->id }}">
                            {{ __('Ablehnen') }}
                        </flux:button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
