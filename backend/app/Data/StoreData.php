<?php

declare(strict_types=1);

namespace App\Data;

/**
 * The store details a seller fills in when creating or editing a store.
 * Coordinates are decimal strings so no precision is lost to floats.
 */
readonly class StoreData
{
    public function __construct(
        public string $name,
        public ?string $description,
        public string $phone,
        public string $address,
        public string $city,
        public string $lat,
        public string $lng,
        public string $bankName,
        public string $bankAccountNo,
        public string $bankAccountName,
        public ?string $stockReminderTime,
    ) {}

    /**
     * Map the fields to `stores` columns. The slug and status are left out on
     * purpose: they are never set from seller input.
     *
     * @return array<string, string|null>
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'bank_name' => $this->bankName,
            'bank_account_no' => $this->bankAccountNo,
            'bank_account_name' => $this->bankAccountName,
            'stock_reminder_time' => $this->stockReminderTime,
        ];
    }
}
