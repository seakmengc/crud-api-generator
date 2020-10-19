<?php

namespace Seakmengc\CrudApiGenerator\Console\Commands;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Filesystem\FileExistsException;

class MakeCrudApi extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:crud-api {name} {--options=a : m=Model, c=Controller, r=Resource, f=FormRequest, p=Policy, a=All}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate api crud based on table. Note: should have table in advance.';

    protected $tableInfo;

    protected $stubsPath;

    protected $name;

    protected $dir;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->stubsPath = resource_path('stubs/crud-api/');

        // DB::connection()->getDoctrineSchemaManager()->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->name = last(explode('/', $this->argument('name')));
        if (strrpos($this->argument('name'), '/') === false)
            $this->dir = '';
        else
            $this->dir = substr($this->argument('name'), 0, strrpos($this->argument('name'), '/'));

        $this->tableInfo = DB::select('describe ' . $this->ask('What is your table name?'));

        if (Str::contains($this->option('options'), ['a', 'm']))
            $this->generateModel();

        if (Str::contains($this->option('options'), ['a', 'c']))
            $this->generateController();

        if (Str::contains($this->option('options'), ['a', 'f']))
            $this->generateFormRequest();

        if (Str::contains($this->option('options'), ['a', 'p']))
            $this->generatePolicy();

        if (Str::contains($this->option('options'), ['a', 'r']))
            $this->generateResource();
    }

    private function generateModel()
    {
        $content = str_replace(
            ['{{modelName}}', '{{fillables}}', '{{modelNamespace}}', '{{uses}}', '{{casts}}', '{{sorts}}', '{{belongTo}}'],
            [$this->name, $this->getAllFillableFields(), $this->getNamespace('model'), $this->getUses(), $this->getCasts(), $this->getSorts(), $this->getBelongTo()],
            file_get_contents($this->stubsPath . 'Model.stub')
        );

        $dir = config('crud-api.model_basepath') . $this->dir;

        $this->generate($dir, $content);

        $this->line("<fg=green>Model generated:\n{$dir}/{$this->name}.php</>\n");
    }

    private function generateController()
    {
        $content = str_replace(
            ['{{modelName}}', '{{modelCamelCaseName}}', '{{controllerNamespace}}', '{{getForCreate}}', '{{resourceNamespace}}', '{{modelNamespace}}'],
            [$this->name, Str::camel($this->name), $this->getNamespace('controller'), $this->getGetForCreateRelations(), $this->getNamespace('resource', true), $this->getNamespace('model', true)],
            file_get_contents($this->stubsPath . 'Controller.stub')
        );

        $dir = config('crud-api.controller_basepath') . $this->dir;

        $this->generate($dir, $content, 'Controller');

        $this->line("<fg=green>Controller generated:\n{$dir}/{$this->name}Controller.php</>\n");
    }

    private function generatePolicy()
    {
        $content = str_replace(
            ['{{modelName}}', '{{modelCamelCaseName}}', '{{modelNamespace}}'],
            [$this->name, Str::camel($this->name), $this->getNamespace('policy')],
            file_get_contents($this->stubsPath . 'Policy.stub')
        );

        $dir = config('crud-api.policy_basepath') . $this->dir;

        $this->generate($dir, $content, 'Policy');

        $this->line("<fg=green>Policy generated:\n{$dir}/{$this->name}Policy.php</>\n");
    }

    private function generateFormRequest()
    {
        $content = str_replace(
            ['{{modelName}}', '{{rules}}', '{{modelNamespace}}'],
            [$this->name, $this->getAllRules(), $this->getNamespace('request')],
            file_get_contents($this->stubsPath . 'Request.stub')
        );

        $dir = config('crud-api.request_basepath') . $this->dir;

        $this->generate($dir, $content, 'Request');

        $this->line("<fg=green>Form request generated:\n{$dir}/{$this->name}Request.php</>\n");
    }

    private function generateResource()
    {
        $content = str_replace(
            ['{{modelName}}', '{{columns}}', '{{modelNamespace}}'],
            [$this->name, $this->getAllResourceColumns(), $this->getNamespace('resource')],
            file_get_contents($this->stubsPath . 'Resource.stub')
        );

        $dir = config('crud-api.resource_basepath') . $this->dir;

        $this->generate($dir, $content, 'Resource');

        $this->line("<fg=green>Resource generated:\n{$dir}/{$this->name}Resource.php</>\n");
    }

    private function generate($fullDirPath, $content, $append = '')
    {
        $path = $fullDirPath . '/' . $this->name . $append . '.php';
        if (file_exists($path))
            throw new FileExistsException();

        if (!file_exists($fullDirPath))
            mkdir($fullDirPath, 0777, true);

        file_put_contents($path, $content);
    }

    private function getAllFillableFields()
    {
        $format = '';
        foreach ($this->filterFields(['id', 'created_at', 'updated_at', 'deleted_at']) as $column) {
            if (!in_array($column, ['id', 'created_at', 'updated_at', 'deleted_at']))
                $format .= "'$column',";
        }

        return $format;
    }

    private function getAllResourceColumns(): string
    {
        $allColumns = [];
        foreach ($this->filterFields(['created_at', 'updated_at', 'deleted_at']) as $column) {
            if (!in_array($column, ['created_at', 'updated_at', 'deleted_at']))
                $allColumns[$column] = '$this->' . $column;
        }

        $format = '';
        foreach ($allColumns as $key => $val)
            $format .= "'$key' => $val," . PHP_EOL;

        return $format;
    }

    private function getAllRules(): string
    {
        $allRules = [];
        foreach ($this->tableInfo as $column) {
            if (!in_array($column->Field, ['id', 'created_at', 'updated_at', 'deleted_at']))
                $allRules[$column->Field] =  '[\'' . implode("', '", $this->getRulesByColumn($column)) . '\']';
        }

        $format = '';
        foreach ($allRules as $key => $val)
            $format .= "'$key' => $val," . PHP_EOL;

        return $format;
    }

    private function getRulesByColumn($column)
    {
        $rules = collect('bail');
        if ($column->Null == 'NO')
            $rules->add('required');

        $rules->add($this->getRulesByType($column->Type));

        if (substr($column->Field, -3) === '_id')
            $rules->add('exists:' . Str::plural(substr($column->Field, 0, -3)) . ',id,deleted_at,null');

        return $rules->flatten(1)->toArray();
    }

    /**
     * currently does not support unsigned
     */
    private function getRulesByType($type)
    {
        $rules = collect();

        $mainType = explode(' ', $type)[0];
        if (Str::startsWith($mainType, ['tinyint', 'smallint', 'int', 'bigint']))
            $rules->add('integer');
        elseif (Str::startsWith($mainType, 'json'))
            $rules->add('json');
        elseif (Str::startsWith($mainType, 'enum')) {
            $rules->add('in:' . str_replace('\'', '', substr($mainType, strpos($mainType, '(') + 1, -1)));
        } elseif (Str::startsWith($mainType, 'varchar')) {
            $rules->add('string');
            $rules->add('max:' . substr($mainType, strpos($mainType, '(') + 1, -1));
        } elseif (Str::startsWith($mainType, 'timestamp')) {
            $rules->add('date');
        }

        return $rules;
    }

    /**
     * get Use trait in model
     *
     * @return string
     */
    private function getUses()
    {
        if (collect($this->tableInfo)->firstWhere('Field', 'deleted_at'))
            return 'use SoftDeletes;';
    }

    private function getCasts()
    {
        $casts = [];
        foreach ($this->tableInfo as $column) {
            if (in_array($column->Field, ['created_at', 'updated_at', 'deleted_at']))
                continue;

            $mainType = explode(' ', $column->Type)[0];
            if (Str::startsWith($mainType, ['tinyint', 'smallint', 'int', 'bigint']))
                $casts[$column->Field] = 'integer';
            elseif (Str::startsWith($mainType, 'json'))
                $casts[$column->Field] = 'collect';
            elseif (Str::startsWith($mainType, 'timestamp'))
                $casts[$column->Field] = 'datetime';
        }

        return $this->trimArrayVarExport($casts);
    }

    private function getSorts()
    {
        $format = '';
        foreach ($this->filterFields() as $column)
            $format .= "'$column',";

        return $format;
    }

    private function getBelongTo()
    {
        $format = '';
        foreach ($this->filterFields(['id', 'created_at', 'deleted_at', 'updated_at']) as $column) {
            if (substr($column, -3) === '_id') {
                $camel = Str::camel(substr($column, 0, -3));
                $studly = ucfirst($camel);
                $format .= "public function {$camel}(): BelongsTo {
                    return \$this->belongsTo({$studly}::class);
                }
                
                ";
            }
        }

        return $format;
    }

    private function getGetForCreateRelations()
    {
        $format = '';
        foreach ($this->filterFields(['id', 'created_at', 'deleted_at', 'updated_at']) as $column) {
            if (substr($column, -3) === '_id') {
                $snake = substr($column, 0, -3);
                $studly = Str::studly($snake);
                $format .= "'{$snake}' => {$studly}::getForCreate()," . PHP_EOL;
            }
        }

        return $format;
    }

    private function getNamespace($type, $append = false)
    {
        static $dir = null;
        if (!$dir) $dir = str_replace('/', '\\', $this->dir);

        $res = rtrim(str_replace('/', '\\', ucfirst(config("crud-api.{$type}_basepath"))) . $dir, '\\');
        if ($append)
            $res .= '\\' . $this->name . ($type === 'model' ? '' : ucfirst($type));

        return $res;
    }

    private function trimArrayVarExport(array &$data)
    {
        return '[' . substr(var_export($data, true), 8, -3) . ']';
    }

    private function filterFields($without = [])
    {
        return collect($this->tableInfo)->pluck('Field')
            ->filter(fn ($val) => !in_array($val, $without));
    }

    // private function getModelsPath()
    // {
    //     return app_path('Models/');
    // }

    // private function getControllersPath()
    // {
    //     return app_path('Http/Controllers/Api/v1/');
    // }

    // private function getResourcesPath()
    // {
    //     return app_path('Http/Resources/');
    // }

    // private function getRequestsPath()
    // {
    //     return app_path('Http/Requests/');
    // }

    // private function getPoliciesPath()
    // {
    //     return app_path('Policies/');
    // }
}