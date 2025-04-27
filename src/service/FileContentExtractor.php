<?php


namespace app\service;


use app\Application;
use app\entity\File;
use app\enum\FileType;
use Exception;
use PhpOffice\PhpWord\IOFactory;
use Smalot\PdfParser\Parser;


class FileContentExtractor {
    private const DEFAULT_ANTIWORD_MAPPING = 'UTF-8.txt';

    public function __construct(
            private readonly ?string $antiwordPath = null,
            private readonly string $antiwordMapping = self::DEFAULT_ANTIWORD_MAPPING
    ) {
    }

    /**
     * @throws ServiceException
     */
    public function extract(File $file): ?string {
        return match ($file->fileType) {
            FileType::Word => $this->extractTextFromWord($file->fileObject->storagePath),
            FileType::Pdf => $this->extractTextFromPdf($file->fileObject->storagePath),
            FileType::Text => $this->extractTextFromTextFile($file->fileObject->storagePath),
            default => null,
        };
    }

    /**
     * @throws ServiceException
     */
    private function extractTextFromWord(string $filePath): string {
        if (!is_readable($filePath)) {
            throw new ServiceException('Word file is not readable');
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($ext) {
            'docx' => $this->extractFromDocx($filePath),
            'doc' => $this->extractFromDoc($filePath),
            default => throw new ServiceException('Unsupported Word format: ' . $ext),
        };
    }

    /**
     * @throws ServiceException
     */
    private function extractFromDocx(string $filePath): string {
        try {
            $phpWord = IOFactory::load($filePath);
        } catch (Exception $e) {
            throw new ServiceException(
                    "Failed to load DOCX: {$e->getMessage()}",
                    previous: $e
            );
        }

        return $this->extractFromElements($phpWord->getSections());
    }

    private function extractFromElements($elements): string {
        $text = '';
        foreach ($elements as $element) {
            if (method_exists($element, 'getText')) {
                $text .= $element->getText() . " ";
            }

            if (method_exists($element, 'getElements')) {
                $text .= $this->extractFromElements($element->getElements());
            }
        }

        return $text;
    }

    /**
     * @throws ServiceException
     */
    private function extractFromDoc(string $filePath): string {
        if (!is_readable($filePath)) {
            throw new ServiceException('DOC file is not readable');
        }

        $antiword = $this->resolveAntiwordPath();
        if (!is_file($antiword)) {
            throw new ServiceException('Antiword executable not found: ' . $antiword);
        }

        [$code, $stdout, $stderr] = $this->runAntiword($antiword, $this->resolveAntiwordMapping(), $filePath);
        $text = trim($stdout);
        if ($code === 0 && $text !== '') {
            return $text;
        }

        $details = trim($stderr . "\n" . $stdout);
        throw new ServiceException('Failed to extract DOC file' . ($details !== '' ? ': ' . $details : '.'));
    }

    private function resolveAntiwordPath(): string {
        $path = getenv('SIMPLEDISK_ANTIWORD_PATH');
        if (is_string($path) && trim($path) !== '') {
            return trim($path);
        }

        if ($this->antiwordPath !== null && trim($this->antiwordPath) !== '') {
            return trim($this->antiwordPath);
        }

        $defaultPath = Application::getDocumentRoot()
                . DIRECTORY_SEPARATOR
                . '..'
                . DIRECTORY_SEPARATOR
                . 'antiword'
                . DIRECTORY_SEPARATOR
                . 'bin'
                . DIRECTORY_SEPARATOR
                . 'antiword.exe';
        $realPath = realpath($defaultPath);

        return $realPath !== false ? $realPath : $defaultPath;
    }

    private function resolveAntiwordMapping(): string {
        $mapping = getenv('SIMPLEDISK_ANTIWORD_MAPPING');
        if (is_string($mapping) && trim($mapping) !== '') {
            return trim($mapping);
        }

        $mapping = trim($this->antiwordMapping);

        return $mapping !== '' ? $mapping : self::DEFAULT_ANTIWORD_MAPPING;
    }

    /**
     * @return array{0: int, 1: string, 2: string}
     *
     * @throws ServiceException
     */
    protected function runAntiword(string $antiword, string $mapping, string $filePath): array {
        $descriptorSpec = array(
                0 => array('pipe', 'r'),
                1 => array('pipe', 'w'),
                2 => array('pipe', 'w'),
        );
        $cwd = dirname($antiword);
        $env = getenv();
        $env = is_array($env) ? $env : array();
        if (!isset($env['HOME']) || trim((string) $env['HOME']) === '') {
            $env['HOME'] = dirname($cwd);
        }

        $process = @proc_open(
                array($antiword, '-m', $mapping, $filePath),
                $descriptorSpec,
                $pipes,
                $cwd,
                $env
        );

        if (!is_resource($process)) {
            throw new ServiceException('Failed to start antiword process');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $code = proc_close($process);

        return array(
                $code,
                is_string($stdout) ? $stdout : '',
                is_string($stderr) ? $stderr : '',
        );
    }

    /**
     * @throws ServiceException
     */
    private function extractTextFromPdf(string $filePath): string {
        if (!is_readable($filePath)) {
            throw new ServiceException('PDF file is not readable');
        }

        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();
        } catch (Exception $e) {
            throw new ServiceException(message: "Failed to extract text from PDF: {$e->getMessage()}", previous: $e);
        }

        return trim($text);
    }

    /**
     * @throws ServiceException
     */
    private function extractTextFromTextFile(string $filePath): string {
        if (!is_readable($filePath)) {
            throw new ServiceException('Text file is not readable');
        }

        $text = file_get_contents($filePath);
        if (!is_string($text)) {
            throw new ServiceException('Failed to read text file');
        }

        return trim($text);
    }
}
