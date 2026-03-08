<?php

/**
 * Contact model: one row per contact pair.
 * When user A sends a request to user B and B accepts, there is a single row:
 * sender_id = A, receiver_id = B, status = accepted.
 * No reverse row (B, A) is stored; the same row represents the contact for both users.
 */

use App\Enums\ContactStatusEnum;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

function asUser(User $user): void
{
    Sanctum::actingAs($user);
}

test('index returns accepted contacts for authenticated user', function (): void {
    $contactA = User::factory()->create();
    $contactB = User::factory()->create();
    // One row per pair: (user sent to contactA, accepted)
    Contact::create([
        'sender_id' => $this->user->id,
        'receiver_id' => $contactA->id,
        'status' => ContactStatusEnum::ACCEPTED,
        'accepted_at' => now(),
    ]);
    // One row per pair: (contactB sent to user, accepted)
    Contact::create([
        'sender_id' => $contactB->id,
        'receiver_id' => $this->user->id,
        'status' => ContactStatusEnum::ACCEPTED,
        'accepted_at' => now(),
    ]);

    asUser($this->user);
    $response = $this->getJson('/api/contacts');

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Contacts retrieved successfully')
        ->assertJsonPath('success', true);
    expect($response->json('data'))->toHaveCount(2);
});

test('index returns empty list when user has no contacts', function (): void {
    asUser($this->user);
    $response = $this->getJson('/api/contacts');

    $response->assertSuccessful()
        ->assertJsonPath('data', []);
});

test('index requires authentication', function (): void {
    $this->getJson('/api/contacts')->assertUnauthorized();
});

test('show returns contact and chat for authenticated user', function (): void {
    $contact = User::factory()->create();
    // One row: user sent to contact, accepted
    Contact::create([
        'sender_id' => $this->user->id,
        'receiver_id' => $contact->id,
        'status' => ContactStatusEnum::ACCEPTED,
        'accepted_at' => now(),
    ]);

    asUser($this->user);
    $response = $this->getJson('/api/contacts/'.$contact->id);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Contact retrieved successfully')
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.contact.id', $contact->id);
});

test('show requires authentication', function (): void {
    $contact = User::factory()->create();
    $this->getJson('/api/contacts/'.$contact->id)->assertUnauthorized();
});

test('pending requests returns received pending contact requests', function (): void {
    $sender = User::factory()->create();
    // One row: sender sent to user, pending
    Contact::create([
        'sender_id' => $sender->id,
        'receiver_id' => $this->user->id,
        'status' => ContactStatusEnum::PENDING,
    ]);

    asUser($this->user);
    $response = $this->getJson('/api/contacts/requests');

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Pending requests retrieved');
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($sender->id);
});

test('pending requests returns empty when none', function (): void {
    asUser($this->user);
    $response = $this->getJson('/api/contacts/requests');

    $response->assertSuccessful()
        ->assertJsonPath('data', []);
});

test('pending requests requires authentication', function (): void {
    $this->getJson('/api/contacts/requests')->assertUnauthorized();
});

test('sent requests returns sent pending contact requests', function (): void {
    $receiver = User::factory()->create();
    // One row: user sent to receiver, pending
    Contact::create([
        'sender_id' => $this->user->id,
        'receiver_id' => $receiver->id,
        'status' => ContactStatusEnum::PENDING,
    ]);

    asUser($this->user);
    $response = $this->getJson('/api/contacts/sent');

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Sent requests retrieved');
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($receiver->id);
});

test('sent requests requires authentication', function (): void {
    $this->getJson('/api/contacts/sent')->assertUnauthorized();
});

test('send request creates pending contact and returns 201', function (): void {
    $receiver = User::factory()->create();

    asUser($this->user);
    $response = $this->postJson('/api/contacts/request', ['receiver_id' => $receiver->id]);

    $response->assertSuccessful()
        ->assertStatus(201)
        ->assertJsonPath('message', 'Contact request sent successfully');
    // One row for the pair: sender = user, receiver = receiver, pending
    Contact::query()
        ->where('sender_id', $this->user->id)
        ->where('receiver_id', $receiver->id)
        ->where('status', ContactStatusEnum::PENDING)
        ->firstOrFail();
    expect(Contact::query()->where('sender_id', $this->user->id)->where('receiver_id', $receiver->id)->count())->toBe(1);
});

test('send request rejects when adding self', function (): void {
    asUser($this->user);
    $response = $this->postJson('/api/contacts/request', ['receiver_id' => $this->user->id]);

    $response->assertStatus(400)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'You cannot add yourself as a contact');
});

test('send request rejects when already in contacts', function (): void {
    $receiver = User::factory()->create();
    // One row: user sent to receiver, accepted (same row represents contact for both)
    Contact::create([
        'sender_id' => $this->user->id,
        'receiver_id' => $receiver->id,
        'status' => ContactStatusEnum::ACCEPTED,
        'accepted_at' => now(),
    ]);

    asUser($this->user);
    $response = $this->postJson('/api/contacts/request', ['receiver_id' => $receiver->id]);

    $response->assertStatus(400)
        ->assertJsonPath('message', 'Already in contacts');
});

test('send request rejects when request already sent', function (): void {
    $receiver = User::factory()->create();
    // One row: user already sent to receiver, still pending
    Contact::create([
        'sender_id' => $this->user->id,
        'receiver_id' => $receiver->id,
        'status' => ContactStatusEnum::PENDING,
    ]);

    asUser($this->user);
    $response = $this->postJson('/api/contacts/request', ['receiver_id' => $receiver->id]);

    $response->assertStatus(400)
        ->assertJsonPath('message', 'Request already sent');
});

