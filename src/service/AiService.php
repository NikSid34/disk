<?php


namespace app\service;


use app\entity\File;
use app\enum\FileType;
use JsonException;


class AiService {
    private const CHAT_ENDPOINT          = 'http://127.0.0.1:1234/v1/chat/completions';
    private const EMBEDDING_ENDPOINT     = 'http://127.0.0.1:1234/v1/embeddings';
    private const IMG_DESCRIPTION_PROMPT = 'Describe the image in Russian in about 200 characters. Focus on objects, colors, environment, actions, and notable features. Do not add any introductory phrases or comments, only provide the description.';
    private const DOC_DESCRIPTION_PROMPT = 'Analyze the content of the document. Determine the document type and generate a concise description in Russian (150 characters) that includes:
– the type of document,
– its main topic or purpose,
– the kind of information it contains.

Do not add any introductory phrases, explanations, or comments.
Response must contain only the description text.
Do not rewrite the content in literary form.';
    private const TEXT_SIZE_LIMIT        = 10000;

    private ?FileContentExtractor $contentExtractor;

    public function __construct(?FileContentExtractor $contentExtractor = null) {
        $this->contentExtractor = $contentExtractor;
    }

    static function instance(): AiService {
        return new AiService();
    }

    /**
     * @throws ServiceException
     */
    function getImageDescription(File $file): string {
        if ($file->fileType !== FileType::Image) {
            throw new ServiceException('Wrong file type');
        }

        $imageData = \base64_encode(\file_get_contents($file->fileObject->storagePath));
        $data = array(
                'model'    => 'google/gemma-3-12b',
                'messages' => array(
                        array(
                                'role'    => 'user',
                                'content' => array(
                                        array(
                                                'type'      => 'image_url',
                                                'image_url' => array(
                                                        'url' => "data:image/{$file->extension};base64, $imageData",
                                                ),
                                        ),
                                        array(
                                                'type' => 'text',
                                                'text' => AiService::IMG_DESCRIPTION_PROMPT,
                                        ),
                                ),
                        ),
                ),
        );
        $response = $this->requestChatCompletion($data);

        try {
            $response = json_decode(json: $response, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $msg = "Invalid JSON response: {$e->getMessage()}";
            throw new ServiceException(message: $msg, previous: $e);
        }

        if (
                isset($response['choices'][0]['message']['content'])
                && \is_string($response['choices'][0]['message']['content'])
        ) {
            return $response['choices'][0]['message']['content'];
        } else {
            throw new ServiceException('Failed to get image description: Invalid response structure');
        }
    }

    /**
     * @throws ServiceException
     */
    function getDocumentDescription(File $file): string {
        $text = $this->contentExtractor()->extract($file);
        if ($text === null) {
            throw new ServiceException('Wrong file type');
        }

        if (\trim($text) === '') {
            throw new ServiceException('Failed to extract text from document');
        }

        $text = \mb_substr($text, 0, AiService::TEXT_SIZE_LIMIT);
        $data = array(
                'model'    => 'google/gemma-3-12b',
                'messages' => array(
                        array(
                                'role'    => 'user',
                                'content' => array(
                                        array(
                                                'type' => 'text',
                                                'text' => AiService::DOC_DESCRIPTION_PROMPT . "\n\n" . $text,
                                        ),
                                ),
                        ),
                ),
        );

        $response = $this->requestChatCompletion($data);

        try {
            $response = \json_decode(json: $response, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $msg = "Invalid JSON response: {$e->getMessage()}";
            throw new ServiceException(message: $msg, previous: $e);
        }

        if (
                isset($response['choices'][0]['message']['content'])
                && \is_string($response['choices'][0]['message']['content'])
        ) {
            return $response['choices'][0]['message']['content'];
        }

        throw new ServiceException('Failed to get document description: Invalid response structure');
    }

    /**
     * @return float[]
     *
     * @throws ServiceException
     */
    function getTextEmbedding(string $text): array {
        if (empty($text)) {
            throw new ServiceException('Text must not be empty');
        }

        $data = array(
                'model' => 'text-embedding-nomic-embed-text-v2-moe',
                'input' => $text,
        );
        $response = $this->requestEmbedding($data);

        try {
            $response = json_decode(json: $response, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $msg = "Invalid JSON response: {$e->getMessage()}";
            throw new ServiceException(message: $msg, previous: $e);
        }

        if (isset($response['data'][0]['embedding']) && \is_array($response['data'][0]['embedding'])) {
            return $response['data'][0]['embedding'];
        } else {
            throw new ServiceException('Failed to get text embedding: Invalid response structure');
        }
    }

    private function contentExtractor(): FileContentExtractor {
        return $this->contentExtractor ??= new FileContentExtractor();
    }

    private static function chatEndpoint(): string {
        return getenv('SIMPLEDISK_AI_CHAT_ENDPOINT') ?: self::CHAT_ENDPOINT;
    }

    private static function embeddingEndpoint(): string {
        return getenv('SIMPLEDISK_AI_EMBEDDING_ENDPOINT') ?: self::EMBEDDING_ENDPOINT;
    }

    /**
     * @param array<string, mixed> $data
     * @throws ServiceException
     */
    protected function requestChatCompletion(array $data): string {
        $ch = \curl_init(self::chatEndpoint());
        \curl_setopt_array($ch, array(
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => array('Content-Type: application/json'),
                CURLOPT_POSTFIELDS     => \json_encode($data, JSON_UNESCAPED_UNICODE),
        ));
        $response = \curl_exec($ch);
        $error = \curl_error($ch);
        \curl_close($ch);

        if (!\is_string($response)) {
            throw new ServiceException('Failed to get image description: ' . $error);
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $data
     * @throws ServiceException
     */
    protected function requestEmbedding(array $data): string {
        $ch = \curl_init(self::embeddingEndpoint());
        \curl_setopt_array($ch, array(
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => array('Content-Type: application/json'),
                CURLOPT_POSTFIELDS     => \json_encode($data, JSON_UNESCAPED_UNICODE),
        ));
        $response = \curl_exec($ch);
        $error = \curl_error($ch);
        \curl_close($ch);

        if (!\is_string($response)) {
            throw new ServiceException('Failed to get text embedding: ' . $error);
        }

        return $response;
    }
}
