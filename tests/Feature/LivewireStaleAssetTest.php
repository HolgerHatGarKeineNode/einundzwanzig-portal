<?php

use Livewire\Mechanisms\HandleRequests\EndpointResolver;

it('returns 404 for stale livewire css module urls instead of 500', function () {
    $prefix = EndpointResolver::prefix();

    $response = $this->get($prefix.'/css/meetups--landingpage.css?v=1502173559');

    $response->assertNotFound();
});

it('returns 404 for stale livewire global css module urls', function () {
    $prefix = EndpointResolver::prefix();

    $response = $this->get($prefix.'/css/meetups--landingpage.global.css?v=1');

    $response->assertNotFound();
});

it('returns 404 for stale livewire js module urls', function () {
    $prefix = EndpointResolver::prefix();

    $response = $this->get($prefix.'/js/meetups--landingpage.js?v=1');

    $response->assertNotFound();
});

it('still serves css for components that do have a style source', function () {
    $componentName = '__staletest_with_style_'.bin2hex(random_bytes(4));
    $bladePath = resource_path('views/livewire/'.$componentName.'.blade.php');

    file_put_contents($bladePath, <<<'BLADE'
        <?php

        use Livewire\Component;

        new class extends Component {
            public function render(): string
            {
                return '<div>test</div>';
            }
        }; ?>

        <div>test</div>
        <style>
        .foo { color: red; }
        </style>
        BLADE
    );

    try {
        $prefix = EndpointResolver::prefix();

        $response = $this->get($prefix.'/css/'.$componentName.'.css');

        $response->assertSuccessful();
        $response->assertHeader('content-type', 'text/css; charset=utf-8');
        expect($response->getContent())->toContain('.foo')->toContain('color: red');
    } finally {
        @unlink($bladePath);
    }
});
