<?php
declare(strict_types=1);
namespace SohoPHP\SoFinder\Http\Action;
use SohoPHP\SoFinder\Http\CachedFileResponseBuilder;
use SohoPHP\SoFinder\Http\EndpointActionInterface;
use SohoPHP\SoFinder\Http\StreamEndpointResult;
use SohoPHP\SoFinder\Image\ImageManager;
use SohoPHP\SoFinder\Value\RequestContext;
final class ImageThumbnailAction implements EndpointActionInterface
{
    public function __construct(private readonly ImageManager $images, private readonly CachedFileResponseBuilder $responses = new CachedFileResponseBuilder()) {}
    public function endpoint(): string { return 'sofinder_image_thumbnail'; }
    public function execute(RequestContext $context = new RequestContext(), array $input = []): StreamEndpointResult
    {
        $thumbnail = $this->images->thumbnail($this->string($context->query('resource'), 'Images'), $this->string($context->query('path')), $this->integer($context->query('width'), 240), $this->integer($context->query('height'), 180));
        return $this->responses->build($context, $thumbnail['path'], $thumbnail['mimeType'], 86400);
    }
    private function string(mixed $value, string $default = ''): string { return is_scalar($value) || $value instanceof \Stringable ? (string) $value : $default; }
    private function integer(mixed $value, int $default): int { return filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : $default; }
}
