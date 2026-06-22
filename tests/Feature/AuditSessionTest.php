<?php

use App\Models\AuditSession;
use App\Models\AuditSessionOnu;
use App\Models\Olt;
use App\Models\User;

it('can create an audit session', function () {
    $user = User::factory()->create();
    $olt = Olt::factory()->create();

    $response = $this->actingAs($user)->postJson('/audit/sessions', [
        'olt_id' => $olt->id,
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
            'data' => [
                'status' => 'active',
                'olt_id' => $olt->id,
            ],
        ]);

    $this->assertDatabaseHas('audit_sessions', [
        'user_id' => $user->id,
        'olt_id' => $olt->id,
        'status' => 'active',
    ]);
});

it('can create an audit session with custom name', function () {
    $user = User::factory()->create();
    $olt = Olt::factory()->create();

    $response = $this->actingAs($user)->postJson('/audit/sessions', [
        'name' => 'Audit Kantor Pusat',
        'olt_id' => $olt->id,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Audit Kantor Pusat');
});

it('generates auto name when name is empty', function () {
    $user = User::factory()->create();
    $olt = Olt::factory()->create();

    $response = $this->actingAs($user)->postJson('/audit/sessions', [
        'olt_id' => $olt->id,
    ]);

    $response->assertOk();
    $name = $response->json('data.name');
    expect($name)->toMatch('/^AUDIT-\d{8}-\d{3}$/');
});

it('can save ONUs to a session', function () {
    $user = User::factory()->create();
    $session = AuditSession::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson("/audit/sessions/{$session->id}/save", [
        'onus' => [
            ['olt_index' => 'gpon-olt_1/1/1', 'sn' => 'ZTEG00000001', 'model' => 'F670LV9.0', 'pw' => 'GD0001'],
            ['olt_index' => 'gpon-olt_1/1/2', 'sn' => 'ZTEG00000002', 'model' => 'F670LV9.0', 'pw' => 'GD0002'],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.onu_count', 2);

    $this->assertDatabaseHas('audit_session_onus', [
        'audit_session_id' => $session->id,
        'sn' => 'ZTEG00000001',
    ]);
});

it('can list audit sessions', function () {
    $user = User::factory()->create();
    AuditSession::factory()->count(3)->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->getJson('/audit/sessions');

    $response->assertOk()
        ->assertJsonCount(3, 'data.data');
});

it('can get session detail with ONUs', function () {
    $user = User::factory()->create();
    $session = AuditSession::factory()->create(['user_id' => $user->id]);
    AuditSessionOnu::factory()->count(3)->create(['audit_session_id' => $session->id]);

    $response = $this->actingAs($user)->getJson("/audit/sessions/{$session->id}");

    $response->assertOk()
        ->assertJsonCount(3, 'data.onus');
});

it('can complete a session', function () {
    $user = User::factory()->create();
    $session = AuditSession::factory()->create(['user_id' => $user->id, 'status' => 'active']);

    $response = $this->actingAs($user)->postJson("/audit/sessions/{$session->id}/complete");

    $response->assertOk();

    $this->assertDatabaseHas('audit_sessions', [
        'id' => $session->id,
        'status' => 'completed',
    ]);
});

it('can delete a session', function () {
    $user = User::factory()->create();
    $session = AuditSession::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->deleteJson("/audit/sessions/{$session->id}");

    $response->assertOk();
    $this->assertDatabaseMissing('audit_sessions', ['id' => $session->id]);
});

it('can get active session', function () {
    $user = User::factory()->create();
    AuditSession::factory()->create(['user_id' => $user->id, 'status' => 'active']);

    $response = $this->actingAs($user)->getJson('/audit/sessions/active');

    $response->assertOk()
        ->assertJsonPath('data.status', 'active');
});

it('returns null when no active session', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/audit/sessions/active');

    $response->assertOk()
        ->assertJsonPath('data', null);
});

it('validates olt_id is required', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/audit/sessions', []);

    // Validation returns 422 or 302 depending on middleware
    expect($response->status())->toBeIn([422, 302]);
});

it('validates olt_id exists', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/audit/sessions', [
        'olt_id' => 999,
    ]);

    // Validation returns 422 or 302 depending on middleware
    expect($response->status())->toBeIn([422, 302]);
});

it('cannot save to inactive session', function () {
    $user = User::factory()->create();
    $session = AuditSession::factory()->create(['user_id' => $user->id, 'status' => 'completed']);

    $response = $this->actingAs($user)->postJson("/audit/sessions/{$session->id}/save", [
        'onus' => [
            ['olt_index' => 'gpon-olt_1/1/1', 'sn' => 'ZTEG00000001', 'model' => 'F670LV9.0', 'pw' => 'GD0001'],
        ],
    ]);

    $response->assertStatus(400);
});
