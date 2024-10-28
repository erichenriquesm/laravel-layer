<?php
namespace Domain\Shared\Contracts;

interface BaseRepositoryContract {

    function create(array $data) :array;

    function update(int $id, array $data) : array;

    function find(int $id): array;

    function delete(int $id) : bool;

    function findAll() : array;
}
