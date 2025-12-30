<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Provider\Monday;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Monday.com GraphQL API client.
 *
 * Returns raw API responses - normalization is handled by Normalizers.
 * Created dynamically by AdapterFactory with user credentials.
 */
#[Autoconfigure(autowire: false)]
class MondayClient
{
    private const API_URL = 'https://api.monday.com/v2';

    private HttpClientInterface $httpClient;

    public function __construct(
        private readonly string $apiToken,
    ) {
        $this->httpClient = HttpClient::create();
    }

    /**
     * Execute a GraphQL query.
     *
     * @param array<string, mixed> $variables
     *
     * @return array<string, mixed>
     */
    private function query(string $query, array $variables = []): array
    {
        $body = ['query' => $query];
        if (!empty($variables)) {
            $body['variables'] = $variables;
        }

        $response = $this->httpClient->request('POST', self::API_URL, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => $this->apiToken,
            ],
            'json' => $body,
        ]);

        /** @var array{data?: array<string, mixed>, errors?: list<array{message: string}>} $result */
        $result = $response->toArray();

        if (isset($result['errors'][0]['message'])) {
            throw new \RuntimeException('Monday.com API error: '.$result['errors'][0]['message']);
        }

        return $result['data'] ?? [];
    }

    /**
     * Get current user.
     *
     * @return array<string, mixed>
     */
    public function getMe(): array
    {
        $data = $this->query('{ me { id name email } }');

        /* @var array<string, mixed> */
        return $data['me'] ?? [];
    }

    /**
     * Get all boards.
     *
     * @return list<array<string, mixed>>
     */
    public function getBoards(int $limit = 100): array
    {
        $query = 'query($limit: Int!) { boards(limit: $limit) { id name } }';
        $data = $this->query($query, ['limit' => $limit]);

        /* @var list<array<string, mixed>> */
        return $data['boards'] ?? [];
    }

    /**
     * Get items from a board.
     *
     * @return list<array<string, mixed>>
     */
    public function getBoardItems(string $boardId, int $limit = 50): array
    {
        $query = <<<'GQL'
            query($boardId: ID!, $limit: Int!) {
                boards(ids: [$boardId]) {
                    items_page(limit: $limit) {
                        items {
                            id
                            name
                            column_values { id text value }
                        }
                    }
                }
            }
        GQL;

        $data = $this->query($query, ['boardId' => $boardId, 'limit' => $limit]);

        $boards = $data['boards'] ?? [];
        if (!\is_array($boards) || !isset($boards[0]) || !\is_array($boards[0])) {
            return [];
        }

        $itemsPage = $boards[0]['items_page'] ?? [];
        if (!\is_array($itemsPage)) {
            return [];
        }

        /* @var list<array<string, mixed>> */
        return $itemsPage['items'] ?? [];
    }

    /**
     * Get a specific item.
     *
     * @return array<string, mixed>
     */
    public function getItem(string $itemId): array
    {
        // First get the board ID for this item
        $boardQuery = sprintf('{ items(ids: [%s]) { board { id } } }', $itemId);
        $boardData = $this->query($boardQuery);

        /** @var list<array{board?: array{id?: string}}> $items */
        $items = $boardData['items'] ?? [];
        if (!isset($items[0]['board']['id'])) {
            throw new \RuntimeException('Item not found');
        }
        $boardId = $items[0]['board']['id'];

        // Then get item with description via boards->items_page (required for description field)
        $query = sprintf(
            '{ boards(ids: [%s]) { items_page(query_params: {ids: [%s]}) { items { id name board { id name } description { blocks { id content } } column_values { id type text value } updates { id body created_at creator { id name } } } } } }',
            $boardId,
            $itemId
        );

        $data = $this->query($query);

        /** @var list<array{items_page?: array{items?: list<array<string, mixed>>}}> $boards */
        $boards = $data['boards'] ?? [];
        $itemsList = $boards[0]['items_page']['items'] ?? [];
        if (!isset($itemsList[0])) {
            throw new \RuntimeException('Item not found');
        }

        return $itemsList[0];
    }

    /**
     * Get all items across boards (for filtering by adapter).
     *
     * @return list<array<string, mixed>>
     */
    public function getAllItems(int $limit = 100): array
    {
        $query = <<<'GQL'
            query($limit: Int!) {
                boards(limit: 50) {
                    id
                    name
                    items_page(limit: $limit) {
                        items {
                            id
                            name
                            board { id name }
                            column_values { id text value }
                        }
                    }
                }
            }
        GQL;

        $data = $this->query($query, ['limit' => $limit]);

        $items = [];
        /** @var list<array{id: string, name: string, items_page: array{items: list<array<string, mixed>>}}> $boards */
        $boards = $data['boards'] ?? [];

        foreach ($boards as $board) {
            /** @var list<array<string, mixed>> $boardItems */
            $boardItems = $board['items_page']['items'];
            foreach ($boardItems as $item) {
                $item['board'] = ['id' => $board['id'], 'name' => $board['name']];
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Get items with time tracking data (read-only).
     *
     * @return list<array<string, mixed>>
     */
    public function getItemsWithTimeTracking(int $limit = 100): array
    {
        $query = <<<'GQL'
            query($limit: Int!) {
                boards(limit: 50) {
                    id
                    name
                    items_page(limit: $limit) {
                        items {
                            id
                            name
                            board { id name }
                            column_values {
                                id
                                text
                                ... on TimeTrackingValue {
                                    duration
                                    running
                                    started_at
                                    updated_at
                                }
                            }
                        }
                    }
                }
            }
        GQL;

        $data = $this->query($query, ['limit' => $limit]);

        $items = [];
        /** @var list<array{id: string, name: string, items_page: array{items: list<array<string, mixed>>}}> $boards */
        $boards = $data['boards'] ?? [];

        foreach ($boards as $board) {
            /** @var list<array<string, mixed>> $boardItems */
            $boardItems = $board['items_page']['items'];
            foreach ($boardItems as $item) {
                // Only include items with time_tracking that has duration
                /** @var list<array{duration?: int}> $columnValues */
                $columnValues = $item['column_values'] ?? [];
                foreach ($columnValues as $column) {
                    if (isset($column['duration']) && $column['duration'] > 0) {
                        $item['board'] = ['id' => $board['id'], 'name' => $board['name']];
                        $items[] = $item;
                        break;
                    }
                }
            }
        }

        return $items;
    }

    /**
     * Get asset (file) metadata.
     *
     * @return array<string, mixed>
     */
    public function getAsset(string $assetId): array
    {
        $query = <<<'GQL'
            query($assetId: ID!) {
                assets(ids: [$assetId]) {
                    id
                    name
                    file_size
                    file_extension
                    public_url
                    url
                    created_at
                    uploaded_by { id name }
                }
            }
        GQL;

        $data = $this->query($query, ['assetId' => $assetId]);

        /** @var list<array<string, mixed>> $assets */
        $assets = $data['assets'] ?? [];
        if (!isset($assets[0])) {
            throw new \RuntimeException('Asset not found');
        }

        return $assets[0];
    }

    /**
     * Download asset content via public URL.
     */
    public function downloadAsset(string $publicUrl): string
    {
        $response = $this->httpClient->request('GET', $publicUrl);

        return $response->getContent();
    }
}
