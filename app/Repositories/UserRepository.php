<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class UserRepository
{
    public function findByPhone(string $phone): ?User
    {
        return User::query()->where('phone', $phone)->first();
    }

    public function findActiveCustomer(int|string $identifier): ?User
    {
        return User::query()
            ->where('role', User::ROLE_CUSTOMER)
            ->where('is_active', true)
            ->where(function ($query) use ($identifier) {
                $query->where('id', $identifier)
                    ->orWhere('phone', $identifier);
            })
            ->first();
    }

    public function searchCustomers(string $term, int $limit = 10): Collection
    {
        return User::query()
            ->where('role', User::ROLE_CUSTOMER)
            ->where(function ($query) use ($term) {
                $query->where('phone', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            })
            ->with('wallet')
            ->limit($limit)
            ->get();
    }
}
