<?php


namespace app\service;


use app\entity\File;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Throwable;


class SearchIndexService {
    private const DEFAULT_HOST = 'http://127.0.0.1:9200';
    private const DEFAULT_INDEX = 'simpledisk_files';
    private const MAX_RESULTS = 1000;

    private ?Client $client;
    private ?FileContentExtractor $contentExtractor;
    private bool $indexEnsured = false;

    public function __construct(
            ?FileContentExtractor $contentExtractor = null,
            ?Client $client = null
    ) {
        $this->contentExtractor = $contentExtractor;
        $this->client = $client;
    }

    /**
     * @return string[]
     * @throws ServiceException
     */
    public function searchFileHashes(int $userId, ?int $folderId, string $search): array {
        $search = trim($search);
        if ($search === '') {
            return array();
        }

        try {
            $this->ensureIndexExists();
            $response = $this->client()->search(array(
                    'index' => self::indexName(),
                    'body' => array(
                            'size' => self::MAX_RESULTS,
                            '_source' => array('file_hash'),
                            'query' => array(
                                    'bool' => array(
                                            'filter' => $this->buildScopeFilters($userId, $folderId),
                                            'minimum_should_match' => 1,
                                            'should' => array(
                                                    array(
                                                            'multi_match' => array(
                                                                    'query' => $search,
                                                                    'fields' => array('name^4', 'content'),
                                                                    'type' => 'best_fields',
                                                                    'operator' => 'and',
                                                            ),
                                                    ),
                                                    array(
                                                            'wildcard' => array(
                                                                    'name.keyword' => array(
                                                                            'value' => '*' . $this->escapeWildcardValue($search) . '*',
                                                                            'case_insensitive' => true,
                                                                    ),
                                                            ),
                                                    ),
                                            ),
                                    ),
                            ),
                    ),
            ))->asArray();
        } catch (Throwable $e) {
            throw new ServiceException('Failed to search files in Elasticsearch', previous: $e);
        }

        $hashes = array();
        foreach (($response['hits']['hits'] ?? array()) as $hit) {
            $hash = $hit['_source']['file_hash'] ?? $hit['_id'] ?? null;
            if (is_string($hash) && $hash !== '') {
                $hashes[] = $hash;
            }
        }

        return array_values(array_unique($hashes));
    }

    /**
     * @throws ServiceException
     */
    public function indexFile(File $file): void {
        try {
            $this->ensureIndexExists();
            $this->client()->index(array(
                    'index' => self::indexName(),
                    'id' => $file->hash,
                    'refresh' => 'wait_for',
                    'body' => $this->buildDocument($file),
            ));
        } catch (Throwable $e) {
            throw new ServiceException('Failed to index file in Elasticsearch', previous: $e);
        }
    }

    /**
     * @throws ServiceException
     */
    public function deleteFile(string $fileHash): void {
        try {
            $this->ensureIndexExists();
            $this->client()->delete(array(
                    'index' => self::indexName(),
                    'id' => $fileHash,
                    'refresh' => 'wait_for',
            ));
        } catch (ClientResponseException $e) {
            if ($e->getCode() !== 404) {
                throw new ServiceException('Failed to delete file from Elasticsearch', previous: $e);
            }
        } catch (Throwable $e) {
            throw new ServiceException('Failed to delete file from Elasticsearch', previous: $e);
        }
    }

    private function client(): Client {
        if ($this->client === null) {
            $this->client = ClientBuilder::create()
                    ->setHosts(self::hosts())
                    ->build();
        }

        return $this->client;
    }

    private function contentExtractor(): FileContentExtractor {
        return $this->contentExtractor ??= new FileContentExtractor();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildScopeFilters(int $userId, ?int $folderId): array {
        $filters = array(
                array(
                        'term' => array(
                                'user_id' => $userId,
                        ),
                ),
        );

        if ($folderId === null) {
            $filters[] = array(
                    'bool' => array(
                            'must_not' => array(
                                    array(
                                            'exists' => array(
                                                    'field' => 'folder_id',
                                            ),
                                    ),
                            ),
                    ),
            );
        } else {
            $filters[] = array(
                    'term' => array(
                            'folder_id' => $folderId,
                    ),
            );
        }

        return $filters;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDocument(File $file): array {
        $document = array(
                'file_hash' => $file->hash,
                'user_id' => $file->userId,
                'name' => $file->name,
                'content' => $this->extractIndexableContent($file),
        );

        if ($file->folderId !== null) {
            $document['folder_id'] = $file->folderId;
        }

        return $document;
    }

    private function extractIndexableContent(File $file): string {
        try {
            return trim($this->contentExtractor()->extract($file) ?? '');
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * @throws ServiceException
     */
    private function ensureIndexExists(): void {
        if ($this->indexEnsured) {
            return;
        }

        try {
            $exists = $this->client()->indices()->exists(array(
                    'index' => self::indexName(),
            ))->asBool();

            if (!$exists) {
                $this->client()->indices()->create(array(
                        'index' => self::indexName(),
                        'body' => array(
                                'mappings' => array(
                                        'properties' => array(
                                                'file_hash' => array('type' => 'keyword'),
                                                'user_id' => array('type' => 'integer'),
                                                'folder_id' => array('type' => 'integer'),
                                                'name' => array(
                                                        'type' => 'text',
                                                        'fields' => array(
                                                                'keyword' => array(
                                                                        'type' => 'keyword',
                                                                        'ignore_above' => 256,
                                                                ),
                                                        ),
                                                ),
                                                'content' => array('type' => 'text'),
                                        ),
                                ),
                        ),
                ));
            }
        } catch (ClientResponseException $e) {
            if (!str_contains($e->getMessage(), 'resource_already_exists_exception')) {
                throw new ServiceException('Failed to prepare Elasticsearch index', previous: $e);
            }
        } catch (Throwable $e) {
            throw new ServiceException('Failed to prepare Elasticsearch index', previous: $e);
        }

        $this->indexEnsured = true;
    }

    private function escapeWildcardValue(string $value): string {
        return str_replace(
                array('\\', '*', '?'),
                array('\\\\', '\\*', '\\?'),
                $value
        );
    }

    /**
     * @return string[]
     */
    private static function hosts(): array {
        $hosts = getenv('SIMPLEDISK_ELASTICSEARCH_HOSTS') ?: self::DEFAULT_HOST;

        return array_values(array_filter(array_map('trim', explode(',', $hosts))));
    }

    private static function indexName(): string {
        $index = getenv('SIMPLEDISK_ELASTICSEARCH_INDEX') ?: self::DEFAULT_INDEX;

        return trim($index) !== '' ? trim($index) : self::DEFAULT_INDEX;
    }
}
