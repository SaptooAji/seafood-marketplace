<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Stores\Pages;

use App\Filament\Admin\Resources\Stores\Actions\StoreStatusActions;
use App\Filament\Admin\Resources\Stores\StoreResource;
use Filament\Resources\Pages\ViewRecord;

class ViewStore extends ViewRecord
{
    protected static string $resource = StoreResource::class;

    /**
     * The same status actions as the table, shown only when the status allows them.
     */
    protected function getHeaderActions(): array
    {
        return StoreStatusActions::all();
    }
}
