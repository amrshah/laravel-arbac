<?php

namespace Amrshah\Arbac\Commands;

use Illuminate\Console\GeneratorCommand;

class MakeRuleCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'arbac:make-rule';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new ARBAC attribute rule class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Attribute Rule';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub()
    {
        return __DIR__.'/stubs/attribute-rule.stub';
    }

    /**
     * Get the default namespace for the class.
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\\Arbac\\Rules';
    }
}
