<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AttributeType;
use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * The seafood category tree. Root categories carry a quality standard and
     * at least one required attribute; subcategories add their own.
     *
     * @var list<array<string, mixed>>
     */
    private const array TREE = [
        [
            'name' => 'Kepiting',
            'slug' => 'kepiting',
            'quality_standard' => "Kepiting hidup dan aktif bergerak.\n\n- Cangkang keras dan utuh\n- Kaki dan capit lengkap\n- Tidak berbau busuk",
            'attributes' => [
                ['name' => 'Berat per ekor', 'type' => AttributeType::Number, 'unit' => 'g', 'is_required' => true, 'is_filterable' => true],
                ['name' => 'Jenis kelamin', 'type' => AttributeType::Select, 'options' => ['Jantan', 'Betina'], 'is_filterable' => true],
            ],
            'children' => [
                ['name' => 'Bakau', 'slug' => 'bakau', 'attributes' => [
                    ['name' => 'Grade', 'type' => AttributeType::Select, 'options' => ['Super', 'A', 'B'], 'is_filterable' => true],
                ]],
                ['name' => 'Soka', 'slug' => 'soka'],
                ['name' => 'Rajungan', 'slug' => 'rajungan'],
            ],
        ],
        [
            'name' => 'Cumi',
            'slug' => 'cumi',
            'quality_standard' => "- Mata jernih dan menonjol\n- Kulit mengkilap, warna cerah\n- Daging kenyal, tidak berlendir berlebihan",
            'attributes' => [
                ['name' => 'Ukuran', 'type' => AttributeType::Select, 'options' => ['Kecil', 'Sedang', 'Besar'], 'is_required' => true, 'is_filterable' => true],
            ],
            'children' => [
                ['name' => 'Cumi Segar', 'slug' => 'cumi-segar'],
                ['name' => 'Cumi Beku', 'slug' => 'cumi-beku', 'attributes' => [
                    ['name' => 'Tanggal beku', 'type' => AttributeType::Date, 'is_required' => true],
                ]],
            ],
        ],
        [
            'name' => 'Tiram',
            'slug' => 'tiram',
            'quality_standard' => "- Cangkang tertutup rapat atau menutup saat disentuh\n- Berbau segar seperti air laut\n- Tidak ada cangkang retak",
            'attributes' => [
                ['name' => 'Isi per kg', 'type' => AttributeType::Number, 'unit' => 'ekor', 'is_required' => true],
            ],
        ],
        [
            'name' => 'Ikan',
            'slug' => 'ikan',
            'quality_standard' => "- Mata cerah dan menonjol\n- Insang merah segar\n- Sisik melekat kuat, daging kenyal",
            'attributes' => [
                ['name' => 'Jenis ikan', 'type' => AttributeType::Text, 'is_required' => true, 'is_filterable' => true],
                ['name' => 'Berat per ekor', 'type' => AttributeType::Number, 'unit' => 'g', 'is_required' => true],
            ],
        ],
    ];

    /**
     * Create or update the tree by slug. This is master data, so it also runs
     * in production. Safe to run more than once.
     */
    public function run(): void
    {
        foreach (self::TREE as $order => $node) {
            $this->seedNode($node, $order + 1);
        }
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function seedNode(array $node, int $sortOrder, ?Category $parent = null): void
    {
        $category = Category::query()->updateOrCreate(['slug' => $node['slug']], [
            'parent_id' => $parent?->id,
            'name' => $node['name'],
            'sort_order' => $sortOrder,
            'quality_standard' => $node['quality_standard'] ?? null,
        ]);

        foreach ($node['attributes'] ?? [] as $attribute) {
            $category->attributes()->updateOrCreate(['name' => $attribute['name']], [
                'type' => $attribute['type'],
                'options' => $attribute['options'] ?? null,
                'unit' => $attribute['unit'] ?? null,
                'is_required' => $attribute['is_required'] ?? false,
                'is_filterable' => $attribute['is_filterable'] ?? false,
            ]);
        }

        foreach ($node['children'] ?? [] as $order => $child) {
            $this->seedNode($child, $order + 1, $category);
        }
    }
}
