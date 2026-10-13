<?php

declare(strict_types=1);

use App\Enums\AttributeType;
use App\Enums\StoreRole;
use App\Exceptions\ProductDescriptionUnavailable;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

const SUGGESTION_URL = '/api/v1/seller/products/description-suggestion';
const SUGGESTION_GEMINI_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-test-flash:generateContent';
const SUGGESTION_GEMINI_KEY = 'AIzaFAKE-api-test-key-456';

/*
 * Kepiting (Berat per ekor: number, unit g)
 *   └─ Bakau (Jenis kelamin: select Jantan/Betina)
 */
beforeEach(function () {
    Http::preventStrayRequests();

    config([
        'services.gemini.key' => SUGGESTION_GEMINI_KEY,
        'services.gemini.model' => 'gemini-test-flash',
        'services.gemini.fallback_models' => [],
        'services.gemini.daily_limit' => 500,
    ]);

    // Mid-morning, so requests a minute apart stay on the same day.
    $this->travelTo(today()->setTime(10, 0));

    $this->kepiting = Category::factory()->create(['name' => 'Kepiting']);
    $this->bakau = Category::factory()->childOf($this->kepiting)->create(['name' => 'Bakau']);
    $this->berat = CategoryAttribute::factory()->for($this->kepiting)->create([
        'name' => 'Berat per ekor', 'type' => AttributeType::Number, 'unit' => 'g',
    ]);
    $this->kelamin = CategoryAttribute::factory()->for($this->bakau)->create([
        'name' => 'Jenis kelamin', 'type' => AttributeType::Select, 'options' => ['Jantan', 'Betina'],
    ]);
});

/**
 * Sign in a new member of the given store, or of a new active store.
 */
function suggestionSeller(StoreRole $role = StoreRole::Owner, ?Store $store = null): Store
{
    $user = User::factory()->create();
    $store ??= Store::factory()->active()->create();
    $store->users()->attach($user, ['role' => $role->value]);
    Sanctum::actingAs($user);

    return $store;
}

/**
 * The product form as it is before a description is written, unless overridden.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function suggestionPayload(array $overrides = []): array
{
    $test = test();

    return array_merge([
        'category_id' => $test->bakau->id,
        'name' => 'Kepiting Bakau Super',
        'condition' => 'live',
        'sales_mode' => 'ready_stock',
        'shelf_life_days' => 2,
        'attributes' => [
            $test->berat->id => '350',
            $test->kelamin->id => 'Jantan',
        ],
    ], $overrides);
}

function fakeSuggestionGemini(mixed $response = null): void
{
    Http::fake([SUGGESTION_GEMINI_URL => $response ?? Http::response([
        'candidates' => [[
            'content' => ['role' => 'model', 'parts' => [['text' => json_encode([
                'description' => "Kepiting bakau jantan hidup, sekitar 350 g per ekor.\n\nSiap dikirim hari ini.",
                'seo_description' => 'Kepiting bakau jantan hidup 350 g per ekor.',
            ])]]],
            'finishReason' => 'STOP',
        ]],
    ])]);
}

/**
 * Send the request the given number of times, a minute apart so the
 * per-minute limit stays out of the way.
 */
function postSuggestionsSpaced(int $times): void
{
    for ($i = 0; $i < $times; $i++) {
        test()->postJson(SUGGESTION_URL, suggestionPayload())->assertOk();
        test()->travel(1)->minute();
    }
}

