<?php

use App\Enums\IncidentState;
use App\Enums\UserRole;
use App\Jobs\SendAlert;
use App\Mail\IncidentAlert;
use App\Models\AlertChannel;
use App\Models\CheckResult;
use App\Models\Circuit;
use App\Models\Incident;
use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use App\Models\User;
use App\Services\IncidentService;
use App\Services\StatusEvaluator;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/** Feed a pass/fail pattern (e.g. "fff", "pp") through the evaluator, as the check engine would. */
function checks(Monitor $monitor, string $pattern): void
{
    foreach (str_split($pattern) as $c) {
        $result = CheckResult::create(['monitor_id' => $monitor->id, 'checked_at' => now(), 'success' => $c === 'p', 'latency_ms' => $c === 'p' ? 5 : null]);
        app(StatusEvaluator::class)->apply($monitor, $result);
    }
}

function noc(UserRole $role = UserRole::Engineer): User
{
    $user = User::factory()->create(['role' => $role]);
    test()->actingAs($user);

    return $user;
}

function service(): IncidentService
{
    return app(IncidentService::class);
}

beforeEach(fn () => Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]));

// ---- opening and auto-resolving

it('opens one incident when a monitor goes Down, not one per failed check', function () {
    $monitor = Monitor::factory()->create();

    checks($monitor, 'pff');
    expect(Incident::count())->toBe(0); // flap protection: two failures is not an outage yet

    checks($monitor, 'fff');
    $incident = Incident::sole();
    expect($incident->state)->toBe(IncidentState::Detected)->and($incident->title)->toBe("{$monitor->name} is down")
        ->and($incident->events)->toHaveCount(1);
});

