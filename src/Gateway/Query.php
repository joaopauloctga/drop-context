<?php

declare(strict_types=1);

namespace DropContext\Gateway;

/** Immutable filter/sort/page builder for entity_gateway collection requests. */
final class Query {

  final public const int MAX_PER_PAGE = 50;

  /** @var array<string, mixed> */
  private array $filters = [];

  /** @var list<string> */
  private array $sort = [];

  private int $page = 1;

  private int $perPage = 10;

  /** Adds a filter. `eq` renders as `filter[key]=value`; any other op renders as `filter[key][op]=value`. */
  public function filter(string $key, mixed $value, string $op = 'eq'): self {
    $clone = clone $this;

    if (is_array($value)) {
      $value = implode(',', $value);
    }

    if ($op === 'eq') {
      $clone->filters[$key] = $value;
    }
    else {
      $current = $clone->filters[$key] ?? [];
      if (!is_array($current)) {
        $current = [];
      }
      $current[$op] = $value;
      $clone->filters[$key] = $current;
    }

    return $clone;
  }

  /** Prefix a field with `-` for descending. */
  public function sort(string ...$fields): self {
    $clone = clone $this;
    $clone->sort = $fields;
    return $clone;
  }

  public function page(int $page): self {
    $clone = clone $this;
    $clone->page = max(1, $page);
    return $clone;
  }

  public function perPage(int $perPage): self {
    $clone = clone $this;
    $clone->perPage = max(1, min(self::MAX_PER_PAGE, $perPage));
    return $clone;
  }

  /** @return array<string, mixed> */
  public function toParams(): array {
    $params = [];

    if ($this->filters !== []) {
      $params['filter'] = $this->filters;
    }
    if ($this->sort !== []) {
      $params['sort'] = implode(',', $this->sort);
    }

    $params['page'] = $this->page;
    $params['perPage'] = $this->perPage;

    return $params;
  }

}
