<?php

declare(strict_types=1);

namespace DropContext\Gateway\Dto;

/** One page of an entity_gateway collection response. */
final readonly class CollectionPage implements \JsonSerializable {

  /** @param array<int, array<string, mixed>> $data */
  public function __construct(
    public int $total,
    public int $page,
    public int $perPage,
    public int $count,
    public array $data,
  ) {}

  /** @param array<string, mixed> $data */
  public static function fromArray(array $data): self {
    return new self(
      total: (int) ($data['total'] ?? 0),
      page: (int) ($data['page'] ?? 1),
      perPage: (int) ($data['perPage'] ?? 0),
      count: (int) ($data['count'] ?? 0),
      data: $data['data'] ?? [],
    );
  }

  public function jsonSerialize(): array {
    return [
      'total' => $this->total,
      'page' => $this->page,
      'perPage' => $this->perPage,
      'count' => $this->count,
      'data' => $this->data,
    ];
  }

}