describe('access', function () {
    it('requires a token', function () {
        $this->postJson(SUGGESTION_URL, suggestionPayload())->assertUnauthorized();
    });

    it('returns 404 for a user without a store', function () {
        fakeSuggestionGemini();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(SUGGESTION_URL, suggestionPayload())->assertNotFound();

        Http::assertNothingSent();
    });

    it('lets owners and staff ask', function (StoreRole $role) {
        fakeSuggestionGemini();
        suggestionSeller($role);

        $this->postJson(SUGGESTION_URL, suggestionPayload())->assertOk();
    })->with([
        'owner' => [StoreRole::Owner],
        'staff' => [StoreRole::Staff],
    ]);

    it('lets a store waiting for verification ask', function () {
        fakeSuggestionGemini();
        suggestionSeller(store: Store::factory()->pending()->create());

        $this->postJson(SUGGESTION_URL, suggestionPayload())->assertOk();
    });

    it('rejects a suspended store without calling Gemini', function () {
        fakeSuggestionGemini();
        suggestionSeller(store: Store::factory()->suspended()->create());

        $this->postJson(SUGGESTION_URL, suggestionPayload())->assertForbidden();

        Http::assertNothingSent();
    });
});

describe('suggestion', function () {
    it('returns the description and SEO summary and saves nothing', function () {
        fakeSuggestionGemini();
        suggestionSeller();

        $this->postJson(SUGGESTION_URL, suggestionPayload())
            ->assertOk()
            ->assertExactJson(['data' => [
                'description' => "Kepiting bakau jantan hidup, sekitar 350 g per ekor.\n\nSiap dikirim hari ini.",
                'seo_description' => 'Kepiting bakau jantan hidup 350 g per ekor.',
            ]]);

        expect(Product::query()->count())->toBe(0);
    });

    it('needs no description, SEO summary, or variants', function () {
        fakeSuggestionGemini();
        suggestionSeller();

        $this->postJson(SUGGESTION_URL, [
            'category_id' => $this->bakau->id,
            'name' => 'Kepiting Bakau Super',
            'condition' => 'live',
            'sales_mode' => 'ready_stock',
        ])->assertOk();
    });

    it('describes the signed-in seller’s store, whatever store the body names', function () {
        fakeSuggestionGemini();
        $store = suggestionSeller();
        $other = Store::factory()->active()->create(['name' => 'Toko Lain']);

        $this->postJson(SUGGESTION_URL, suggestionPayload(['store_id' => $other->id]))->assertOk();

        Http::assertSent(function (Request $request) use ($store) {
            $facts = json_decode($request['contents'][0]['parts'][0]['text'], true);

            return $facts['toko']['nama'] === $store->name;
        });
    });

    it('rejects invalid input without calling Gemini', function (Closure $overrides, Closure $field) {
        fakeSuggestionGemini();
        suggestionSeller();

        $this->postJson(SUGGESTION_URL, suggestionPayload($overrides()))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field());

        Http::assertNothingSent();
    })->with([
        'no name' => [fn () => ['name' => null], fn () => 'name'],
        'blank name' => [fn () => ['name' => '   '], fn () => 'name'],
        'no category' => [fn () => ['category_id' => null], fn () => 'category_id'],
        'unknown category' => [fn () => ['category_id' => 999_999, 'attributes' => []], fn () => 'category_id'],
        'no condition' => [fn () => ['condition' => null], fn () => 'condition'],
        'unknown sales mode' => [fn () => ['sales_mode' => 'auction'], fn () => 'sales_mode'],
        'name too long' => [fn () => ['name' => str_repeat('a', 256)], fn () => 'name'],
        'description too long' => [fn () => ['description' => str_repeat('a', 5001)], fn () => 'description'],
        'SEO summary too long' => [fn () => ['seo_description' => str_repeat('a', 161)], fn () => 'seo_description'],
        'storage instructions too long' => [fn () => ['storage_instructions' => str_repeat('a', 2001)], fn () => 'storage_instructions'],
        'attribute too long' => [fn () => ['attributes' => [test()->berat->id => str_repeat('1', 256)]], fn () => 'attributes.'.test()->berat->id],
    ]);
});

