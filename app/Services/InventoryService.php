<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use LogicException;

class InventoryService
{
    public const INVENTORY_ACCOUNT = '1400';

    public const COGS_ACCOUNT = '5010';

    /**
     * Weighted-average receive — increases qty and avg_cost.
     */
    public function receive(
        Product $product,
        float $qty,
        float $unitCost,
        string $movementDate,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): InventoryMovement {
        if ($qty <= 0) {
            throw new LogicException('Receive quantity must be positive.');
        }

        return DB::transaction(function () use ($product, $qty, $unitCost, $movementDate, $referenceType, $referenceId) {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $this->assertTracksInventory($product);

            $oldQty = (float) $product->qty_on_hand;
            $oldAvg = (float) $product->avg_cost;
            $newQty = $oldQty + $qty;
            $newAvg = $newQty > 0
                ? round((($oldQty * $oldAvg) + ($qty * $unitCost)) / $newQty, 4)
                : 0.0;

            $product->update([
                'qty_on_hand' => round($newQty, 4),
                'avg_cost'    => $newAvg,
            ]);

            return InventoryMovement::create([
                'product_id'     => $product->id,
                'type'           => 'receive',
                'qty'            => $qty,
                'unit_cost'      => $unitCost,
                'reference_type' => $referenceType,
                'reference_id'   => $referenceId,
                'movement_date'  => $movementDate,
            ]);
        });
    }

    /**
     * Issue stock at weighted-average cost. Returns COGS amount in base currency.
     */
    public function issue(
        Product $product,
        float $qty,
        string $movementDate,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): float {
        if ($qty <= 0) {
            throw new LogicException('Issue quantity must be positive.');
        }

        return (float) DB::transaction(function () use ($product, $qty, $movementDate, $referenceType, $referenceId) {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $this->assertTracksInventory($product);

            $onHand = (float) $product->qty_on_hand;
            if ($qty > $onHand + 0.0001) {
                throw new LogicException("Insufficient stock for {$product->name} (on hand {$onHand}, requested {$qty}).");
            }

            $cogs = round($qty * (float) $product->avg_cost, 2);
            $product->update(['qty_on_hand' => round(max(0, $onHand - $qty), 4)]);

            InventoryMovement::create([
                'product_id'     => $product->id,
                'type'           => 'issue',
                'qty'            => $qty,
                'unit_cost'      => (float) $product->avg_cost,
                'reference_type' => $referenceType,
                'reference_id'   => $referenceId,
                'movement_date'  => $movementDate,
            ]);

            return $cogs;
        });
    }

    /**
     * @return list<array{account_code: string, debit: float, credit: float}>
     */
    public function cogsJournalLines(float $cogsAmount): array
    {
        if ($cogsAmount <= 0) {
            return [];
        }

        return [
            ['account_code' => self::COGS_ACCOUNT, 'debit' => $cogsAmount, 'credit' => 0],
            ['account_code' => self::INVENTORY_ACCOUNT, 'debit' => 0, 'credit' => $cogsAmount],
        ];
    }

    private function assertTracksInventory(Product $product): void
    {
        if (! $product->track_inventory) {
            throw new LogicException("Product {$product->name} does not track inventory.");
        }
    }
}
