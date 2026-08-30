<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use SohoPHP\SoFinder\Http\Action\CreateFolderAction;
use SohoPHP\SoFinder\Http\Action\DeleteAction;
use SohoPHP\SoFinder\Http\Action\RenameAction;
use SohoPHP\SoFinder\Http\Action\TransferAction;

final class FileMutationActions
{
    public function __construct(
        public readonly CreateFolderAction $createFolder,
        public readonly RenameAction $rename,
        public readonly TransferAction $copy,
        public readonly TransferAction $move,
        public readonly DeleteAction $delete,
    ) {
        if ($copy->endpoint() !== 'sofinder_api_copy' || $move->endpoint() !== 'sofinder_api_move') {
            throw new \InvalidArgumentException('File mutation transfer actions are assigned to the wrong operation.');
        }
    }
}
