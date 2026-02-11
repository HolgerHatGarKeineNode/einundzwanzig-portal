<?php

namespace App\Livewire\Forms;

use App\Enums\SelfHostedServiceType;
use App\Models\SelfHostedService;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Form;

class ServiceForm extends Form
{
    #[Locked]
    public ?SelfHostedService $service = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string')]
    public ?string $intro = null;

    #[Validate('nullable|url|max:255')]
    public ?string $url_clearnet = null;

    #[Validate('nullable|string|max:255')]
    public ?string $url_onion = null;

    #[Validate('nullable|string|max:255')]
    public ?string $url_i2p = null;

    #[Validate('nullable|string|max:255')]
    public ?string $url_pkdns = null;

    #[Validate('nullable|ip|max:45')]
    public ?string $ip = null;

    #[Validate('required')]
    public ?string $type = null;

    #[Validate('nullable|string')]
    public ?string $contact = null;

    #[Validate('boolean')]
    public bool $anonymous = false;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => [
                'required',
                'in:'.collect(SelfHostedServiceType::cases())->map(fn ($c) => $c->value)->implode(','),
            ],
            'intro' => ['nullable', 'string'],
            'url_clearnet' => ['nullable', 'url', 'max:255'],
            'url_onion' => ['nullable', 'string', 'max:255'],
            'url_i2p' => ['nullable', 'string', 'max:255'],
            'url_pkdns' => ['nullable', 'string', 'max:255'],
            'ip' => ['nullable', 'ip', 'max:45'],
            'contact' => ['nullable', 'string'],
            'anonymous' => ['boolean'],
        ];
    }

    public function setService(SelfHostedService $service): void
    {
        $this->service = $service;

        $this->name = $service->name;
        $this->intro = $service->intro;
        $this->url_clearnet = $service->url_clearnet;
        $this->url_onion = $service->url_onion;
        $this->url_i2p = $service->url_i2p;
        $this->url_pkdns = $service->url_pkdns;
        $this->ip = $service->ip;
        $this->type = $service->type?->value;
        $this->contact = $service->contact;
        $this->anonymous = $service->anon;
    }

    public function store(): SelfHostedService
    {
        $this->validate();
        $this->validateAtLeastOneUrl();

        return SelfHostedService::create([
            'name' => $this->name,
            'type' => $this->type,
            'intro' => $this->intro,
            'url_clearnet' => $this->url_clearnet,
            'url_onion' => $this->url_onion,
            'url_i2p' => $this->url_i2p,
            'url_pkdns' => $this->url_pkdns,
            'ip' => $this->ip,
            'contact' => $this->contact,
            'anon' => $this->anonymous,
            'created_by' => auth()->id(),
        ]);
    }

    public function update(): void
    {
        $this->validate();
        $this->validateAtLeastOneUrl();

        $this->service->update([
            'name' => $this->name,
            'type' => $this->type,
            'intro' => $this->intro,
            'url_clearnet' => $this->url_clearnet,
            'url_onion' => $this->url_onion,
            'url_i2p' => $this->url_i2p,
            'url_pkdns' => $this->url_pkdns,
            'ip' => $this->ip,
            'contact' => $this->contact,
            'anon' => $this->anonymous,
        ]);
    }

    protected function validateAtLeastOneUrl(): void
    {
        if (empty($this->url_clearnet) && empty($this->url_onion) && empty($this->url_i2p) && empty($this->url_pkdns) && empty($this->ip)) {
            $this->addError('url_clearnet', __('Mindestens eine URL oder IP muss angegeben werden.'));
            throw new \Illuminate\Validation\ValidationException(
                \Illuminate\Support\Facades\Validator::make([], [])
            );
        }
    }
}
