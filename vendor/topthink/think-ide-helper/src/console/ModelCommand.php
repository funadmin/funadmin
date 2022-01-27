<?php

namespace think\ide\console;

use Ergebnis\Classy\Constructs;
use Exception;
use think\console\Command;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\ide\ModelGenerator;

class ModelCommand extends Command
{
    protected $dirs = [];

    protected $properties = [];

    protected $methods = [];

    protected $overwrite = false;

    protected $reset = false;

    protected function configure()
    {
        $this->setName("ide-helper:model")
             ->addArgument('model', Argument::OPTIONAL | Argument::IS_ARRAY, 'Which models to include', [])
             ->addOption('dir', 'D', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, 'The model dir', [])
             ->addOption('ignore', 'I', Option::VALUE_OPTIONAL, 'Which models to ignore', '')
             ->addOption('reset', 'R', Option::VALUE_NONE, 'Remove the original phpdocs instead of appending')
             ->addOption('overwrite', 'O', Option::VALUE_NONE, 'Overwrite the phpdocs');
    }

    public function handle()
    {
        $this->dirs = array_merge(['model'], $this->input->getOption('dir'));

        $model  = $this->input->getArgument('model');
        $ignore = $this->input->getOption('ignore');

        $this->overwrite = $this->input->getOption('overwrite');

        $this->reset = $this->input->getOption('reset');

        $this->generateDocs($model, $ignore);
    }

    /**
     * 生成注释
     * @param        $loadModels
     * @param string $ignore
     */
    protected function generateDocs($loadModels, $ignore = "")
    {
        if (empty($loadModels)) {
            $models = $this->loadModels();
        } else {
            $models = [];
            foreach ($loadModels as $model) {
                $models = array_merge($models, explode(',', $model));
            }
        }

        $ignore = explode(',', $ignore);

        foreach ($models as $name) {
            if (in_array($name, $ignore)) {
                if ($this->output->getVerbosity() >= Output::VERBOSITY_VERBOSE) {
                    $this->output->comment("Ignoring model '$name'");
                }
                continue;
            }

            $this->properties = [];
            $this->methods    = [];

            if (class_exists($name)) {
                $generator = new ModelGenerator($this->app, $this->output, $name, $this->reset, $this->overwrite);
                try {
                    $generator->generate();
                    $ignore[] = $name;
                } catch (Exception $exception) {
                    $this->output->error("Exception: " . $exception->getMessage() . "\nCould not analyze class $name.");
                }
            }
        }
    }

    /**
     * 自动获取模型
     * @return array
     */
    protected function loadModels()
    {
        $models = [];
        foreach ($this->dirs as $dir) {
            $dir = $this->app->getBasePath() . $dir;
            if (file_exists($dir)) {
                $constructs = Constructs::fromDirectory($dir);

                foreach ($constructs as $construct) {
                    $models[] = $construct->name();
                }
            }
        }
        return $models;
    }
}
