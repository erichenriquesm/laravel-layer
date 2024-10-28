<?php

declare(strict_types=1);

namespace Domain\User\Repositories;

use App\Models\User;
use Domain\User\Contracts\UserRepositoryContract;
use Domain\Shared\Repositories\BaseRepository;
use Domain\User\DTOs\RegisterUserDTO;

class UserRepository extends BaseRepository implements UserRepositoryContract
{
    public function __construct()
    {
        $this->modelClass = User::class;
        parent::__construct();
    }
}
