<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Contract\ImageCapabilityProviderInterface;
use SohoPHP\SoFinder\Exception\SoFinderException;
use SohoPHP\SoFinder\FileManager;
use SohoPHP\SoFinder\Http\CompatibleUploadGuard;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Http\GuardedActionInterface;
use SohoPHP\SoFinder\Http\StreamEndpointResult;
use SohoPHP\SoFinder\Http\UploadedFileInput;
use SohoPHP\SoFinder\Upload\UploadNamePolicy;
use SohoPHP\SoFinder\Value\RequestContext;

final class QuickUploadAction implements GuardedActionInterface
{
    public function __construct(
        private readonly FileManager $files,
        private readonly CompatibleUploadGuard $guard,
        private readonly UploadNamePolicy $names,
        private readonly ?ImageCapabilityProviderInterface $imageCapabilities = null,
        private readonly bool $overwriteOnUpload = false,
    ) {
    }

    public function endpoint(): string
    {
        return 'sofinder_quick_upload';
    }

    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult|StreamEndpointResult
    {
        $this->assertAllowed($context, $input);
        $uploaded = $input['upload'] ?? null;
        if (!$uploaded instanceof UploadedFileInput || $uploaded->error !== UPLOAD_ERR_OK) {
            throw new SoFinderException('No valid uploaded file was received.', 'invalid_upload', 400);
        }
        $function = $this->integer($context->query('CKEditorFuncNum', $input['CKEditorFuncNum'] ?? 0));
        $expectsJson = strtolower($this->string($context->query('responseType'))) === 'json'
            || strtolower($context->header('X-Requested-With')) === 'xmlhttprequest'
            || str_contains(strtolower($context->header('Accept')), 'application/json')
            || $function <= 0;
        $resource = $this->string($context->query('type'), 'Files');
        $selection = strtolower($this->string($context->query('selection'), $resource === 'Images' ? 'image' : 'file'));
        $stream = $uploaded->open();
        $sample = fread($stream, 262_144);
        if ($sample === false || rewind($stream) === false) {
            fclose($stream);
            throw new SoFinderException('Unable to inspect the uploaded file.', 'invalid_upload', 400);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($sample) ?: 'application/octet-stream';
        if ($selection === 'image' && ($this->imageCapabilities === null || !$this->imageCapabilities->isWebEmbeddable($mime))) {
            fclose($stream);

            return $this->failure($function, $expectsJson, 'image_not_web_embeddable', 'This image format cannot be embedded directly in a web page.');
        }
        $name = $this->names->normalize($uploaded->clientName);
        try {
            $entry = $this->files->upload(
                $resource,
                $this->string($context->query('currentFolder')),
                $name,
                $uploaded->size,
                $stream,
                $this->overwriteOnUpload,
                !$this->overwriteOnUpload,
            );
        } finally {
            fclose($stream);
        }
        $url = $entry->url ?? '';
        $renamed = $entry->name !== $name;
        $message = $renamed ? sprintf('A file with the same name already exists. The uploaded file was renamed to "%s".', $entry->name) : '';
        if ($expectsJson) {
            $payload = ['uploaded' => 1, 'fileName' => $entry->name, 'url' => $url];
            if ($renamed) {
                $payload['error'] = ['message' => $message];
            }

            return new EndpointResult($payload);
        }

        return $this->script($function, $url, $message);
    }

    public function assertAllowed(RequestContext $context, array $input = []): void
    {
        $this->guard->assertAllowed($context, $input);
    }

    private function failure(int $function, bool $expectsJson, string $code, string $message): EndpointResult|StreamEndpointResult
    {
        return $expectsJson
            ? new EndpointResult(['uploaded' => 0, 'error' => ['code' => $code, 'message' => $message]], 415)
            : $this->script($function, '', $message, 415);
    }

    private function script(int $function, string $url, string $message, int $status = 200): StreamEndpointResult
    {
        $payload = json_encode([$function, $url, $message], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $nonce = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
        $html = sprintf('<script nonce="%s">(function(){var p=%s;window.parent.CKEDITOR.tools.callFunction(p[0],p[1],p[2]);})();</script>', $nonce, $payload);
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new \RuntimeException('Unable to create the compatible upload response.');
        }
        fwrite($stream, $html);
        rewind($stream);

        return new StreamEndpointResult($stream, $status, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => sprintf("default-src 'none'; script-src 'nonce-%s'; frame-ancestors 'self'; base-uri 'none'", $nonce),
        ]);
    }

    private function string(mixed $value, string $default = ''): string
    {
        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : $default;
    }

    private function integer(mixed $value): int
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : 0;
    }
}
