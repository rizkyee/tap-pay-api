<?php

namespace App\Http\Resources;

use App\Models\Transaction;
use App\Support\Masker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $sender = $this->relationLoaded('senderWallet') ? $this->senderWallet?->user : null;
        $receiver = $this->relationLoaded('receiverWallet') ? $this->receiverWallet?->user : null;

        return [
            'id' => $this->id,
            'transaction_code' => $this->transaction_code,
            'reference' => $this->reference,
            'type' => $this->type,
            'direction' => $this->resolveDirection($request),
            'status' => $this->status,
            'amount' => (float) $this->amount,
            'fee' => (float) $this->fee,
            'total_amount' => (float) ($this->total_amount ?? $this->amount),
            'notes' => $this->notes,
            'sender' => $sender ? [
                'name' => $sender->name,
                'phone_masked' => Masker::phone($sender->phone),
            ] : null,
            'receiver' => $receiver ? [
                'name' => $receiver->name,
                'phone_masked' => Masker::phone($receiver->phone),
            ] : null,
            'merchant' => $this->whenLoaded('merchant', fn () => $this->merchant ? [
                'name' => $this->merchant->name,
            ] : null),
            'expires_at' => $this->expires_at?->toISOString(),
            'approved_at' => $this->approved_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'receipt_url' => $this->transaction_code
                ? "/api/transactions/{$this->id}/receipt"
                : null,
        ];
    }

    /**
     * Tentukan arah transaksi (in/out) relatif terhadap user yang login.
     */
    private function resolveDirection(Request $request): ?string
    {
        return match ($this->type) {
            Transaction::TYPE_TOPUP => 'in',
            Transaction::TYPE_WITHDRAW => 'out',
            Transaction::TYPE_TRANSFER => $this->isIncomingTransferFor($request) ? 'in' : 'out',
            default => null,
        };
    }

    private function isIncomingTransferFor(Request $request): bool
    {
        $userId = $request->user()?->id;

        if ($userId === null || ! $this->relationLoaded('receiverWallet')) {
            return false;
        }

        return (int) $this->receiverWallet?->user_id === (int) $userId;
    }
}
