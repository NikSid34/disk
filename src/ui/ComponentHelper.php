<?php


namespace app\ui;


use DateTimeInterface;


class ComponentHelper {
    public function e(mixed $value): string {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function formatFileSize(int|float|string|null $bytes, int $decimals = 1): string {
        if (!is_numeric($bytes)) {
            return '0 B';
        }

        $bytes = (float) $bytes;
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = (int) floor(log($bytes, 1024));
        $factor = max(0, min($factor, count($units) - 1));

        return sprintf("%.{$decimals}f", $bytes / (1024 ** $factor)) . ' ' . $units[$factor];
    }

    public function fileIcon(string $filename): string {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf' => 'bi-file-earmark-pdf-fill text-danger',
            'doc', 'docx' => 'bi-file-earmark-word-fill text-primary',
            'xls', 'xlsx' => 'bi-file-earmark-excel-fill text-success',
            'ppt', 'pptx' => 'bi-file-earmark-ppt-fill text-warning',
            'mp4', 'avi', 'mov', 'mkv' => 'bi-file-earmark-play-fill text-dark',
            'mp3', 'wav', 'flac' => 'bi-file-earmark-music-fill text-info',
            'zip', 'rar', '7z' => 'bi-file-earmark-zip-fill text-muted',
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg' => 'bi-file-earmark-image-fill text-warning',
            default => 'bi-file-earmark-fill text-secondary',
        };
    }

    public function formatDate(mixed $value, string $format = 'd.m.Y H:i'): string {
        if ($value instanceof DateTimeInterface) {
            return $value->format($format);
        }

        if (is_string($value) && $value !== '') {
            $timestamp = strtotime($value);
            if ($timestamp !== false) {
                return date($format, $timestamp);
            }
        }

        return '';
    }

    public function buildUrl(string $path, array $query = []): string {
        $query = array_filter($query, static fn(mixed $value): bool => $value !== null && $value !== '');
        if ($query === []) {
            return $path;
        }

        return $path . '?' . http_build_query($query);
    }
}
