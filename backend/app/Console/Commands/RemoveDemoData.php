<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\RemoveDemoStores;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('demo:remove {--force : Remove without asking}')]
#[Description('Remove the demo stores ("Toko contoh") with their products, photos, and owners')]
class RemoveDemoData extends Command
{
    /**
     * Execute the console command. Asks first, since it cannot be undone.
     */
    public function handle(RemoveDemoStores $removeDemoStores): int
    {
        if (! $this->option('force') && ! $this->confirm('Hapus semua toko contoh beserta produk, foto, dan akun pemiliknya?')) {
            $this->info('Tidak ada yang dihapus.');

            return self::SUCCESS;
        }

        $removed = $removeDemoStores->handle();

        $this->info("Dihapus: {$removed['stores']} toko contoh, {$removed['products']} produk, {$removed['owners']} akun pemilik.");

        return self::SUCCESS;
    }
}