it('does not open an incident while the monitor is under maintenance, but does if it is still down afterwards', function () {
    $monitor = Monitor::factory()->create();
    $window = MaintenanceWindow::factory()->create(['monitor_id' => $monitor->id, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);

    checks($monitor, 'pfff');
    expect(Incident::count())->toBe(0);

    $window->update(['ends_at' => now()->subMinute()]);
    checks($monitor, 'f');
    expect(Incident::count())->toBe(1);
});

it('marks incidents on circuit monitors critical', function () {
    $circuit = Circuit::factory()->create();
    $monitor = Monitor::factory()->create(['monitorable_type' => Circuit::class, 'monitorable_id' => $circuit->id]);

    checks($monitor, 'pfff');

    expect(Incident::sole())->severity->value->toBe('critical')->circuit_id->toBe($circuit->id);
});

it('resolves automatically when the monitor recovers, and opens a fresh incident on the next outage', function () {
    $monitor = Monitor::factory()->create();

    checks($monitor, 'pfff');
    checks($monitor, 'pp'); // 2 passes to recover
    $first = Incident::sole();
    expect($first->state)->toBe(IncidentState::Resolved)->and($first->resolved_at)->not->toBeNull()
        ->and($first->events()->reorder('id', 'desc')->first()->note)->toContain('recovered');

    checks($monitor, 'fff');
    expect(Incident::count())->toBe(2)->and(Incident::latest('id')->first()->state)->toBe(IncidentState::Detected);
});

it('leaves an escalated incident for a person to move on when the monitor recovers', function () {
    $monitor = Monitor::factory()->create();
    checks($monitor, 'pfff');
    $incident = Incident::sole();
    foreach ([IncidentState::Acknowledged, IncidentState::Investigating, IncidentState::Escalated] as $to) {
        service()->transition($incident, $to);
    }

    checks($monitor, 'pp');

    expect($incident->refresh()->state)->toBe(IncidentState::Escalated);
});

it('explains why a monitor with incidents cannot be deleted, and deletes one without', function () {
    noc();
    $withHistory = Incident::factory()->create()->monitor;
    $clean = Monitor::factory()->create();

    $this->deleteJson("/api/monitors/{$withHistory->id}")->assertStatus(409)->assertJsonPath('message', fn ($m) => str_contains($m, '1 incident') && str_contains($m, 'Enabled'));
    $this->deleteJson("/api/monitors/{$clean->id}")->assertNoContent();
});

// ---- state machine

it('walks the full lifecycle and records MTTA and MTTR', function () {
    $this->travelTo(now()->startOfMinute());
    $user = noc();
    $incident = Incident::factory()->create(['opened_at' => now()]);

    $steps = [[IncidentState::Acknowledged, 120], [IncidentState::Investigating, 60], [IncidentState::Escalated, 60], [IncidentState::Monitoring, 300], [IncidentState::Resolved, 600]];
    foreach ($steps as [$to, $wait]) {
        $this->travel($wait)->seconds();
        $this->postJson("/api/incidents/{$incident->id}/transition", ['to' => $to->value, 'note' => "to {$to->value}"])->assertOk();
    }
    $this->putJson("/api/incidents/{$incident->id}", ['rfo_summary' => 'Fibre cut', 'rfo_root_cause' => 'Excavator', 'rfo_corrective_action' => 'Re-route'])->assertOk();
    $this->postJson("/api/incidents/{$incident->id}/transition", ['to' => 'closed'])->assertOk()->assertJsonPath('state', 'closed')
        ->assertJsonPath('time_to_acknowledge_s', 120)->assertJsonPath('time_to_resolve_s', 1140)->assertJsonPath('allowed_next', []);

    $events = $incident->events()->get();
    expect($events)->toHaveCount(6)->and($events->first()->user_id)->toBe($user->id)->and($events->first()->from_state)->toBe(IncidentState::Detected);
});

it('rejects illegal transitions', function (IncidentState $from, IncidentState $to) {
    noc();
    $incident = Incident::factory()->create(['state' => $from]);

    $this->postJson("/api/incidents/{$incident->id}/transition", ['to' => $to->value])->assertUnprocessable()->assertJsonValidationErrors('to');
    expect($incident->refresh()->state)->toBe($from)->and($incident->events)->toHaveCount(0);
})->with([
    'detected -> closed' => [IncidentState::Detected, IncidentState::Closed],
    'detected -> escalated' => [IncidentState::Detected, IncidentState::Escalated],
    'acknowledged -> detected' => [IncidentState::Acknowledged, IncidentState::Detected],
    'escalated -> resolved' => [IncidentState::Escalated, IncidentState::Resolved],
    'resolved -> acknowledged' => [IncidentState::Resolved, IncidentState::Acknowledged],
    'closed -> resolved' => [IncidentState::Closed, IncidentState::Resolved],
]);

it('will not close an incident without an RFO summary, and freezes it once closed', function () {
    noc();
    $incident = Incident::factory()->create(['state' => IncidentState::Resolved]);

    $this->postJson("/api/incidents/{$incident->id}/transition", ['to' => 'closed'])->assertUnprocessable();
    $this->putJson("/api/incidents/{$incident->id}", ['rfo_summary' => 'Done.'])->assertOk();
    $this->postJson("/api/incidents/{$incident->id}/transition", ['to' => 'closed'])->assertOk();
    $this->putJson("/api/incidents/{$incident->id}", ['rfo_summary' => 'Rewritten.'])->assertUnprocessable();
});

it('lets viewers read incidents but not change them', function () {
    noc(UserRole::Viewer);
    $incident = Incident::factory()->create();

    $this->getJson('/api/incidents')->assertOk()->assertJsonPath('data.0.id', $incident->id);
    $this->getJson("/api/incidents/{$incident->id}")->assertOk()->assertJsonPath('allowed_next', ['acknowledged', 'resolved']);
    $this->postJson("/api/incidents/{$incident->id}/transition", ['to' => 'acknowledged'])->assertForbidden();
    $this->putJson("/api/incidents/{$incident->id}", ['severity' => 'minor'])->assertForbidden();
});

it('filters open incidents and summarises MTTA and MTTR', function () {
    noc();
    Incident::factory()->create(['state' => IncidentState::Detected]);
    Incident::factory()->create(['state' => IncidentState::Resolved, 'opened_at' => now()->subHour(), 'acknowledged_at' => now()->subMinutes(58), 'resolved_at' => now()->subMinutes(50)]);

    $this->getJson('/api/incidents?state=open')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/incidents/summary')->assertOk()->assertJsonPath('open', 1)->assertJsonPath('last_30d', 2)
        ->assertJsonPath('mtta_s', 120)->assertJsonPath('mttr_s', 600);
});

// ---- alerts

it('sends a Telegram alert with an Acknowledge button on open, and a plain one on resolve', function () {
    config(['services.telegram.token' => 'SECRET-TOKEN']);
    $channel = AlertChannel::factory()->create(['target' => '4242']);
    AlertChannel::factory()->create(['enabled' => false]);
    $monitor = Monitor::factory()->create();

    checks($monitor, 'pfff');
    $incident = Incident::sole();

    Http::assertSentCount(1); // the disabled channel got nothing
    Http::assertSent(fn ($r) => str_contains($r->url(), '/botSECRET-TOKEN/sendMessage')
        && $r['chat_id'] === '4242'
        && str_contains($r['text'], "INCIDENT #{$incident->id}")
        && $r['reply_markup']['inline_keyboard'][0][0]['callback_data'] === "ack:{$incident->id}");

    checks($monitor, 'pp');
    Http::assertSent(fn ($r) => str_contains($r['text'], 'RESOLVED') && ! isset($r['reply_markup']) && str_contains($r['text'], 'Downtime'));
});

it('emails an alert to email channels', function () {
    Mail::fake();
    AlertChannel::factory()->email()->create(['target' => 'noc@example.test']);

    checks(Monitor::factory()->create(), 'pfff');

    Mail::assertSent(IncidentAlert::class, fn ($m) => $m->hasTo('noc@example.test') && str_contains($m->envelope()->subject, 'INCIDENT'));
});

it('sends no alert for a monitor in maintenance', function () {
    AlertChannel::factory()->create();
    $monitor = Monitor::factory()->create();
    MaintenanceWindow::factory()->create(['monitor_id' => $monitor->id, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);

    checks($monitor, 'pfff');

    Http::assertNothingSent();
});

it('never leaks the bot token when Telegram rejects a request', function () {
    config(['services.telegram.token' => 'SECRET-TOKEN']);
    Http::swap(new Factory);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 500)]);
    AlertChannel::factory()->create();
    $incident = Incident::factory()->create();

    try {
        (new SendAlert(AlertChannel::first()->id, $incident->id, 'opened'))->handle(app(App\Alerts\TelegramClient::class));
        $message = '';
    } catch (RuntimeException $e) {
        $message = $e->getMessage();
    }

    expect($message)->not->toBe('')->and($message)->not->toContain('SECRET-TOKEN');
});

