<?php
namespace Domain\Shared\Repositories;

use Domain\Shared\Contracts\BaseRepositoryContract;

class BaseRepository implements BaseRepositoryContract {
    protected $modelClass;
    protected $model;

    public function __construct()
    {
        $this->model = app("{$this->modelClass}");
    }

    public function create(array $data) : array
    {
        $register = $this->model->create($data);
        return $register->toArray();
    }

    public function update(int $id, array $data) : array
    {
        $register = $this->model->find($id)->fill($data);
        $register->update();
        return $register->toArray();
    }

    public function find(int $id) : array
    {
        $register = $this->model->find($id);
        return $register->toArray();
    }

    public function delete(int $id) : bool
    {
        return $this->model->find($id)->delete();
    }

    public function findAll() : array
    {
        $registers = $this->model->all();
        return $registers->toArray();
    }
}
