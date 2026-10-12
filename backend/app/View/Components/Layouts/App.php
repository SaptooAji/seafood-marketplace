<?php

declare(strict_types=1);

namespace App\View\Components\Layouts;

use App\Models\Category;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\Component;

/**
 * The buyer web layout: header with search and root categories, footer, and
 * the SEO tags every public page needs. Pages pass their own title and
 * description and push extra tags (JSON-LD) to the `head` stack.
 */
class App extends Component
{
    public const string DEFAULT_DESCRIPTION = 'Belanja hasil laut segar langsung dari penjual: kepiting, cumi, tiram, dan ikan, dengan stok yang dikonfirmasi setiap hari.';

    /**
     * @param  string|null  $title  the page title; the home page leaves it out
     * @param  string|null  $description  for the meta and Open Graph descriptions
     */
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
    ) {}

    /**
     * `{page} | {app}`, or only the app name on the home page.
     */
    public function fullTitle(): string
    {
        $appName = config()->string('app.name');

        return filled($this->title) ? $this->title.' | '.$appName : $appName;
    }

    public function metaDescription(): string
    {
        return filled($this->description) ? (string) $this->description : self::DEFAULT_DESCRIPTION;
    }

    /**
     * The current URL without its query, so tracking or search parameters
     * are never reflected into the head.
     */
    public function canonicalUrl(): string
    {
        return url()->current();
    }

    /**
     * Root categories for the header, in their admin order.
     *
     * @return Collection<int, Category>
     */
    public function rootCategories(): Collection
    {
        return Category::query()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }

    public function render(): View
    {
        return view('components.layouts.app');
    }
}
