<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StatementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'statement_number' => $this->statement_number,
            'type' => $this->type,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'opening_balance' => (float) $this->opening_balance,
            'total_debit' => (float) $this->total_debit,
            'total_credit' => (float) $this->total_credit,
            'closing_balance' => (float) $this->closing_balance,
            'total_transactions' => $this->total_transactions,
            'download_url' => "/api/statements/{$this->id}/download",
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
