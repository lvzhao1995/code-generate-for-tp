<?php

namespace Generate\Command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Env;

class Generate extends Command
{
    protected function configure()
    {
        $this->setName('generate')->setDescription('open code generator');
    }

    protected function execute(Input $input, Output $output)
    {
        //询问是否需要其他功能
        /*if (function_exists('system')) {
            $needPay = $output->confirm($input, 'Do you need payment in your project?', false);
            if ($needPay) {
                system('composer require hxc/qt-pay');
                $output->writeln('---------------------------------------');
                $output->writeln('Payment function has been introduced, please check the documentation for detailed usage.');
            }
            $needSms = $output->confirm($input, 'Do you need SMS in your project?', false);
            if ($needSms) {
                system('composer require hxc/qt-sms');
                $output->writeln('---------------------------------------');
                $output->writeln('SMS has been introduced, please see the documentation for detailed usage.');
            }
            $needQueue = $output->confirm($input, 'Do you need the queue in your project?', false);
            if ($needQueue) {
                system('composer require topthink/think-queue:~2.0');
                $output->writeln('---------------------------------------');
                $output->writeln('Queue function has been introduced, please see the documentation for detailed usage.');
            }
        } else {
            $output->writeln('---------------------------------------');
            $output->writeln('The system function has been disabled, please execute the following code according to your project needs:');
            $output->writeln('payment：composer require hxc/qt-pay');
            $output->writeln('sms：composer require hxc/qt-sms');
            $output->writeln('queue：composer require topthink/think-queue:~1.0');
        }*/
        $doc = '这是代码生成器所需文件。';

        file_put_contents(Env::get('root_path') . 'generate.lock', $doc);
        $output->writeln('---------------------------------------');
        $output->writeln('Code generation tool url：/generate');
        $targetPath = Env::get('root_path') . 'config/';
        if (!file_exists($targetPath)) {
            mkdir($targetPath, 0777, true);
        }
        if (file_exists($targetPath . 'curd.php')) {
            //配置文件已存在
            $file = realpath(__DIR__ . '/../config.php');
            $output->writeln('---------------------------------------');
            $output->warning('The configuration file(' . realpath($targetPath . 'curd.php') . ') already exists. Please check ' . $file . ' to confirm if it is updated.');
        } else {
            copy(__DIR__ . '/../config.php', $targetPath . 'curd.php');
        }
        if (!file_exists(Env::get('root_path') . '/env.php')) {
            file_put_contents(Env::get('root_path') . '/env.php', "<?php\nreturn [\n    'view_root' => '',\n    'api_token' => '',\n    'api_uri' => ''\n];");
        }
    }
}
