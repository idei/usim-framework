<?php

namespace App\UI\Screens\Demo\Support;

use Idei\Usim\Support\UIStateManager;
use Illuminate\Support\Facades\Cache;

class TableDemoService
{
	private const CACHE_PREFIX = 'table_demo_users';

	private static function users(): array
	{
		return [
			['id' => 1, 'name' => 'Ana Torres', 'email' => 'ana.torres.demo@example.com'],
			['id' => 2, 'name' => 'Luis Herrera', 'email' => 'luis.herrera.demo@example.com'],
			['id' => 3, 'name' => 'Marta Gil', 'email' => 'marta.gil.demo@example.com'],
			['id' => 4, 'name' => 'Carlos Vega', 'email' => 'carlos.vega.demo@example.com'],
			['id' => 5, 'name' => 'Sofía Navarro', 'email' => 'sofia.navarro.demo@example.com'],
			['id' => 6, 'name' => 'Diego Ruiz', 'email' => 'diego.ruiz.demo@example.com'],
			['id' => 7, 'name' => 'Lucía Castro', 'email' => 'lucia.castro.demo@example.com'],
			['id' => 8, 'name' => 'Javier Molina', 'email' => 'javier.molina.demo@example.com'],
			['id' => 9, 'name' => 'Elena Romero', 'email' => 'elena.romero.demo@example.com'],
			['id' => 10, 'name' => 'Pablo Ortega', 'email' => 'pablo.ortega.demo@example.com'],
			['id' => 11, 'name' => 'Irene Santos', 'email' => 'irene.santos.demo@example.com'],
			['id' => 12, 'name' => 'Raúl Medina', 'email' => 'raul.medina.demo@example.com'],
			['id' => 13, 'name' => 'Clara Flores', 'email' => 'clara.flores.demo@example.com'],
			['id' => 14, 'name' => 'Álvaro León', 'email' => 'alvaro.leon.demo@example.com'],
			['id' => 15, 'name' => 'Paula Serrano', 'email' => 'paula.serrano.demo@example.com'],
			['id' => 16, 'name' => 'Sergio Vidal', 'email' => 'sergio.vidal.demo@example.com'],
			['id' => 17, 'name' => 'Natalia Campos', 'email' => 'natalia.campos.demo@example.com'],
			['id' => 18, 'name' => 'Adrián Peña', 'email' => 'adrian.pena.demo@example.com'],
			['id' => 19, 'name' => 'Laura Fuentes', 'email' => 'laura.fuentes.demo@example.com'],
			['id' => 20, 'name' => 'Miguel Rojas', 'email' => 'miguel.rojas.demo@example.com'],
			['id' => 21, 'name' => 'Carmen Prieto', 'email' => 'carmen.prieto.demo@example.com'],
			['id' => 22, 'name' => 'Hugo Márquez', 'email' => 'hugo.marquez.demo@example.com'],
			['id' => 23, 'name' => 'Sara Lozano', 'email' => 'sara.lozano.demo@example.com'],
			['id' => 24, 'name' => 'Rubén Iglesias', 'email' => 'ruben.iglesias.demo@example.com'],
			['id' => 25, 'name' => 'Noelia Pardo', 'email' => 'noelia.pardo.demo@example.com'],
		];
	}

	public static function all(): array
	{
		return self::loadUsers();
	}

	public static function find(int $id): ?array
	{
        // validate id
        if ($id <= 0) {
            return null;
        }
		foreach (self::loadUsers() as $user) {
			if (($user['id'] ?? null) === $id) {
				return $user;
			}
		}

		return null;
	}

	public static function create(array $data): array
	{
		$users = self::loadUsers();
		$nextId = self::nextId($users);

		$newUser = [
			'id' => $nextId,
			'name' => (string) ($data['name'] ?? ''),
			'email' => (string) ($data['email'] ?? ''),
		];

		$users[] = $newUser;
		self::persistUsers($users);

		return $newUser;
	}

	public static function update(int $id, array $data): ?array
	{
		$users = self::loadUsers();

		foreach ($users as $index => $user) {
			if (($user['id'] ?? null) !== $id) {
				continue;
			}

			$users[$index] = [
				'id' => $id,
				'name' => (string) ($data['name'] ?? $user['name'] ?? ''),
				'email' => (string) ($data['email'] ?? $user['email'] ?? ''),
			];

			self::persistUsers($users);
			return $users[$index];
		}

		return null;
	}

	public static function delete(int $id): bool
	{
		$users = self::loadUsers();
		$initialCount = count($users);

		$users = array_values(array_filter($users, static fn (array $user): bool => ($user['id'] ?? null) !== $id));

		if (count($users) === $initialCount) {
			return false;
		}

		self::persistUsers($users);
		return true;
	}

	public static function reset(): array
	{
		$users = self::users();
		self::persistUsers($users);
		return $users;
	}

	private static function loadUsers(): array
	{
		$cacheKey = self::cacheKey();
		$users = Cache::get($cacheKey);

		if (!\is_array($users)) {
			$users = self::users();
			self::persistUsers($users);
		}

		return array_values($users);
	}

	private static function persistUsers(array $users): bool
	{
		$cacheKey = self::cacheKey();
		$ttl = (int) env('UI_CACHE_TTL', UIStateManager::DEFAULT_TTL);

		return Cache::put($cacheKey, array_values($users), $ttl);
	}

	private static function cacheKey(): string
	{
		$clientId = UIStateManager::getOrCreateClientId();
		return self::CACHE_PREFIX . ':' . $clientId;
	}

	private static function nextId(array $users): int
	{
		$maxId = 0;

		foreach ($users as $user) {
			$id = (int) ($user['id'] ?? 0);
			$maxId = max($maxId, $id);
		}

		return $maxId + 1;
	}


}
