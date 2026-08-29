<?php
declare(strict_types=1);
namespace SohoPHP\SoFinder\Http\Action;
use SohoPHP\SoFinder\Http\CachedFileResponseBuilder;
use SohoPHP\SoFinder\Http\EndpointActionInterface;
use SohoPHP\SoFinder\Http\StreamEndpointResult;
use SohoPHP\SoFinder\Image\ImageManager;
use SohoPHP\SoFinder\Value\RequestContext;
final readonly class ImageVariantAction implements EndpointActionInterface
{
    public function __construct(private ImageManager $images, private CachedFileResponseBuilder $responses = new CachedFileResponseBuilder()) {}
    public function endpoint(): string { return 'sofinder_image_variant'; }
    public function execute(RequestContext $context = new RequestContext(), array $input = []): StreamEndpointResult
    {
        $variant = $this->images->variant($this->string($context->query('resource'), 'Images'), $this->string($context->query('path')), $this->integer($context->query('width')), strtolower($this->string($context->query('format'), 'original')));
        return $this->responses->build($context, $variant['path'], $variant['mimeType'], 2592000);
    }
    private function string(mixed $value, string $default = ''): string { return is_scalar($value) || $value instanceof \Stringable ? (string) $value : $default; }
    private function integer(mixed $value, int $default = 0): int { return filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : $default; }
}
