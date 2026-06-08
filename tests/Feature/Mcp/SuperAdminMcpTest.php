<?php

use App\Mcp\Servers\EinundzwanzigServer;
use App\Mcp\Tools\SuperAdmin\SuperAdminCreateRecordTool;
use App\Mcp\Tools\SuperAdmin\SuperAdminDescribeModelTool;
use App\Mcp\Tools\SuperAdmin\SuperAdminListModelsTool;
use App\Mcp\Tools\SuperAdmin\SuperAdminListRecordsTool;
use App\Mcp\Tools\SuperAdmin\SuperAdminShowRecordTool;
use App\Mcp\Tools\SuperAdmin\SuperAdminUpdateRecordTool;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\User;
use Spatie\Permission\Models\Role;

function superAdmin(): User
{
    Role::findOrCreate('super-admin');

    return User::factory()->create()->assignRole('super-admin');
}

it('lets a super-admin update a meetup created by someone else', function () {
    $owner = User::factory()->create();
    $meetup = Meetup::factory()->create(['name' => 'Altname', 'created_by' => $owner->id]);

    EinundzwanzigServer::actingAs(superAdmin())
        ->tool(SuperAdminUpdateRecordTool::class, [
            'model' => 'meetup',
            'id' => $meetup->id,
            'attributes' => ['name' => 'Vom Admin geändert'],
        ])
        ->assertOk();

    $this->assertDatabaseHas('meetups', [
        'id' => $meetup->id,
        'name' => 'Vom Admin geändert',
    ]);
});

it('lets a super-admin create any model', function () {
    $country = Country::factory()->create();

    EinundzwanzigServer::actingAs(superAdmin())
        ->tool(SuperAdminCreateRecordTool::class, [
            'model' => 'city',
            'attributes' => [
                'name' => 'Adminstadt',
                'country_id' => $country->id,
                'longitude' => 1.0,
                'latitude' => 2.0,
            ],
        ])
        ->assertOk();

    $this->assertDatabaseHas('cities', ['name' => 'Adminstadt']);
});

it('lists editable models and describes their columns', function () {
    EinundzwanzigServer::actingAs(superAdmin())
        ->tool(SuperAdminListModelsTool::class)
        ->assertOk()
        ->assertSee('meetup');

    EinundzwanzigServer::actingAs(superAdmin())
        ->tool(SuperAdminDescribeModelTool::class, ['model' => 'meetup'])
        ->assertOk()
        ->assertSee('telegram_link');
});

it('lists and shows records of any model', function () {
    $meetup = Meetup::factory()->create(['name' => 'Sichtbar']);

    EinundzwanzigServer::actingAs(superAdmin())
        ->tool(SuperAdminListRecordsTool::class, ['model' => 'meetup'])
        ->assertOk()
        ->assertSee('Sichtbar');

    EinundzwanzigServer::actingAs(superAdmin())
        ->tool(SuperAdminShowRecordTool::class, ['model' => 'meetup', 'id' => $meetup->id])
        ->assertOk()
        ->assertSee('Sichtbar');
});

it('reports an unknown model with the list of available ones', function () {
    EinundzwanzigServer::actingAs(superAdmin())
        ->tool(SuperAdminShowRecordTool::class, ['model' => 'gibtsnicht', 'id' => 1])
        ->assertHasErrors();
});

it('refuses to write password or role fields via super-admin tools', function () {
    $target = User::factory()->create(['name' => 'Unverändert']);
    $originalPassword = $target->password;

    EinundzwanzigServer::actingAs(superAdmin())
        ->tool(SuperAdminUpdateRecordTool::class, [
            'model' => 'user',
            'id' => $target->id,
            'attributes' => ['name' => 'Neu', 'password' => 'gehackt'],
        ])
        ->assertHasErrors();

    $fresh = $target->fresh();
    expect($fresh->name)->toBe('Unverändert')
        ->and($fresh->password)->toBe($originalPassword);
});

it('refuses to write further protected fields like nostr or email verification', function () {
    $target = User::factory()->create(['nostr' => 'npub-original']);

    EinundzwanzigServer::actingAs(superAdmin())
        ->tool(SuperAdminUpdateRecordTool::class, [
            'model' => 'user',
            'id' => $target->id,
            'attributes' => ['nostr' => 'npub-gefälscht'],
        ])
        ->assertHasErrors();

    expect($target->fresh()->nostr)->toBe('npub-original');
});

it('denies a non super-admin from using the super-admin tools', function () {
    $meetup = Meetup::factory()->create(['name' => 'Geschützt']);

    EinundzwanzigServer::actingAs(User::factory()->create())
        ->tool(SuperAdminUpdateRecordTool::class, [
            'model' => 'meetup',
            'id' => $meetup->id,
            'attributes' => ['name' => 'Hijack'],
        ])
        ->assertHasErrors();

    $this->assertDatabaseHas('meetups', ['id' => $meetup->id, 'name' => 'Geschützt']);
});
