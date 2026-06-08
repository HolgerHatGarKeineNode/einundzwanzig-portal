<?php

use App\Mcp\Servers\EinundzwanzigServer;
use App\Mcp\Tools\CourseEvent\UpdateCourseEventTool;
use App\Mcp\Tools\Meetup\CreateMeetupTool;
use App\Mcp\Tools\Search\SearchCitiesTool;

it('rejects unauthenticated requests to the mcp endpoint', function () {
    $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])->assertUnauthorized();
});

it('registers every domain tool on the server', function () {
    $property = (new ReflectionClass(EinundzwanzigServer::class))->getProperty('tools');
    $tools = $property->getDefaultValue();

    expect($tools)->toHaveCount(38)
        ->and($tools)->toContain(CreateMeetupTool::class)
        ->and($tools)->toContain(UpdateCourseEventTool::class)
        ->and($tools)->toContain(SearchCitiesTool::class);
});

it('serves every tool on a single tools/list page', function () {
    $reflection = new ReflectionClass(EinundzwanzigServer::class);
    $tools = $reflection->getProperty('tools')->getDefaultValue();
    $defaultPerPage = $reflection->getProperty('defaultPaginationLength')->getDefaultValue();

    // Some MCP clients (e.g. the Claude.ai web connector) only load the first
    // tools/list page and do not follow the nextCursor, so every tool must fit on it.
    expect($defaultPerPage)->toBeGreaterThanOrEqual(count($tools));
});
