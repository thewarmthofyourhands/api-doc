<?php

declare(strict_types=1);

namespace Eva\ApiDoc;

use Eva\Console\ArgvInput;

class DocGenerateCommand
{
    public function __construct(protected Generator $generator) {}

    public function execute(ArgvInput $argvInput): void
    {
        $this->generator->generateApiDto();
    }
}
