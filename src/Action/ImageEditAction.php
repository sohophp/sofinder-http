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

final class ImageEditAction implements MutationActionInterface
{
    public function __construct(
        private readonly ImageManager $images,
        private readonly MutationGuard $guard,
        private readonly FeaturePolicy $features,
        private readonly ImageMutationService $operations,
    ) {
    }

    public function endpoint(): string
    {
        return 'sofinder_image_edit';
    }

    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult
    {
        $this->assertAllowed($context, $input);
        $resource = $this->string($input['resource'] ?? null, 'Images');
        $path = $this->string($input['path'] ?? null);
        if (isset($input['actions'])) {
            $this->features->assertEnabled('image_processing');
            $actions = $this->actions($input['actions']);
            $save = $input['save'] ?? [];
            if (!is_array($save)) {
                throw new SoFinderException('Image save settings must be an object.', 'invalid_image_save', 400);
            }
            $result = $this->operations->process(
                $resource,
                $path,
                fn (): array => $this->images->applyActions($resource, $path, $actions, $save),
                context: $context,
            );

            return new EndpointResult(OperationResult::success($result)->jsonSerialize());
        }

        $this->features->assertEnabled('image_editing');
        $entry = $this->operations->process(
            $resource,
            $path,
            fn () => ($input['operation'] ?? 'transform') === 'crop'
                ? $this->images->crop(
                    $resource,
                    $path,
                    $this->integer($input['x'] ?? null, -1),
                    $this->integer($input['y'] ?? null, -1),
                    $this->integer($input['width'] ?? null),
                    $this->integer($input['height'] ?? null),
                )
                : $this->images->edit(
                    $resource,
                    $path,
                    $this->integer($input['rotation'] ?? null),
                    $this->integer($input['width'] ?? null),
                    $this->integer($input['height'] ?? null),
                ),
            context: $context,
        );

        return new EndpointResult(
            OperationResult::success(['entry' => $entry])->jsonSerialize(),
            headers: ['X-SoFinder-Deprecated-Fields' => 'operation,rotation,width,height,x,y'],
        );
    }

    public function assertAllowed(RequestContext $context, array $input = []): void
    {
        $this->guard->assertAllowed($context, $input);
    }

    /** @return list<array<string,mixed>> */
    private function actions(mixed $value): array
    {
        if (!is_array($value) || array_filter($value, static fn (mixed $action): bool => !is_array($action)) !== []) {
            throw new SoFinderException('Image actions must be an array of objects.', 'invalid_image_actions', 400);
        }

        return array_values($value);
    }

    private function string(mixed $value, string $default = ''): string
    {
        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : $default;
    }

    private function integer(mixed $value, int $default = 0): int
    {
        return is_int($value) || is_string($value) || is_float($value) ? (int) $value : $default;
    }
}
