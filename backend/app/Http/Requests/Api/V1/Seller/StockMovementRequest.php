<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Seller;

use App\Enums\StockMovementType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

/**
 * A stock book entry from the seller app. App sales and cancel returns are
 * only written by orders, so they are not accepted here. The quantity's
 * format and which details fit each type are RecordStockMovement's job.
 */
class StockMovementRequest extends FormRequest
{
    /**
     * Who may record stock is checked by the controller and the policy.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'variant_id' => ['required', 'integer'],
            'type' => ['required', Rule::enum(StockMovementType::class)->only([
                StockMovementType::StockIn,
                StockMovementType::SaleOffline,
                StockMovementType::Shrinkage,
                StockMovementType::Adjustment,
            ])],
            'qty' => ['required', 'numeric'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            // Upper bound keeps the value inside the unsigned integer column.
            'unit_cost' => ['nullable', 'integer', 'max:100000000'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function type(): StockMovementType
    {
        return $this->enum('type', StockMovementType::class) ?? throw new LogicException('The type is validated as required.');
    }

    /**
     * The quantity as a decimal string, also when it was sent as a JSON number.
     */
    public function qty(): string
    {
        return (string) $this->input('qty');
    }

    public function supplierName(): ?string
    {
        return $this->filled('supplier_name') ? $this->string('supplier_name')->trim()->toString() : null;
    }

    public function unitCost(): ?int
    {
        return $this->filled('unit_cost') ? $this->integer('unit_cost') : null;
    }

    public function note(): ?string
    {
        return $this->filled('note') ? $this->string('note')->trim()->toString() : null;
    }
}
