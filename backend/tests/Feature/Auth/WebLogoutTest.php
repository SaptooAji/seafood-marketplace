<?php

declare(strict_types=1);

use App\Enums\PlatformRole;
use App\Models\User;
use Database\Seeders\RoleSeeder;

/*
 * Buyers sign out of the web (#268). The web, /seller, and /admin share the
 * `web` guard, so signing out ends all three; the Android API tokens stay.
 */

it('signs a buyer out and says so on the home page', function () {
    $this->actingAs(User::factory()->create(['name' => 'Budi Santoso']));

    $this->followingRedirects()
        ->post('/keluar')
        ->assertOk()
        ->assertSee('Anda sudah keluar.');

    $this->assertGuest();
});

it('names the route logout and sends the buyer home', function () {
    $this->actingAs(User::factory()->create());

    expect(route('logout'))->toEndWith('/keluar');
    $this->post(route('logout'))->assertRedirect('/');
});

it('starts a new session and a new CSRF token', function () {
    $this->actingAs(User::factory()->create());
    $this->get('/');
    $sessionId = session()->getId();
    $token = session()->token();

    $this->post('/keluar');

    expect(session()->getId())->not->toBe($sessionId)
        ->and(session()->token())->not->toBe($token);
});

it('lets a guest sign out without an error', function () {
    $this->post('/keluar')->assertRedirect('/');

    $this->assertGuest();
});

it('refuses to sign out on a GET, so a link elsewhere cannot do it', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get('/keluar')->assertMethodNotAllowed();

    $this->assertAuthenticatedAs($user);
});

describe('the panels share the session', function () {
    it('sends a signed-out admin to the panel login instead of a 403', function () {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(PlatformRole::Admin);
        $this->actingAs($admin);

        $this->post('/keluar');

        $this->get('/admin')->assertRedirect('/admin/login');
    });

    it('lets a buyer who was refused by /admin sign out and reach its login', function () {
        $this->actingAs(User::factory()->create());
        $this->get('/admin')->assertForbidden();

        $this->post('/keluar');

        $this->get('/admin')->assertRedirect('/admin/login');
    });
});

describe('security', function () {
    it('keeps the Android API tokens', function () {
        $user = User::factory()->create();
        $user->createToken('Pixel 9');
        $this->actingAs($user);

        $this->post('/keluar');

        expect($user->tokens()->count())->toBe(1);
    });

    it('changes the remember token, so the remember-me cookie stops working', function () {
        $user = User::factory()->create(['remember_token' => 'token-lama-1234567890']);
        $this->actingAs($user);

        $this->post('/keluar');

        expect($user->fresh()->remember_token)->not->toBe('token-lama-1234567890');
    });
});

describe('the header', function () {
    it('shows a signed-in buyer their first name and Keluar, not Masuk', function () {
        $this->actingAs(User::factory()->create(['name' => 'Budi Santoso']));

        $this->get('/')
            ->assertOk()
            ->assertSee('Budi')
            ->assertSee('action="'.route('logout').'"', escape: false)
            ->assertSee('Keluar')
            ->assertDontSee('href="'.route('login').'"', escape: false);
    });

    it('shows a guest Masuk and no Keluar', function () {
        $this->get('/')
            ->assertOk()
            ->assertSee('href="'.route('login').'"', escape: false)
            ->assertDontSee('action="'.route('logout').'"', escape: false);
    });

    it('escapes a name with markup', function () {
        $this->actingAs(User::factory()->create(['name' => '<script>alert(1)</script> Budi']));

        $this->get('/')
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', escape: false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', escape: false);
    });
});

it('shows a failed Google sign-in message on the home page', function () {
    $this->withSession(['error' => 'Login dengan Google dibatalkan atau sesinya kedaluwarsa. Silakan coba lagi.'])
        ->get('/')
        ->assertSee('Login dengan Google dibatalkan atau sesinya kedaluwarsa. Silakan coba lagi.');
});