// ---- Telegram acknowledge button

function press(string $data, string $chatId, array $headers = ['X-Telegram-Bot-Api-Secret-Token' => 'hook-secret'])
{
    return test()->postJson('/api/telegram/webhook', ['callback_query' => [
        'id' => 'cb1', 'data' => $data, 'from' => ['id' => 1, 'username' => 'ali'], 'message' => ['message_id' => 77, 'chat' => ['id' => (int) $chatId]],
    ]], $headers);
}

it('acknowledges from a Telegram button press by a configured chat', function () {
    config(['services.telegram.token' => 'T', 'services.telegram.webhook_secret' => 'hook-secret']);
    AlertChannel::factory()->create(['target' => '4242']);
    $incident = Incident::factory()->create();

    press("ack:{$incident->id}", '4242')->assertOk();

    expect($incident->refresh()->state)->toBe(IncidentState::Acknowledged)
        ->and($incident->events()->reorder('id', 'desc')->first()->note)->toContain('@ali')
        ->and($incident->events()->reorder('id', 'desc')->first()->user_id)->toBeNull();
    Http::assertSent(fn ($r) => str_contains($r->url(), 'answerCallbackQuery') && $r['text'] === 'Acknowledged.');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'editMessageReplyMarkup'));
});

it('ignores button presses from chats that are not configured channels', function () {
    config(['services.telegram.token' => 'T', 'services.telegram.webhook_secret' => 'hook-secret']);
    AlertChannel::factory()->create(['target' => '4242']);
    $incident = Incident::factory()->create();

    press("ack:{$incident->id}", '9999')->assertOk();

    expect($incident->refresh()->state)->toBe(IncidentState::Detected);
    Http::assertSent(fn ($r) => str_contains($r['text'] ?? '', 'not authorised'));
});