test('send request accepts when receiver had already sent request', function (): void {
    $receiver = User::factory()->create();
    // One row: receiver sent to user, pending (user accepts by "sending" back = accept)
    Contact::create([
        'sender_id' => $receiver->id,
        'receiver_id' => $this->user->id,
        'status' => ContactStatusEnum::PENDING,
    ]);

    asUser($this->user);
    $response = $this->postJson('/api/contacts/request', ['receiver_id' => $receiver->id]);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Contact request accepted');
    // Same single row updated to accepted; no second row created
    $contact = Contact::query()
        ->where('sender_id', $receiver->id)
        ->where('receiver_id', $this->user->id)
        ->firstOrFail();
    expect($contact->status)->toBe(ContactStatusEnum::ACCEPTED);
    expect(Contact::query()->whereIn('sender_id', [$this->user->id, $receiver->id])->whereIn('receiver_id', [$this->user->id, $receiver->id])->count())->toBe(1);
});

test('send request validates receiver_id required', function (): void {
    asUser($this->user);
    $response = $this->postJson('/api/contacts/request', []);

    $response->assertUnprocessable()
        ->assertJsonPath('error_code', 422);
});

test('send request validates receiver_id exists', function (): void {
    asUser($this->user);
    $response = $this->postJson('/api/contacts/request', ['receiver_id' => 99999]);

    $response->assertUnprocessable()
        ->assertJsonPath('error_code', 422);
});

test('send request requires authentication', function (): void {
    $receiver = User::factory()->create();
    $this->postJson('/api/contacts/request', ['receiver_id' => $receiver->id])
        ->assertUnauthorized();
});

test('accept request updates contact to accepted', function (): void {
    $sender = User::factory()->create();
    // One row: sender sent to user, pending; user accepts
    Contact::create([
        'sender_id' => $sender->id,
        'receiver_id' => $this->user->id,
        'status' => ContactStatusEnum::PENDING,
    ]);

    asUser($this->user);
    $response = $this->postJson('/api/contacts/accept/'.$sender->id);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Contact request accepted');
    $contact = Contact::query()
        ->where('sender_id', $sender->id)
        ->where('receiver_id', $this->user->id)
        ->firstOrFail();
    expect($contact->status)->toBe(ContactStatusEnum::ACCEPTED);
    expect($contact->accepted_at)->not->toBeNull();
});

test('accept request returns 404 when no pending request', function (): void {
    $other = User::factory()->create();

    asUser($this->user);
    $response = $this->postJson('/api/contacts/accept/'.$other->id);

    $response->assertNotFound();
});

test('accept request requires authentication', function (): void {
    $sender = User::factory()->create();
    $this->postJson('/api/contacts/accept/'.$sender->id)->assertUnauthorized();
});

test('reject request updates contact to cancelled', function (): void {
    $sender = User::factory()->create();
    // One row: sender sent to user, pending; user rejects
    Contact::create([
        'sender_id' => $sender->id,
        'receiver_id' => $this->user->id,
        'status' => ContactStatusEnum::PENDING,
    ]);

    asUser($this->user);
    $response = $this->postJson('/api/contacts/reject/'.$sender->id);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Contact request rejected');
    $contact = Contact::query()
        ->where('sender_id', $sender->id)
        ->where('receiver_id', $this->user->id)
        ->firstOrFail();
    expect($contact->status)->toBe(ContactStatusEnum::CANCELLED);
});

test('reject request returns 404 when no pending request', function (): void {
    $other = User::factory()->create();

    asUser($this->user);
    $response = $this->postJson('/api/contacts/reject/'.$other->id);

    $response->assertNotFound();
});

test('reject request requires authentication', function (): void {
    $sender = User::factory()->create();
    $this->postJson('/api/contacts/reject/'.$sender->id)->assertUnauthorized();
});

test('remove contact updates contact to removed', function (): void {
    $contactUser = User::factory()->create();
    // One row: user sent to contactUser, accepted; user removes contact
    Contact::create([
        'sender_id' => $this->user->id,
        'receiver_id' => $contactUser->id,
        'status' => ContactStatusEnum::ACCEPTED,
        'accepted_at' => now(),
    ]);

    asUser($this->user);
    $response = $this->deleteJson('/api/contacts/'.$contactUser->id);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Contact removed');
    $contact = Contact::query()
        ->where('sender_id', $this->user->id)
        ->where('receiver_id', $contactUser->id)
        ->firstOrFail();
    expect($contact->status)->toBe(ContactStatusEnum::REMOVED);
});

test('remove contact works when current user was receiver', function (): void {
    $sender = User::factory()->create();
    // One row: sender sent to user, accepted; user (receiver) removes contact
    Contact::create([
        'sender_id' => $sender->id,
        'receiver_id' => $this->user->id,
        'status' => ContactStatusEnum::ACCEPTED,
        'accepted_at' => now(),
    ]);

    asUser($this->user);
    $response = $this->deleteJson('/api/contacts/'.$sender->id);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Contact removed');
    $contact = Contact::query()
        ->where('sender_id', $sender->id)
        ->where('receiver_id', $this->user->id)
        ->firstOrFail();
    expect($contact->status)->toBe(ContactStatusEnum::REMOVED);
});

test('remove contact returns 404 when no accepted contact', function (): void {
    $other = User::factory()->create();

    asUser($this->user);
    $response = $this->deleteJson('/api/contacts/'.$other->id);

    $response->assertNotFound();
});

test('remove contact requires authentication', function (): void {
    $contactUser = User::factory()->create();
    $this->deleteJson('/api/contacts/'.$contactUser->id)->assertUnauthorized();
});