describe('unavailable', function () {
    it('answers 503 with a general message when Gemini fails', function (Closure $response) {
        config(['app.debug' => false]);
        fakeSuggestionGemini($response());
        suggestionSeller();

        $this->postJson(SUGGESTION_URL, suggestionPayload())
            ->assertServiceUnavailable()
            ->assertExactJson(['message' => (new ProductDescriptionUnavailable)->getMessage()])
            ->assertDontSee(SUGGESTION_GEMINI_KEY);
    })->with([
        'error' => [fn () => Http::response(['error' => ['message' => 'API key '.SUGGESTION_GEMINI_KEY.' not valid']], 400)],
        'timeout' => [fn () => Http::failedConnection('cURL error 28: Operation timed out')],
    ]);

    it('answers 503 without calling Gemini when no API key is set', function () {
        config(['services.gemini.key' => '']);
        fakeSuggestionGemini();
        suggestionSeller();

        $this->postJson(SUGGESTION_URL, suggestionPayload())->assertServiceUnavailable();

        Http::assertNothingSent();
    });
});

describe('limits', function () {
    it('answers 429 to the 21st request of the day from a store, and other stores can still ask', function () {
        fakeSuggestionGemini();
        $store = suggestionSeller();
        postSuggestionsSpaced(10);
        suggestionSeller(StoreRole::Staff, $store);
        postSuggestionsSpaced(10);

        $this->postJson(SUGGESTION_URL, suggestionPayload())->assertTooManyRequests()->assertHeader('Retry-After');

        suggestionSeller();
        $this->postJson(SUGGESTION_URL, suggestionPayload())->assertOk();
    });

    it('answers 429 to the 6th request of the day from a store waiting for verification', function () {
        fakeSuggestionGemini();
        suggestionSeller(store: Store::factory()->pending()->create());
        postSuggestionsSpaced(5);

        $this->postJson(SUGGESTION_URL, suggestionPayload())->assertTooManyRequests();
    });

    it('answers 429 to the 4th request in a minute from the same user', function () {
        fakeSuggestionGemini();
        suggestionSeller();

        $this->postJson(SUGGESTION_URL, suggestionPayload())->assertOk();
        $this->postJson(SUGGESTION_URL, suggestionPayload())->assertOk();
        $this->postJson(SUGGESTION_URL, suggestionPayload())->assertOk();

        $this->postJson(SUGGESTION_URL, suggestionPayload())
            ->assertTooManyRequests()
            ->assertJsonPath('message', fn (string $message) => str_starts_with($message, 'Terlalu sering'))
            ->assertHeader('Retry-After');

        Http::assertSentCount(3);
    });

    it('answers 429 to every store once the global daily limit is reached', function () {
        config(['services.gemini.daily_limit' => 2]);
        fakeSuggestionGemini();
        suggestionSeller();
        postSuggestionsSpaced(2);

        suggestionSeller();
        $this->postJson(SUGGESTION_URL, suggestionPayload())->assertTooManyRequests();

        Http::assertSentCount(2);
    });

    it('counts a request that Gemini failed', function () {
        config(['services.gemini.daily_limit' => 1]);
        fakeSuggestionGemini(Http::response(['error' => ['code' => 500]], 500));
        suggestionSeller();

        $this->postJson(SUGGESTION_URL, suggestionPayload())->assertServiceUnavailable();
        $this->travel(1)->minute();
        $this->postJson(SUGGESTION_URL, suggestionPayload())->assertTooManyRequests();
    });

    it('does not count a request rejected by validation', function () {
        fakeSuggestionGemini();
        suggestionSeller();

        $this->postJson(SUGGESTION_URL, suggestionPayload(['name' => null]))->assertUnprocessable();
        $this->postJson(SUGGESTION_URL, suggestionPayload(['name' => null]))->assertUnprocessable();
        $this->postJson(SUGGESTION_URL, suggestionPayload(['name' => null]))->assertUnprocessable();

        $this->postJson(SUGGESTION_URL, suggestionPayload())->assertOk();
    });
});
