<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use SohoPHP\SoFinder\Http\Action\DeleteTrashAction;
use SohoPHP\SoFinder\Http\Action\RestoreTrashAction;
use SohoPHP\SoFinder\Http\Action\TrashListAction;

final readonly class TrashActions
{
    public function __construct(
        public TrashListAction $list,
        public RestoreTrashAction $restore,
        public DeleteTrashAction $delete,
    ) {
    }
}
