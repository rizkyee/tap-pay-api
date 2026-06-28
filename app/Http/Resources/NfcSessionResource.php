<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NfcSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nfc_token' => $this->token,
            'mode' => $this->mode,
            'status' => $this->status,
            'expires_at' => $this->expires_at?->toISOString(),
            'used_at' => $this->used_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
