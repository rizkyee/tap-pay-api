<?php

namespace App\Http\Resources;

use App\Support\Masker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone_masked' => Masker::phone($this->phone),
            'wallet' => $this->whenLoaded('wallet', fn () => $this->wallet ? [
                'wallet_code_masked' => Masker::walletCode($this->wallet->wallet_code),
                'status' => $this->wallet->status,
            ] : null),
        ];
    }
}
