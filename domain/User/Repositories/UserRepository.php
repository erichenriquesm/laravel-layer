<?php

declare(strict_types=1);

namespace Domain\User\Repositories;

use App\Models\User;
use Domain\User\Contracts\UserRepositoryContract;
use Domain\Shared\Repositories\BaseRepository;
use Domain\User\DTOs\StoreUserDTO;

class UserRepository extends BaseRepository implements UserRepositoryContract
{
    protected string $modelClass = User::class;

    public function __construct()
    {
        parent::__construct();
    }

    public function methodName(StoreUserDTO $input) : array
    {
        // your Function
        return ['OK'];
    }
}
