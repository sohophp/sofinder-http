<?php
declare(strict_types=1);
namespace SohoPHP\SoFinder\Http;
use SohoPHP\SoFinder\Http\Action\ImageThumbnailAction;
use SohoPHP\SoFinder\Http\Action\ImageVariantAction;
final class ImageStreamActions { public function __construct(public readonly ImageThumbnailAction $thumbnail, public readonly ImageVariantAction $variant) {} }
