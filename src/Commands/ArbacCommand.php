<?php

namespace Amrshah\Arbac\Commands;

use Illuminate\Console\Command;

class ArbacCommand extends Command
{
    public $signature = 'arbac';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
