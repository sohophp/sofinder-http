<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use SohoPHP\SoFinder\Http\Action\DeleteTrashAction;
use SohoPHP\SoFinder\Http\Action\RestoreTrashAction;
use SohoPHP\SoFinder\Http\Action\TrashListAction;

final class TrashActions
{
    public function __construct(
        public readonly TrashListAction $list,
        public readonly RestoreTrashAction $restore,
        public readonly DeleteTrashAction $delete,
    ) {
    }
}
