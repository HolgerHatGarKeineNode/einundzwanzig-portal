<?php

use App\Models\Meetup;
use Livewire\Livewire;

/**
 * The single-event save button (`type="submit"` inside `<form wire:submit="save">`)
 * is already covered by Livewire's own supportDisablingFormsDuringRequest feature:
 * it walks the form and disables every submit button while the request is in
 * flight, no extra markup needed.
 *
 * The series-confirmation button is different: it sits inside
 * `<flux:modal.close>`, after the form's closing `</form>` tag in the rendered
 * markup, so Livewire's automatic form-disabling never reaches it — it needs its
 * own explicit wire:loading wiring, which is what these tests guard.
 */
it('locks both series-save triggers to the save action while it is in flight', function () {
    $leader = actingAsUser();
    $meetup = Meetup::factory()->create(['created_by' => $leader->id]);

    $html = Livewire::test('meetups.create-edit-events', ['meetup' => $meetup])
        ->set('seriesMode', true)
        ->html();

    // The modal trigger: locked so a second click cannot reopen the confirmation
    // and fire a second series while the first save() is still running. The window
    // is generous — Flux buttons render a long Tailwind class list before their
    // wire:* attributes.
    $triggerMarkup = mb_substr($html, mb_strpos($html, 'confirm-series'), 2000);
    expect($triggerMarkup)->toContain('wire:loading.attr="disabled"')
        ->and($triggerMarkup)->toContain('wire:target="save"');

    // The confirm button itself, further down in the modal markup.
    $confirmClickPosition = mb_strpos($html, 'wire:click="save"');
    $confirmMarkup = mb_substr($html, max(0, $confirmClickPosition - 1500), 1800);
    expect($confirmMarkup)->toContain('wire:loading.attr="disabled"');
});

it('shows a pending indicator targeting the save action', function () {
    $leader = actingAsUser();
    $meetup = Meetup::factory()->create(['created_by' => $leader->id]);

    $html = Livewire::test('meetups.create-edit-events', ['meetup' => $meetup])
        ->set('seriesMode', true)
        ->html();

    expect($html)->toContain('wire:loading wire:target="save"')
        ->and($html)->toContain('Wird gespeichert');
});
