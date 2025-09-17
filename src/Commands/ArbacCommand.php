<?php

namespace Amrshah\Arbac\Commands;

use Illuminate\Console\Command;

class ArbacCommand extends Command
{
    public $signature = 'arbac';

    public $description = 'Arbac command';

    public function handle(): int
    {
        $this->comment('Welcome to the ARBAC package!');

        return self::SUCCESS;
    }
}
