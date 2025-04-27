<?php


namespace app\service;


use app\entity\QdrantMatchingFile;
use JsonException;


class QdrantService {
    private const COLLECTION_ENDPOINT       = 'http://127.0.0.1:6333/collections/files';
    private const SAVE_EMBEDDING_ENDPOINT   = 'http://127.0.0.1:6333/collections/files/points';
    private const SEARCH_EMBEDDING_ENDPOINT = 'http://127.0.0.1:6333/collections/files/points/search';
    private const VECTOR_SIZE = 768;
    private const MIN_MATCH_PERCENTAGE = 40;

    static function instance(): QdrantService {
        return new QdrantService();
    }

    /**
     * @param float[] $embedding
     *
     * @throws ServiceException
     */
    function saveEmbedding(int $userId, int $fileId, string $fileHash, array $embedding): void {
        $data = array(
                'points' => array(
                        array(
                                'id'      => $fileId,
                                'vector'  => $embedding,
                                'payload' => array(
                                        'file_hash' => $fileHash,
                                        'user_id'   => $userId,
                                ),
                        ),
                ),
        );

        try {
            $jsonData = \json_encode($data, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $msg = "Failed to encode JSON: {$e->getMessage()}";
            throw new ServiceException(message: $msg, previous: $e);
        }

        ['body' => $response, 'status' => $httpCode] = $this->sendJsonRequest(
                self::saveEmbeddingEndpoint(),
                'PUT',
                $jsonData
        );

        if ($httpCode !== 200) {
            throw new ServiceException("Failed to save embedding to Qdrant: $response");
        }
    }

    /**
     * @param float[] $embedding
     * @return QdrantMatchingFile[]
     *
     * @throws ServiceException
     */
    function getMatchedFiles(int $userId, array $embedding): array {
        $data = array(
                'vector'         => $embedding,
                'limit'          => 1000,
                'with_payload'   => true,
                'score_threshold' => QdrantService::MIN_MATCH_PERCENTAGE / 100,
                'filter'         => array(
                        'must'   => array(
                                array(
                                        'key'   => 'user_id',
                                        'match' => array('value' => $userId),
                                ),
                        ),
                ),
        );

        try {
            $jsonData = \json_encode($data, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $msg = "Failed to encode JSON: {$e->getMessage()}";
            throw new ServiceException(message: $msg, previous: $e);
        }

        ['body' => $response, 'status' => $httpCode] = $this->sendJsonRequest(
                self::searchEmbeddingEndpoint(),
                'POST',
                $jsonData
        );

        if ($httpCode !== 200) {
            throw new ServiceException("Failed to search matches in Qdrant: $response");
        }

        try {
            $response = \json_decode(json: $response, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $msg = "Invalid JSON response: {$e->getMessage()}";
            throw new ServiceException(message: $msg, previous: $e);
        }

        $matchedItems = $response['result'] ?? array();

        $matches = array();
        foreach ($matchedItems as $item) {
            $hash = isset($item['payload']['file_hash']) && \is_string($item['payload']['file_hash'])
                    ? $item['payload']['file_hash'] : '';
            $score = isset($item['score']) && \is_numeric($item['score']) ? (float) $item['score'] : 0.0;

            if (empty($hash)) {
                continue;
            }

            $matchPercentage = \round($score * 100, 2);
            $matches[] = new QdrantMatchingFile($hash, $matchPercentage);
        }

        return $matches;
    }

    /**
     * @throws ServiceException
     */
    function initCollection(): void {
        $httpCode = $this->fetchStatusCode(self::collectionEndpoint());

        if ($httpCode === 200) {
            return;
        }

        $data = array(
                'vectors' => array(
                        'size'     => QdrantService::VECTOR_SIZE,
                        'distance' => 'Cosine',
                ),
        );

        ['body' => $response, 'status' => $httpCode] = $this->sendJsonRequest(
                self::collectionEndpoint(),
                'PUT',
                (string) \json_encode($data)
        );

        if ($httpCode !== 200) {
            throw new ServiceException("Qdrant collection creation failed: $response");
        }
    }

    private static function collectionEndpoint(): string {
        return getenv('SIMPLEDISK_QDRANT_COLLECTION_ENDPOINT') ?: self::COLLECTION_ENDPOINT;
    }

    private static function saveEmbeddingEndpoint(): string {
        return getenv('SIMPLEDISK_QDRANT_SAVE_ENDPOINT') ?: self::SAVE_EMBEDDING_ENDPOINT;
    }

    private static function searchEmbeddingEndpoint(): string {
        return getenv('SIMPLEDISK_QDRANT_SEARCH_ENDPOINT') ?: self::SEARCH_EMBEDDING_ENDPOINT;
    }

    /**
     * @return array{body: string|false, status: int}
     */
    protected function sendJsonRequest(string $url, string $method, string $jsonData): array {
        $ch = \curl_init($url);
        \curl_setopt_array($ch, array(
                CURLOPT_CUSTOMREQUEST  => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => array('Content-Type: application/json'),
                CURLOPT_POSTFIELDS     => $jsonData,
        ));

        $response = \curl_exec($ch);
        $httpCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
        \curl_close($ch);

        return array(
                'body' => $response,
                'status' => $httpCode,
        );
    }

    protected function fetchStatusCode(string $url): int {
        $ch = \curl_init($url);
        \curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_NOBODY         => true,
        ));

        \curl_exec($ch);
        $httpCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
        \curl_close($ch);

        return $httpCode;
    }
}
