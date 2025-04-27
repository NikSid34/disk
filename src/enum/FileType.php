<?php


namespace app\enum;


enum FileType {
    case Image;
    case Video;
    case Pdf;
    case Word;
    case Excel;
    case PowerPoint;
    case Audio;
    case Archive;
    case Text;
    case Other;

    static function fromFileName(string $filename): self {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match($extension) {
            'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff', 'svg' => FileType::Image,
            'mp4', 'mov', 'avi', 'mkv', 'webm', 'flv', 'wmv' => FileType::Video,
            'pdf' => FileType::Pdf,
            'doc', 'docx' => FileType::Word,
            'xls', 'xlsx' => FileType::Excel,
            'ppt', 'pptx' => FileType::PowerPoint,
            'mp3', 'wav', 'ogg', 'flac', 'aac', 'm4a' => FileType::Audio,
            'zip', 'rar', '7z', 'tar', 'gz', 'bz2' => FileType::Archive,
            'txt', 'md' => FileType::Text,
            default => FileType::Other,
        };
    }
}