it('rejects webhook calls with a wrong or missing secret, and everything when no secret is configured', function () {
    config(['services.telegram.webhook_secret' => 'hook-secret']);
    $incident = Incident::factory()->create();
    AlertChannel::factory()->create(['target' => '4242']);

    press("ack:{$incident->id}", '4242', ['X-Telegram-Bot-Api-Secret-Token' => 'nope'])->assertForbidden();
    press("ack:{$incident->id}", '4242', [])->assertForbidden();
    config(['services.telegram.webhook_secret' => null]);
    press("ack:{$incident->id}", '4242', [])->assertForbidden();

    expect($incident->refresh()->state)->toBe(IncidentState::Detected);
});

it('reports "already" instead of failing when the incident has moved on', function () {
    config(['services.telegram.token' => 'T', 'services.telegram.webhook_secret' => 'hook-secret']);
    AlertChannel::factory()->create(['target' => '4242']);
    $incident = Incident::factory()->create(['state' => IncidentState::Resolved]);

    press("ack:{$incident->id}", '4242')->assertOk();

    Http::assertSent(fn ($r) => str_contains($r['text'] ?? '', 'already resolved'));
    expect($incident->refresh()->state)->toBe(IncidentState::Resolved);
});

// ---- channels

it('lets engineers see alert channels but only admins change or test them', function () {
    AlertChannel::factory()->create();

    noc(UserRole::Viewer);
    $this->getJson('/api/alert-channels')->assertForbidden();

    noc(UserRole::Engineer);
    $this->getJson('/api/alert-channels')->assertOk();
    $this->postJson('/api/alert-channels', ['name' => 'x', 'type' => 'telegram', 'target' => '123'])->assertForbidden();

    noc(UserRole::Admin);
    $this->postJson('/api/alert-channels', ['name' => 'NOC group', 'type' => 'telegram', 'target' => '-100123'])->assertCreated();
    $this->postJson('/api/alert-channels', ['name' => 'Bad', 'type' => 'telegram', 'target' => 'not a chat'])->assertUnprocessable()->assertJsonValidationErrors('target');
    $this->postJson('/api/alert-channels', ['name' => 'Bad', 'type' => 'email', 'target' => 'nope'])->assertUnprocessable();
});

it('sends a test message and reports a failure without exposing the token', function () {
    config(['services.telegram.token' => 'SECRET-TOKEN']);
    noc(UserRole::Admin);
    $channel = AlertChannel::factory()->create(['target' => '4242']);

    $this->postJson("/api/alert-channels/{$channel->id}/test")->assertOk();

    Http::swap(new Factory);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 400)]);
    $body = $this->postJson("/api/alert-channels/{$channel->id}/test")->assertStatus(502)->json('message');
    expect($body)->not->toContain('SECRET-TOKEN');
});

// ---- RFO

it('exports the RFO as a PDF once resolved, and refuses while the incident is open', function () {
    noc();
    $open = Incident::factory()->create();
    $done = Incident::factory()->create(['state' => IncidentState::Resolved, 'resolved_at' => now(), 'rfo_summary' => 'Upstream fibre cut.']);
    $done->events()->create(['from_state' => 'detected', 'to_state' => 'resolved', 'note' => 'Recovered', 'created_at' => now()]);

    $this->get("/api/incidents/{$open->id}/rfo")->assertStatus(409);
    $response = $this->get("/api/incidents/{$done->id}/rfo")->assertOk()->assertHeader('content-type', 'application/pdf');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
});
