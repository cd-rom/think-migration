<?php
// +----------------------------------------------------------------------
// | TopThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016 http://www.topthink.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangyajun <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think\migration\command\migrate;

use Phinx\Util\Util;
use think\console\input\Argument as InputArgument;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\migration\command\Migrate;

class Create extends Migrate
{

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('migrate:create')
            ->setDescription('Create a new migration')
            ->addArgument('name', InputArgument::REQUIRED, 'What is the name of the migration?')
            ->setHelp(sprintf('%sCreates a new database migration%s', PHP_EOL, PHP_EOL));
    }

    /**
     * Create the new migration.
     *
     * @param Input $input
     * @param Output $output
     * @return void
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    protected function execute(Input $input, Output $output)
    {
        $path = $this->getPath();

        if (!file_exists($path)) {
            if ($this->output->confirm($this->input, 'Create migrations directory? [y]/n')) {
                mkdir($path, 0755, true);
            }
        }

        $this->verifyMigrationDirectory($path);

        $path = realpath($path);
        $className = $input->getArgument('name');

        if (!Util::isValidPhinxClassName($className)) {
            throw new \InvalidArgumentException(sprintf('The migration class name "%s" is invalid. Please use CamelCase format.', $className));
        }

        if (!Util::isUniqueMigrationClassName($className, $path)) {
            throw new \InvalidArgumentException(sprintf('The migration class name "%s" already exists', $className));
        }

        // Compute the file path
        $fileName = Util::mapClassNameToFileName($className);
        $filePath = $path . DS . $fileName;

        if (is_file($filePath)) {
            throw new \InvalidArgumentException(sprintf('The file "%s" already exists', $filePath));
        }

        // Verify that the template creation class (or the aliased class) exists and that it implements the required interface.
        $aliasedClassName = null;

        // Load the alternative template if it is defined.
        $contents = file_get_contents($this->getTemplate());

        //  Generate the table construct by exist table
        $changeCode = $this->getDbConstruct($className);

        // inject the class names appropriate to this migration
        $contents = strtr($contents, [
            '$className' => $className,
            '$changeCode' => $changeCode,
        ]);

        if (false === file_put_contents($filePath, $contents)) {
            throw new \RuntimeException(sprintf('The file "%s" could not be written to', $path));
        }

        $output->writeln('<info>created</info> .' . str_replace(getcwd(), '', $filePath));
    }

    protected function getTemplate()
    {
        return __DIR__ . '/../stubs/migrate.stub';
    }

    protected function getDbConstruct($table_name)
    {
        $return = "";
        $config = $this->getDbConfig();
        if ($config) {
            $full_table_name = $config['table_prefix'] . $table_name;
            $table_exists = Db::query("SHOW TABLES LIKE '{$full_table_name}'");
            if ($table_exists) {
                $status = "SHOW TABLE STATUS WHERE NAME = '{$full_table_name}'";
                $construct = Db::query("SHOW COLUMNS FROM `{$full_table_name}`");
                $index = Db::query("SHOW INDEX FROM `{$full_table_name}`");

                $engine = isset($status['Engine']) ? $status['Engine'] : 'Innodb';

                $result = "\$table = \$this->table('{$table_name}',array('engine'=>'{$engine}'));\n";
                foreach ($construct as $key => $item) {
                    $field = isset($item['Field']) ? $item['Field'] : "";
                    $field_type = isset($item['Type']) ? $item['Type'] : "";
                    $is_null = isset($item['Null']) ? $item['Null'] : "";
                    $field_default = isset($item['Default']) ? $item['Default'] : null;
                    $field_extra = isset($item['Extra']) ? $item['Extra'] : "";
                    if ($field) {
                        if ($key == 0) {
                            $result .= "\$table";
                        }
                        $column_type = "";

                        if(strpos($field_type,"(") !== false){
                            $column_type = "";
                        }else{
                            $column_type = $field_type;
                        }
                        $result .= "->addColumn('{$column_type}','',array(''))\n";
                    }
                }
            }
        }
        return $return;
    }

}
