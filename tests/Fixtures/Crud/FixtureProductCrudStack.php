<?php

declare(strict_types=1);

namespace Tests\Fixtures\Crud;

use Idei\Usim\Attributes\Crud\BelongsTo;
use Idei\Usim\Attributes\Crud\BelongsToMany;
use Idei\Usim\Attributes\Crud\CrudField;
use Idei\Usim\Contracts\ItemList\Listable;
use Idei\Usim\Contracts\MasterDetail\CreatableDetail;
use Idei\Usim\Contracts\MasterDetail\DeletableDetail;
use Idei\Usim\Contracts\MasterDetail\Detailable;
use Idei\Usim\Contracts\MasterDetail\UpdatableDetail;
use Idei\Usim\DTOs\ListItemDTO;
use Idei\Usim\Enums\Style;
use Illuminate\Support\Collection;

final class FixtureBrandService implements Listable
{
    public function list(string $search = '', array $context = []): Collection
    {
        return collect([
            ListItemDTO::make(id: 'acme', label: 'Acme Corp'),
            ListItemDTO::make(id: 'globex', label: 'Globex Inc'),
        ]);
    }
}

final class FixtureTagService implements Listable
{
    public function list(string $search = '', array $context = []): Collection
    {
        return collect([
            ListItemDTO::make(id: 'featured', label: 'Featured'),
            ListItemDTO::make(id: 'fragile', label: 'Fragile'),
            ListItemDTO::make(id: 'clearance', label: 'Clearance'),
        ]);
    }
}

final readonly class FixtureProductDTO
{
    /**
     * @param  list<int|string>  $tagIds
     */
    public function __construct(
        public int|string|null $id,
        public string $sku,
        public string $name,
        public float $price,
        public bool $active = true,
        #[CrudField(type: 'textarea')]
        public ?string $notes = null,
        #[BelongsTo(FixtureBrandService::class)]
        public int|string|null $brandId = null,
        #[BelongsToMany(FixtureTagService::class)]
        public array $tagIds = [],
    ) {}

    public function withId(int|string $id): self
    {
        return new self(
            id: $id,
            sku: $this->sku,
            name: $this->name,
            price: $this->price,
            active: $this->active,
            notes: $this->notes,
            brandId: $this->brandId,
            tagIds: $this->tagIds,
        );
    }
}

interface FixtureProductRepositoryInterface
{
    /**
     * @return list<FixtureProductDTO>
     */
    public function all(): array;

    public function find(int|string $id): ?FixtureProductDTO;

    public function create(FixtureProductDTO $dto): FixtureProductDTO;

    public function update(int|string $id, FixtureProductDTO $dto): ?FixtureProductDTO;

    public function delete(int|string $id): bool;
}

final class InMemoryFixtureProductRepository implements FixtureProductRepositoryInterface
{
    /** @var array<string, FixtureProductDTO> */
    private array $items;

    public function __construct()
    {
        $this->items = [
            '1' => new FixtureProductDTO(
                id: 1,
                sku: 'SKU-100',
                name: 'Industrial Sensor',
                price: 149.90,
                active: true,
                notes: 'Calibrated unit',
                brandId: 'acme',
                tagIds: ['featured', 'fragile'],
            ),
            '2' => new FixtureProductDTO(
                id: 2,
                sku: 'SKU-200',
                name: 'Control Valve',
                price: 89.50,
                active: false,
                notes: null,
                brandId: 'globex',
                tagIds: [],
            ),
        ];
    }

    public function all(): array
    {
        return array_values($this->items);
    }

    public function find(int|string $id): ?FixtureProductDTO
    {
        return $this->items[(string) $id] ?? null;
    }

    public function create(FixtureProductDTO $dto): FixtureProductDTO
    {
        $nextId = count($this->items) + 10;
        $stored = $dto->withId($nextId);
        $this->items[(string) $nextId] = $stored;

        return $stored;
    }

    public function update(int|string $id, FixtureProductDTO $dto): ?FixtureProductDTO
    {
        $key = (string) $id;
        if (! isset($this->items[$key])) {
            return null;
        }

        $updated = $dto->withId($id);
        $this->items[$key] = $updated;

        return $updated;
    }

    public function delete(int|string $id): bool
    {
        $key = (string) $id;
        if (! isset($this->items[$key])) {
            return false;
        }

        unset($this->items[$key]);

        return true;
    }
}

/**
 * @implements Detailable<FixtureProductDTO>
 * @implements CreatableDetail<FixtureProductDTO>
 * @implements UpdatableDetail<FixtureProductDTO>
 */
final class FixtureProductService implements CreatableDetail, DeletableDetail, Detailable, Listable, UpdatableDetail
{
    private FixtureProductRepositoryInterface $repository;

    public function __construct(?FixtureProductRepositoryInterface $repository = null)
    {
        $this->repository = $repository ?? (
            app()->bound(FixtureProductRepositoryInterface::class)
                ? app(FixtureProductRepositoryInterface::class)
                : new InMemoryFixtureProductRepository
        );
    }

    public function list(string $search = '', array $context = []): Collection
    {
        $items = collect($this->repository->all())->map(
            static fn (FixtureProductDTO $p): ListItemDTO => ListItemDTO::make(
                id: $p->id ?? 0,
                label: "{$p->name} ({$p->sku})",
                subtitle: '$'.number_format($p->price, 2),
                badge: $p->active ? 'Active' : 'Archived',
                badgeStyle: $p->active ? Style::SUCCESS : Style::SECONDARY,
            )
        );

        $term = mb_strtolower(trim($search));
        if ($term === '') {
            return $items->values();
        }

        return $items->filter(
            static fn (ListItemDTO $item): bool => str_contains(mb_strtolower($item->label), $term)
        )->values();
    }

    public function findDetail(int|string $id): ?FixtureProductDTO
    {
        return $this->repository->find($id);
    }

    public function createDetail(object $dto): FixtureProductDTO
    {
        assert($dto instanceof FixtureProductDTO);

        return $this->repository->create($dto);
    }

    public function updateDetail(int|string $id, object $dto): ?FixtureProductDTO
    {
        assert($dto instanceof FixtureProductDTO);

        return $this->repository->update($id, $dto);
    }

    public function deleteDetail(int|string $id): bool
    {
        return $this->repository->delete($id);
    }
}

/**
 * Read-only Audit Explorer Service implementing ONLY Listable + Detailable.
 *
 * @implements Detailable<FixtureProductDTO>
 */
final class FixtureReadOnlyCatalogService implements Detailable, Listable
{
    private InMemoryFixtureProductRepository $repository;

    public function __construct()
    {
        $this->repository = new InMemoryFixtureProductRepository;
    }

    public function list(string $search = '', array $context = []): Collection
    {
        return collect($this->repository->all())->map(
            static fn (FixtureProductDTO $p): ListItemDTO => ListItemDTO::make(
                id: $p->id ?? 0,
                label: $p->name,
                subtitle: $p->sku,
            )
        );
    }

    public function findDetail(int|string $id): ?FixtureProductDTO
    {
        return $this->repository->find($id);
    }
}
