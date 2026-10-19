<?php

declare(strict_types=1);

use App\Actions\CreateStore;
use App\Enums\PlatformRole;
use App\Enums\StoreRole;
use App\Events\StoreOpened;
use App\Filament\Admin\Resources\Stores\StoreResource;
use App\Models\Store;
use App\Models\User;
use App\Notifications\StoreAwaitingReview;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

/*
 * Admins learn that a new store waits for review (#264): a bell notification
 * in /admin and an email. Only platform roles get it, and it carries the
 * store's name, city, and a link, never the bank account, phone, or address
 * (DATA-2).
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function adminWithRole(PlatformRole $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function openStoreThroughApi(User $seller, array $overrides = []): void
{
    Sanctum::actingAs($seller);

    test()->postJson('/api/v1/seller/store', array_merge([
        'name' => 'Kepiting Pak Budi',
        'phone' => '081234567890',
        'address' => 'Jl. Pelabuhan No. 5, Muara Angke',
        'city' => 'Jakarta Utara',
        'lat' => -6.2087634,
        'lng' => 106.845599,
        'bank_name' => 'BCA',
        'bank_account_no' => '1234567890',
        'bank_account_name' => 'Budi Santoso',
    ], $overrides));
}

function viewStoreUrl(Store $store): string
{
    return StoreResource::getUrl('view', ['record' => $store], panel: 'admin');
}

describe('who is told', function () {
    it('tells every admin and super admin when a store is opened', function () {
        Notification::fake();
        $admin = adminWithRole(PlatformRole::Admin);
        $superAdmin = adminWithRole(PlatformRole::SuperAdmin);

        openStoreThroughApi(User::factory()->create());

        $store = Store::query()->sole();
        foreach ([$admin, $superAdmin] as $recipient) {
            Notification::assertSentTo(
                $recipient,
                StoreAwaitingReview::class,
                fn (StoreAwaitingReview $notification, array $channels) => $notification->store->is($store)
                    && $channels === ['database', 'mail'],
            );
        }
    });

    it('tells each admin once, however many there are', function () {
        Notification::fake();
        $admins = collect([
            adminWithRole(PlatformRole::Admin),
            adminWithRole(PlatformRole::Admin),
            adminWithRole(PlatformRole::SuperAdmin),
        ]);

        openStoreThroughApi(User::factory()->create());

        Notification::assertSentTimes(StoreAwaitingReview::class, 3);
        $admins->each(fn (User $admin) => Notification::assertSentToTimes($admin, StoreAwaitingReview::class, 1));
    });

    it('does not tell the seller, staff of other stores, or buyers', function () {
        Notification::fake();
        adminWithRole(PlatformRole::Admin);
        $seller = User::factory()->create();
        $staff = User::factory()->create();
        Store::factory()->active()->create()->users()->attach($staff, ['role' => StoreRole::Staff->value]);
        $buyer = User::factory()->create();

        openStoreThroughApi($seller);

        Notification::assertNotSentTo([$seller, $staff, $buyer], StoreAwaitingReview::class);
    });

    it('opens the store without error when there is no admin yet', function () {
        Notification::fake();

        openStoreThroughApi(User::factory()->create());

        expect(Store::query()->count())->toBe(1);
        Notification::assertNothingSent();
    });

    it('opens the store when the platform roles are not seeded', function () {
        Notification::fake();
        Role::query()->delete();

        openStoreThroughApi(User::factory()->create());

        test()->assertDatabaseCount('stores', 1);
        Notification::assertNothingSent();
    });
});

describe('when nobody is told', function () {
    it('sends nothing when the store is refused', function () {
        Notification::fake();
        adminWithRole(PlatformRole::Admin);

        openStoreThroughApi(User::factory()->create(), ['bank_account_no' => '12ab']);

        Notification::assertNothingSent();
    });

    it('sends nothing when the seller already owns a store', function () {
        Notification::fake();
        adminWithRole(PlatformRole::Admin);
        $seller = User::factory()->create();
        Store::factory()->create()->users()->attach($seller, ['role' => StoreRole::Owner->value]);

        openStoreThroughApi($seller);

        Notification::assertNothingSent();
    });

    it('sends nothing when the store is rolled back', function () {
        Notification::fake();
        adminWithRole(PlatformRole::Admin);

        try {
            DB::transaction(function () {
                app(CreateStore::class)->handle(User::factory()->create(), storeData());

                throw new RuntimeException('The surrounding work failed.');
            });
        } catch (RuntimeException) {
            // Expected: the store and its event go with the rollback.
        }

        expect(Store::query()->count())->toBe(0);
        Notification::assertNothingSent();
    });

    it('announces a store once it is saved', function () {
        Event::fake([StoreOpened::class]);

        $store = app(CreateStore::class)->handle(User::factory()->create(), storeData());

        Event::assertDispatchedTimes(StoreOpened::class, 1);
        Event::assertDispatched(StoreOpened::class, fn (StoreOpened $event) => $event->store->is($store));
    });
});

describe('what it says', function () {
    beforeEach(function () {
        $this->store = Store::factory()->create([
            'name' => 'Kepiting Pak Budi',
            'city' => 'Jakarta Utara',
            'phone' => '081234567890',
            'address' => 'Jl. Pelabuhan No. 5, Muara Angke',
            'bank_account_no' => '1234567890',
        ]);
        $this->admin = adminWithRole(PlatformRole::Admin);
    });

    it('is queued, so opening a store never waits for mail', function () {
        expect(new StoreAwaitingReview($this->store))->toBeInstanceOf(ShouldQueue::class);
    });

    it('shows in the admin bell with the store and a link to it', function () {
        $data = (new StoreAwaitingReview($this->store))->toDatabase($this->admin);

        expect($data)
            ->format->toBe('filament')
            ->title->toBe('Toko baru menunggu review')
            ->and($data['body'])->toContain('Kepiting Pak Budi')->toContain('Jakarta Utara')
            ->and(collect($data['actions'])->pluck('url'))->toContain(viewStoreUrl($this->store));
    });

    it('emails the store name and a button to it', function () {
        $mail = (new StoreAwaitingReview($this->store))->toMail($this->admin);

        expect($mail->subject)->toBe('Toko baru menunggu review: Kepiting Pak Budi')
            ->and($mail->actionUrl)->toBe(viewStoreUrl($this->store))
            ->and((string) $mail->render())->toContain('Jakarta Utara');
    });

    it('carries no bank account, phone, or address', function () {
        $notification = new StoreAwaitingReview($this->store);
        $shown = json_encode($notification->toDatabase($this->admin)).(string) $notification->toMail($this->admin)->render();

        expect($shown)
            ->not->toContain('1234567890')
            ->not->toContain('081234567890')
            ->not->toContain('Jl. Pelabuhan');
    });

    it('escapes a store name with markup in the email', function () {
        $this->store->update(['name' => '<script>alert(1)</script> Kepiting']);

        $html = (string) (new StoreAwaitingReview($this->store))->toMail($this->admin)->render();

        expect($html)->not->toContain('<script>alert(1)</script>')
            ->toContain('&lt;script&gt;');
    });
});

describe('the admin bell', function () {
    it('is on in /admin and off in /seller', function () {
        expect(Filament::getPanel('admin')->hasDatabaseNotifications())->toBeTrue()
            ->and(Filament::getPanel('seller')->hasDatabaseNotifications())->toBeFalse();
    });

    it('keeps each admin\'s notifications separate', function () {
        $first = adminWithRole(PlatformRole::Admin);
        $second = adminWithRole(PlatformRole::SuperAdmin);

        openStoreThroughApi(User::factory()->create());
        $first->unreadNotifications()->sole()->markAsRead();

        expect($first->unreadNotifications()->count())->toBe(0)
            ->and($second->unreadNotifications()->count())->toBe(1);
    });

    it('links to a page that refuses users without a platform role', function () {
        $store = Store::factory()->create();
        $owner = User::factory()->create();
        $store->users()->attach($owner, ['role' => StoreRole::Owner->value]);

        $this->actingAs($owner)->get(viewStoreUrl($store))->assertForbidden();
    });
});
