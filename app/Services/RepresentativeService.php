<?php

namespace App\Services;

use App\Enums\UserStatusEnum;
use App\Models\Representative;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RepresentativeService extends BaseService
{
    public function setModel(): void
    {
        $this->model = new Representative;
    }

    public function getRepresentatives(): Collection
    {
        return Representative::where('user_id', $this->currentUserId())
            ->orderBy('id')
            ->get();
    }

    public function create(array $data): Representative
    {
        return DB::transaction(function () use ($data): Representative {
            $user = User::whereKey($this->currentUserId())
                ->lockForUpdate()
                ->firstOrFail();

            if ($user->status !== UserStatusEnum::Active || ! $user->hasValidAccessPeriod()) {
                throw new AuthorizationException('Only active subscribers can add representatives.');
            }

            $representativesCount = $user->representatives()->count();

            if ($representativesCount >= $user->representatives_limit) {
                throw ValidationException::withMessages([
                    'representatives' => 'The representative limit for this account has been reached.',
                ]);
            }

            return $user->representatives()->create($data);
        });
    }

    public function update(int $id, array $data): Representative
    {
        $representative = $this->findOwnedRepresentative($id);
        $representative->update($data);

        return $representative->refresh();
    }

    public function delete(int $id): void
    {
        $this->findOwnedRepresentative($id)->delete();
    }

    private function findOwnedRepresentative(int $id): Representative
    {
        $representative = Representative::where('user_id', $this->currentUserId())
            ->find($id);

        if ($representative === null) {
            throw (new ModelNotFoundException)->setModel(Representative::class, [$id]);
        }

        return $representative;
    }
}
