# Master-Detail CRUD (`usim:crud`) — AI Technical Reference & Guardrails

Technical specification and strict architectural boundaries for LLMs/AI agents generating or modifying Master-Detail screens in USIM (v2.24.0+).

---

## 1. Architectural Mandate (`Screen ➔ Service ➔ RepositoryInterface` + `DTO`)

1. **Zero Eloquent Coupling in UI & Service Contracts:**
   - A `Screen` talks **only** to a `Service` and a typed immutable `readonly` **DTO**. It NEVER injects a `Repository` or Eloquent `Model` directly.
   - A `Service` injects a domain `RepositoryInterface` (backed by JSON flat files, `session()`, in-memory arrays for tests, REST APIs, or Eloquent) and adapts domain DTOs to USIM contracts.
   - A `RepositoryInterface` operates strictly on domain `DTO` instances (`all()`, `find()`, `create()`, `update()`, `delete()`) and MUST NOT reference `ListItemDTO`, `Style`, or UI concerns.
2. **Octane Concurrency Safety:**
   - Never store mutable request/selection state in `static` properties on `Screen`, `Service`, or `Repository` singletons.
   - `InteractsWithMasterDetail` stores active state exclusively in per-screen instance properties (`protected string $state_detail_mode`, `protected int|string|null $state_selected_id`), persisted automatically via `UIStateRepositoryInterface`.
3. **Constructor Discipline on `Screen` Subclasses:**
   - `Screen::__construct()` runs `$this->autoWireComponentProperties()`. Every `Screen` subclass that declares `__construct(...)` for dependency injection **MUST** call `parent::__construct();`.

---

## 2. Exact Service Contracts (Capability Matrix)

Do **NOT** implement `Idei\Usim\Contracts\ItemList\Creatable` or `Idei\Usim\Contracts\ItemList\Updatable` on a Master-Detail service unless inline single-input list creation/renaming inside `left_panel` is explicitly desired. Always use `Idei\Usim\Contracts\MasterDetail\*` for central-panel DTO operations:

| Interface | Namespace | Signature | UI Effect |
|---|---|---|---|
| `Listable` | `Idei\Usim\Contracts\ItemList` | `list(string $search = '', array $context = []): Collection<int, ListItemDTO>` | Populates `lst_items` in `slots.left_panel`. **(Required)** |
| `Detailable` | `Idei\Usim\Contracts\MasterDetail` | `findDetail(int\|string $id): ?object` *(concrete `?EntityDTO` return type required for reflection)* | Hydrates `cnt_detail_form` in `main`. **(Required)** |
| `CreatableDetail` | `Idei\Usim\Contracts\MasterDetail` | `createDetail(object $dto): object` | Enables `btn_new`, `btn_empty_create`, and `onStartCreate()`. |
| `UpdatableDetail` | `Idei\Usim\Contracts\MasterDetail` | `updateDetail(int\|string $id, object $dto): ?object` | Enables direct-edit mode (`editing`), `btn_save`, `btn_cancel`, and `UIRequest`. |
| `DeletableDetail` | `Idei\Usim\Contracts\MasterDetail` | `deleteDetail(int\|string $id): bool` | Enables `btn_delete` in `slots.top_bar` + `ConfirmDialog` (`request_delete` -> `confirm_delete`). |

- If a service implements **only** `Listable` + `Detailable`, `usim:crud` scaffolds a read-only explorer (`screen_readonly.php.stub`) with `readonly`/`disabled` detail widgets and no `UIRequest`.

---

## 3. DTO Reflection & Relation Attributes (`Idei\Usim\Attributes\Crud\*`)

`CrudSchemaInspector` inspects the concrete DTO return type of `Service::findDetail()` (or `@implements Detailable<EntityDTO>`):

- `id` property is excluded from form inputs and tracked automatically via `$this->state_selected_id`.
- `#[BelongsTo(RelatedListableService::class, searchable: true)]` on `int|string|null $foreignId = null`:
  - Generates `protected Select $sel_<snake>;` on `Screen`.
  - Injects `RelatedListableService` into `Screen::__construct()` and populates options via `$this->loadSelectOptionsFromService(...)` inside `initializeDetailRelations()`.
- `#[BelongsToMany(RelatedListableService::class, searchable: true, maxHeight: 240)]` on `array $relatedIds = []`:
  - Generates `protected ItemList $lst_<snake>;` configured in `.layout.yaml` with `service: <RelatedListableService>`, `selection_mode: multiple`, `keep_selected_visible: true`.
  - Hydrated via `$this->lst_<snake>->select($dto->relatedIds)` and collected automatically on `save_detail` into `UIRequest::$map`.
- `#[CrudField(type: 'text'|'email'|'number'|'password'|'textarea'|'checkbox', rules: '...', span: 1|2, readonly: false, ignore: false)]`:
  - Optional per-property overrides.
- **Component error clearing parity:**
  - `Input`, `Textarea`, `ItemList` clear errors via `->error(null)`.
  - `Checkbox`, `Select` clear errors via `->errorMessage('')`.

---

## 4. Canonical Component & Action Contract (`InteractsWithMasterDetail`)

Any screen using `Idei\Usim\Concerns\InteractsWithMasterDetail` (or extending `Idei\Usim\Screens\MasterDetailScreen`) relies on these deterministic component names in its sibling `.layout.yaml`:

- Required elements:
  - `lst_items` (`ItemList` in `slots.left_panel`, `selection_mode: single`, `on_select: item_selected`)
  - `cnt_empty_state` (`Container` in `main`, visible when `$state_detail_mode === 'empty'`)
  - `cnt_detail_form` (`Container` in `main`, `visible: false` initially)
  - `lbl_detail_heading` (`NewLabel` in `slots.top_bar` or header)
- Optional capability-gated buttons:
  - `btn_new` / `btn_empty_create` (`action: start_create`)
  - `btn_save` (`action: save_detail`, handled by `onSaveDetail(SaveXxxRequest $request)` calling `$this->persistDetailDto($request->toDto($this->state_selected_id))`)
  - `btn_cancel` (`action: cancel_detail`)
  - `btn_delete` (`action: request_delete`, `visible: false` initially; triggers `ConfirmDialog` -> `confirm_delete` / `cancel_delete`)

---

## 5. CLI Commands for AI Agents (Non-Interactive)

Always pass `--no-interaction` in automated/agentic workflows:

```bash
# 1. Scaffold UI from an existing Service + DTO:
php artisan usim:crud Catalog/ProductManager \
  --service=App\\Services\\Catalog\\ProductService \
  --visibility=authenticated \
  --discover \
  --no-interaction

# 2. Scaffold full DTO + RepositoryInterface + MockRepo (json|session|memory) + Service + UI:
php artisan usim:crud Catalog/ProductManager \
  --with-stack=json \
  --fields="sku:string,name:string,price:float,active:bool,brandId:one(Catalog/BrandService),tagIds:many(Catalog/TagService)" \
  --visibility=authenticated \
  --discover \
  --no-interaction

# 3. Publish customizable CRUD stubs to stubs/usim/crud/ (file-by-file cascade):
php artisan vendor:publish --tag=usim-crud-stubs
```
