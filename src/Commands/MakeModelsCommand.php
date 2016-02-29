<?php

namespace Baris\Generator\Commands;

use Doctrine\Common\Inflector\Inflector;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class MakeModelsCommand extends Command
{

    private $tables = [];
    private $references = [];
    private $models = [];
    private $pivots = [];
    private $relations = [];
    private $timestamps = [];
    private $fillable = [];
    private $guarded = [];
    private $hidden = [];

    private $model_template;
    private $user_model_template;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'generate:models';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command automatically generates models with its relations according to DB';

    public function relation_maker($model_name, $relation_type){
        return
            '
    public function '.(($relation_type == 'belongsTo') ? strtolower($model_name) : Inflector::pluralize(strtolower($model_name))).'()
    {
        return $this->'.Inflector::camelize($relation_type).'(\'App\\'.$model_name.'\');
    }
';
    }

    public function create_models()
    {
        foreach ($this->models as $model) {
            $content = '';
            $template = null;


            if ($model == "User") {
                $template = $this->user_model_template;
            } else {
                $template = $this->model_template;
            }

            if (isset($this->timestamps[$model])) {
                $content .= 'public $timestamps = ' . ($this->timestamps[$model] ? "true" : "false") . ';' . PHP_EOL . PHP_EOL;
            }

            if (isset($this->fillable[$model])) {
                $tmp = null;
                foreach ($this->fillable[$model] as $item) {
                    if ($tmp == null)
                        $tmp = "'" . $item . "'";
                    else
                        $tmp .= ", '" . $item . "'";
                }
                $content .= "\t".'public $fillable = [' . $tmp . '];' . PHP_EOL . PHP_EOL;
            }

            if (isset($this->guarded[$model])) {
                $tmp = null;
                foreach ($this->guarded[$model] as $item) {
                    if ($tmp == null)
                        $tmp = "'" . $item . "'";
                    else
                        $tmp .= ", '" . $item . "'";
                }
                $content .= "\t".'public $guarded = [' . $tmp . '];' . PHP_EOL . PHP_EOL;
            }

            if (isset($this->hidden[$model])) {
                $tmp = null;
                foreach ($this->hidden[$model] as $item) {
                    if ($tmp == null)
                        $tmp = "'" . $item . "'";
                    else
                        $tmp = ", '" . $item . "'";
                }
                $content .= "\t".'public $hidden = [' . $tmp . '];' . PHP_EOL . PHP_EOL;
            }

            if(isset($this->relations[$model])){
                foreach ($this->relations[$model] as $relation => $targets) {
                    foreach ($targets as $target) {
                        $content .= $this->relation_maker($target, $relation).PHP_EOL.PHP_EOL;
                    }
                }
            }

            $template = str_replace('/*NAME*/', $model, $template);
            $template = str_replace('/*CONTENT*/', $content, $template);



            if ($this->files->exists($path = $this->getPath($model))) {
                $this->error($this->extends . ' for ' . $model . ' already exists!');
            }else{
                $this->makeDirectory($path);
                $this->files->put($path, $template);
                $this->info($this->extends . ' for ' . $model . ' created successfully.');
            }
        }
    }

    public function generate_properties()
    {
        foreach ($this->models as $model) {
            $columns = [];
            foreach (DB::select('DESCRIBE ' . Inflector::pluralize($model)) as $column) {
                $columns[] = $column->Field;
            }
            if (in_array('id', $columns)) {
                $this->guarded[$model][] = 'id';
            }
            if (in_array('password', $columns)) {
                $this->guarded[$model][] = 'password';
                $this->hidden[$model][] = 'password';
            }
            if (in_array('remember_token', $columns)) {
                $this->hidden[$model][] = 'remember_token';
            }
            if (in_array('created_at', $columns) && in_array('updated_at', $columns)) {
                $this->timestamps[$model] = true;
            } else {
                $this->timestamps[$model] = false;
            }
            foreach ($columns as $column) {
                if ($column == "id" || $column == "password")
                    continue;
                $this->fillable[$model][] = $column;
            }
        }
    }

    public function generate_models()
    {
        // Retrieve table list
        $tmp = DB::table('INFORMATION_SCHEMA.TABLES')->select('TABLE_NAME AS table')->where('TABLE_SCHEMA', '=', getenv('DB_DATABASE'))->get();
        foreach ($tmp as $item) {
            $this->tables[] = $item->table;
        }

        //Retrieve references
        $this->references = DB::table('INFORMATION_SCHEMA.KEY_COLUMN_USAGE')->select('TABLE_NAME', 'COLUMN_NAME', 'CONSTRAINT_NAME', 'REFERENCED_TABLE_NAME', 'REFERENCED_COLUMN_NAME')->WhereNotNull('REFERENCED_TABLE_NAME')->where('TABLE_SCHEMA', '=', getenv('DB_DATABASE'))->get();

        //Identify models and pivot tables and define their names
        foreach ($this->tables as $table) {
            if (strpos($table, "_") !== false) {
                $exploded = explode("_", $table);
                $tmp = end($exploded);
                if ($tmp == Inflector::pluralize($tmp)) {
                    $this->models[$table] = Inflector::classify(Inflector::singularize($table));
                } else {
                    $this->pivots[] = $table;
                }
            } else {
                $this->models[$table] = Inflector::classify(Inflector::singularize($table));
            }
        }

        //Identify model relations
        foreach ($this->references as $reference) {
            if (in_array($reference->TABLE_NAME, $this->pivots)) {
                $tmp = explode("_", $reference->TABLE_NAME);
                $other = null;
                if ($tmp[0] == Inflector::singularize($reference->REFERENCED_TABLE_NAME)) {
                    $other = $tmp[1];
                } else {
                    $other = $tmp[0];
                }
                $this->relations[Inflector::classify(Inflector::singularize($other))]["belongsToMany"][] = Inflector::classify(Inflector::singularize($reference->REFERENCED_TABLE_NAME));
            } else {
                $this->relations[Inflector::classify(Inflector::singularize($reference->TABLE_NAME))]["belongsTo"][] = Inflector::classify(Inflector::singularize($reference->REFERENCED_TABLE_NAME));
                $this->relations[Inflector::classify(Inflector::singularize($reference->REFERENCED_TABLE_NAME))]["hasMany"][] = Inflector::classify(Inflector::singularize($reference->TABLE_NAME));
            }
        }
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */

    public function fire()
    {
        $folder = __DIR__ . '/../stubs/';
        $this->model_template = $this->files->get($folder . "model.stub");
        $this->user_model_template = $this->files->get($folder . "user_model.stub");

        $this->generate_models();
        $this->generate_properties();
        $this->create_models();
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            //['example', InputArgument::REQUIRED, 'An example argument.'],
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            //['example', null, InputOption::VALUE_OPTIONAL, 'An example option.', null],
        ];
    }

    public function getStub()
    {
        return __DIR__ . '/../stubs/model.stub';
    }

}
