<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use Psr\Http\Message\UploadedFileInterface;
use SohoPHP\SoFinder\Exception\SoFinderException;

/** A framework-neutral, single-consumer uploaded file passed into shared actions. */
final readonly class UploadedFileInput
{
    private \Closure $opener;

    /** @param callable():mixed $opener */
    public function __construct(
        public string $clientName,
        public int $size,
        public int $error,
        callable $opener,
    ) {
        $this->opener = \Closure::fromCallable($opener);
    }

    public static function fromPath(string $path, string $clientName, int $size, int $error = UPLOAD_ERR_OK): self
    {
        return new self($clientName, $size, $error, static fn () => @fopen($path, 'rb'));
    }

    public static function fromPsr(UploadedFileInterface $file): self
    {
        return new self(
            $file->getClientFilename() ?? '',
            $file->getSize() ?? 0,
            $file->getError(),
            static function () use ($file) {
                $source = $file->getStream();
                $resource = $source->detach();
                if (is_resource($resource)) {
                    return $resource;
                }
                $temporary = fopen('php://temp', 'w+b');
                if ($temporary === false) {
                    return false;
                }
                $source->rewind();
                while (!$source->eof()) {
                    $chunk = $source->read(65_536);
                    if ($chunk === '') {
                        break;
                    }
                    fwrite($temporary, $chunk);
                }
                rewind($temporary);

                return $temporary;
            },
        );
    }

    /** @return resource */
    public function open()
    {
        $stream = ($this->opener)();
        if (!is_resource($stream)) {
            throw new SoFinderException('Unable to read the uploaded file.', 'invalid_upload', 400);
        }
        $metadata = stream_get_meta_data($stream);
        if ($metadata['seekable'] !== true) {
            $temporary = fopen('php://temp', 'w+b');
            if ($temporary === false || stream_copy_to_stream($stream, $temporary) === false) {
                fclose($stream);
                if (is_resource($temporary)) {
                    fclose($temporary);
                }
                throw new SoFinderException('Unable to prepare the uploaded file.', 'invalid_upload', 400);
            }
            fclose($stream);
            rewind($temporary);
            $stream = $temporary;
        }

        return $stream;
    }
}
