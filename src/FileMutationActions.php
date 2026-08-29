<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use SohoPHP\SoFinder\Http\Action\CreateFolderAction;
use SohoPHP\SoFinder\Http\Action\DeleteAction;
use SohoPHP\SoFinder\Http\Action\RenameAction;
use SohoPHP\SoFinder\Http\Action\TransferAction;

final readonly class FileMutationActions
{
    public function __construct(
        public CreateFolderAction $createFolder,
        public RenameAction $rename,
        public TransferAction $copy,
        public TransferAction $move,
        public DeleteAction $delete,
    ) {
        if ($copy->endpoint() !== 'sofinder_api_copy' || $move->endpoint() !== 'sofinder_api_move') {
            throw new \InvalidArgumentException('File mutation transfer actions are assigned to the wrong operation.');
        }
    }
}
