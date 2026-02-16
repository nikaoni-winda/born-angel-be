# Pagination Guide — Born Angel API

All GET list endpoints return paginated responses using Laravel's offset-based pagination.

## Query Parameters

| Parameter  | Type | Default | Description |
|------------|------|---------|-------------|
| `page`     | int  | 1       | Page number (auto-read by Laravel) |
| `per_page` | int  | 10 or 15 | Items per page (see defaults below) |

## Endpoint Defaults

| Endpoint | Default `per_page` | Auth Required | Notes |
|----------|-------------------|---------------|-------|
| `GET /api/services` | 10 | No | |
| `GET /api/instructors` | 10 | No | |
| `GET /api/schedules` | 15 | No | Context-aware (role changes results) |
| `GET /api/reviews` | 15 | No | Context-aware for instructors |
| `GET /api/bookings` | 15 | Yes | Users see own; admins see all |
| `GET /api/users` | 15 | Yes (admin) | Supports `?role=` filter |

## Response Shape

Every paginated endpoint returns this structure:

```json
{
  "current_page": 1,
  "data": [
    { "id": 1, "..." : "..." }
  ],
  "first_page_url": "http://localhost/api/services?page=1",
  "from": 1,
  "last_page": 3,
  "last_page_url": "http://localhost/api/services?page=3",
  "links": [
    { "url": null, "label": "&laquo; Previous", "active": false },
    { "url": "http://localhost/api/services?page=1", "label": "1", "active": true },
    { "url": "http://localhost/api/services?page=2", "label": "2", "active": false },
    { "url": "http://localhost/api/services?page=2", "label": "Next &raquo;", "active": false }
  ],
  "next_page_url": "http://localhost/api/services?page=2",
  "path": "http://localhost/api/services",
  "per_page": 10,
  "prev_page_url": null,
  "to": 10,
  "total": 25
}
```

### Key Fields

| Field | Type | Description |
|-------|------|-------------|
| `data` | array | The actual items for this page |
| `current_page` | int | Current page number |
| `last_page` | int | Total number of pages |
| `per_page` | int | Items per page |
| `total` | int | Total item count across all pages |
| `next_page_url` | string\|null | URL for next page (`null` if on last page) |
| `prev_page_url` | string\|null | URL for previous page (`null` if on first page) |
| `from` | int\|null | Index of first item on this page (1-based) |
| `to` | int\|null | Index of last item on this page |

## Frontend Usage Examples

### Fetch Helper (JS/TS)

```ts
async function fetchPaginated<T>(
  endpoint: string,
  page = 1,
  perPage?: number
): Promise<PaginatedResponse<T>> {
  const params = new URLSearchParams({ page: String(page) });
  if (perPage) params.set('per_page', String(perPage));

  const res = await fetch(`${API_BASE}/${endpoint}?${params}`, {
    headers: { Authorization: `Bearer ${token}` },
  });
  return res.json();
}

// Usage
const services = await fetchPaginated<Service>('services', 1, 10);
console.log(services.data);        // Service[]
console.log(services.last_page);   // total pages
```

### React Hook Pattern

```tsx
function usePaginated<T>(endpoint: string, perPage?: number) {
  const [page, setPage] = useState(1);
  const [data, setData] = useState<PaginatedResponse<T> | null>(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    setLoading(true);
    fetchPaginated<T>(endpoint, page, perPage)
      .then(setData)
      .finally(() => setLoading(false));
  }, [endpoint, page, perPage]);

  return {
    items: data?.data ?? [],
    page,
    lastPage: data?.last_page ?? 1,
    total: data?.total ?? 0,
    loading,
    setPage,
    hasNext: data?.next_page_url !== null,
    hasPrev: data?.prev_page_url !== null,
  };
}

// Usage
function ServiceList() {
  const { items, page, lastPage, setPage, hasNext, hasPrev } =
    usePaginated<Service>('services', 10);

  return (
    <>
      {items.map((s) => <ServiceCard key={s.id} service={s} />)}
      <button disabled={!hasPrev} onClick={() => setPage(page - 1)}>Prev</button>
      <span>{page} / {lastPage}</span>
      <button disabled={!hasNext} onClick={() => setPage(page + 1)}>Next</button>
    </>
  );
}
```

### TypeScript Type

```ts
interface PaginatedResponse<T> {
  current_page: number;
  data: T[];
  first_page_url: string;
  from: number | null;
  last_page: number;
  last_page_url: string;
  links: { url: string | null; label: string; active: boolean }[];
  next_page_url: string | null;
  path: string;
  per_page: number;
  prev_page_url: string | null;
  to: number | null;
  total: number;
}
```
