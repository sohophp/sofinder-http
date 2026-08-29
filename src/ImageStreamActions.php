<?php
declare(strict_types=1);
namespace SohoPHP\SoFinder\Http;
use SohoPHP\SoFinder\Http\Action\ImageThumbnailAction;
use SohoPHP\SoFinder\Http\Action\ImageVariantAction;
final readonly class ImageStreamActions { public function __construct(public ImageThumbnailAction $thumbnail, public ImageVariantAction $variant) {} }
