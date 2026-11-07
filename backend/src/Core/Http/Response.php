<?php

declare(strict_types=1);

namespace ArtaPharma\Core\Http;

/**
 * JSON response value object. A tiny "View" transport for the API:
 * controllers return one of these and the front controller emits it.
 */
final class Response
{
    private ?string $filePath = null;
    private string $fileName = 'download';

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private mixed $payload,
        private int $status = 200,
        private array $headers = [],
    ) {
    }

    /**
     * Stream a file from disk as a download (used for letter attachments).
     */
    public static function file(string $path, string $downloadName, string $mime = 'application/octet-stream'): self
    {
        $response = new self(null, 200, ['Content-Type' => $mime]);
        $response->filePath = $path;
        $response->fileName = $downloadName;

        return $response;
    }

    public static function json(mixed $payload, int $status = 200): self
    {
        return new self($payload, $status);
    }

    public static function ok(mixed $data, string $message = ''): self
    {
        return new self(['success' => true, 'message' => $message, 'data' => $data], 200);
    }

    public static function created(mixed $data, string $message = 'با موفقیت ثبت شد.'): self
    {
        return new self(['success' => true, 'message' => $message, 'data' => $data], 201);
    }

    public static function noContent(): self
    {
        return new self(null, 204);
    }

    /** @param array<string, mixed> $errors */
    public static function error(string $message, int $status = 400, array $errors = []): self
    {
        return new self(['success' => false, 'message' => $message, 'errors' => $errors], $status);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function send(): void
    {
        // Binary file download.
        if ($this->filePath !== null) {
            if (!is_readable($this->filePath)) {
                // The file vanished between the controller check and now.
                http_response_code(404);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'فایل پیوست یافت نشد.', 'errors' => []], JSON_UNESCAPED_UNICODE);

                return;
            }
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
            header('Content-Disposition: attachment; filename="' . rawurlencode($this->fileName) . '"');
            header('Content-Length: ' . (string) filesize($this->filePath));
            readfile($this->filePath);

            return;
        }

        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        if ($this->status !== 204 && $this->payload !== null) {
            echo json_encode(
                $this->payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
            );
        }
    }
}
