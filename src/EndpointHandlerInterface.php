<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface EndpointHandlerInterface
{
    public function endpoint(): string;

    public function handle(ServerRequestInterface $request): ResponseInterface;
}
