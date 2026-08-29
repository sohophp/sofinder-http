<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Exception\SoFinderException;
use SohoPHP\SoFinder\Feature\FeaturePolicy;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Http\ImageMutationService;
use SohoPHP\SoFinder\Http\MutationActionInterface;
use SohoPHP\SoFinder\Http\MutationGuard;
use SohoPHP\SoFinder\Image\ImageManager;
use SohoPHP\SoFinder\Value\OperationResult;
use SohoPHP\SoFinder\Value\RequestContext;

final readonly class ImageBatchAction implements MutationActionInterface
{
    public function __construct(
        private ImageManager $images,
        private MutationGuard $guard,
        private FeaturePolicy $features,
        private ImageMutationService $operations,
    ) {
    }

    public function endpoint(): string
    {
        return 'sofinder_image_batch';
    }

    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult
    {
        $this->assertAllowed($context, $input);
        $this->features->assertEnabled('image_processing');
        if (!is_array($input['paths'] ?? null) || !is_array($input['actions'] ?? null) || !is_array($input['save'] ?? [])) {
            throw new SoFinderException('Batch image paths, actions and save settings are invalid.', 'invalid_image_batch', 400);
        }
        if (array_filter($input['actions'], static fn (mixed $action): bool => !is_array($action)) !== []) {
            throw new SoFinderException('Image actions must be an array of objects.', 'invalid_image_actions', 400);
        }
        $resource = $this->string($input['resource'] ?? null, 'Images');
        $paths = array_map(fn (mixed $path): string => $this->string($path), array_values($input['paths']));
        $actions = array_values($input['actions']);
        $save = $input['save'] ?? [];
        $result = $this->operations->process(
            $resource,
            $paths[0] ?? '',
            fn (): array => $this->images->applyBatch($resource, $paths, $actions, $save),
            ['batchItems' => count($paths)],
            $context,
        );

        return new EndpointResult(OperationResult::success($result)->jsonSerialize());
    }

    public function assertAllowed(RequestContext $context, array $input = []): void
    {
        $this->guard->assertAllowed($context, $input);
    }

    private function string(mixed $value, string $default = ''): string
    {
        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : $default;
    }
}
