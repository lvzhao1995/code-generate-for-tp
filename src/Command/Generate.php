<?php

namespace Generate\Command;

use think\console\Command;
use think\console\Input;
use think\console\Output;

class Generate extends Command
{
    protected function configure()
    {
        $this->setName('generate')->setDescription('open code generator');
    }

    protected function execute(Input $input, Output $output)
    {
        $doc = '这是代码生成器所需文件。';

        file_put_contents(root_path() . 'generate.lock', $doc);
        $output->writeln('---------------------------------------');
        $output->writeln('Code generation tool url：/generate');
        $targetPath = root_path() . 'config' . DIRECTORY_SEPARATOR;
        if (!file_exists($targetPath)) {
            mkdir($targetPath, 0777, true);
        }
        if (file_exists($targetPath . 'curd.php')) {
            //配置文件已存在
            $file = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config.php');
            $output->writeln('---------------------------------------');
            $output->warning('The configuration file(' . realpath($targetPath . 'curd.php') . ') already exists. Please check ' . $file . ' to confirm if it is updated.');
        } else {
            copy(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config.php', $targetPath . 'curd.php');
        }
        $output->writeln('Please set "view_root" in the ".env" file.');
    }
}
