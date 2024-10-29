<?php

declare(strict_types=1);

namespace Domain\Auth\Repositories;

use App\Models\User;
use Domain\Auth\Contracts\UserRepositoryContract;
use Domain\Shared\Repositories\BaseRepository;
use Domain\Auth\DTOs\RegisterUserDTO;

class UserRepository extends BaseRepository implements UserRepositoryContract
{
    public function __construct()
    {
        $this->modelClass = User::class;
        parent::__construct();
    }
}
