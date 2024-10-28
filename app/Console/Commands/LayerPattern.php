<?php

namespace App\Console\Commands;

use Error;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class LayerPattern extends Command
{

    protected Filesystem $files;
    protected $signature = 'make:layer {name} {model}';
    protected $description = 'Cria a base dos contratos, repositórios e services';
    protected $name;
    protected $modelName;
    protected $rootPath;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }


    public function handle()
    {
        // Adiciona letra maiuscula aos argumentos declarados.
        $this->name = ucfirst($this->argument('name'));
        $this->modelName = ucfirst($this->argument('model'));

        // Pega o diretório que vai ficar o layer.
        $this->rootPath = base_path("domain/{$this->name}/");

        // Verifica se existe o local da base ou se a model não existe.
        if (($model = !file_exists("app/Models/$this->modelName.php")) || file_exists($this->rootPath)) {
            if($model){
                return $this->error('Model not exist');
            }
            return $this->info('base already exists!');
        }

        // Cria a pasta base do layer.
        mkdir($this->rootPath,0777,true);

        $methods = [
            'Repositories' => 'Repository',
            'Services' => '',
            'DTOs' => 'DTO'
        ];

        // Faz a criação dos metodos da base.
        foreach ($methods as $folderSuffix => $fileSuffix) {
            $this->createMethod($folderSuffix,$fileSuffix);
        }

        // Importa e adiciona ao register do AppServiceProvider.
        $this->AddRegisterServiceProvider();

    }

    private function createMethod($folderSuffix,$fileSuffix)
    {
        // Diretório do repositório.
        $path = $this->rootPath . "$folderSuffix";

        // Verifica se diretório existe.
        $this->checkDirectory($path);

        // Verifica se arquivo existe, se não cria .
        $filepath = $path . "/{$this->name}$fileSuffix.php";
        if(!file_exists($filepath)){
            return $this->createFile($filepath, $folderSuffix);
        }

        $this->info("{$this->name}$fileSuffix already exists!");
    }

    private function createContract($sufix = false)
    {
        $path = $this->rootPath . "/Contracts/";

        // Verifica se a pasta existe.
        $this->checkDirectory($path);

        // Verifica se é um service.
        if($sufix == 'Services'){
            $fileContract = $path . "{$this->name}Contract.php";
            $stub = $this->getContentServiceContract();
        }else{
            $fileContract = $path . "{$this->name}RepositoryContract.php";
            $stub = $this->getContentRepositoryContract();
        }

        // Verifica se o arquivo existe.
        if(file_exists($fileContract)){
            return  $this->info("{$this->name}RepositoryContract already exists!");
        }

        // cria o arquivo.
        $this->files->put($fileContract, $stub);
    }

    protected function getContentRepository(): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace Domain\\{$this->name}\Repositories;

        use App\Models\\{$this->modelName};
        use Domain\\{$this->name}\Contracts\\{$this->name}RepositoryContract;
        use Domain\Shared\Repositories\BaseRepository;
        use Domain\\{$this->name}\DTOs\\{$this->name}DTO;

        class {$this->name}Repository extends BaseRepository implements {$this->name}RepositoryContract
        {
            protected string \$modelClass = {$this->modelName}::class;

            public function __construct()
            {
                parent::__construct();
            }

            public function methodName({$this->name}DTO \$input) : array
            {
                // your Function
                return ['OK'];
            }
        }
        PHP;
    }

    protected function getContentRepositoryContract(): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace Domain\\{$this->name}\Contracts;
        use Domain\Shared\Contracts\BaseRepositoryContract;
        use Domain\\{$this->name}\DTOs\\{$this->name}DTO;

        interface {$this->name}RepositoryContract extends BaseRepositoryContract
        {
            function methodName({$this->name}DTO \$input);
        }
        PHP;
    }

    protected function getContentServiceContract(): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace Domain\\{$this->name}\Contracts;

        use Domain\\{$this->name}\DTOs\\{$this->name}DTO;

        interface {$this->name}Contract
        {
            public function exec({$this->name}DTO \$input);
        }

        PHP;
    }

    protected function getContentService(): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace Domain\\{$this->name}\Services;

        use Domain\\{$this->name}\Contracts\\{$this->name}Contract;
        use Domain\\{$this->name}\DTOs\\{$this->name}DTO;
        use Domain\\{$this->name}\Contracts\\{$this->name}RepositoryContract;

        class {$this->name} implements {$this->name}Contract
        {
            public function __construct(
                private readonly {$this->name}RepositoryContract \$repository
            ){}

            public function exec({$this->name}DTO \$input) : array //type
            {
                return \$this->repository->methodName(\$input);
            }
        }
        PHP;
    }

    protected function getContentDTO(): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace Domain\\{$this->name}\DTOs;

        final class {$this->name}DTO
        {

            public function __construct(
                public readonly string \$exemple
            ){}
        }

        PHP;

    }


    protected function AddRegisterServiceProvider()
    {

        $filePath = base_path('app/Providers/AppServiceProvider.php');

        // Lê o conteúdo do arquivo.
        $fileContent = $this->files->get($filePath);

        // Adiciona as importações logo após o namespace.
        $namespacePos = strpos($fileContent, 'namespace ');
        $namespaceEndPos = strpos($fileContent, ';', $namespacePos) + 1;

        $imports = "\nuse Domain\\{$this->name}\Contracts\\{$this->name}RepositoryContract;"
            . "\nuse Domain\\{$this->name}\Repositories\\{$this->name}Repository;"
            . "\nuse Domain\\{$this->name}\Contracts\\{$this->name}Contract;"
            . "\nuse Domain\\{$this->name}\Services\\{$this->name};";

        $fileContent = substr($fileContent, 0, $namespaceEndPos)
            . $imports
            . substr($fileContent, $namespaceEndPos);

        // Encontra a posição do método register.
        $registerMethodPos = strpos($fileContent, 'public function register()');

        // Encontra a posição da abertura do método register.
        $insertPos = strpos($fileContent, '{', $registerMethodPos) + 1;

        // Insere os bindings dentro do método register
        $newContent = substr($fileContent, 0, $insertPos)
            . "\n        \$this->app->bind({$this->name}RepositoryContract::class, {$this->name}Repository::class);"
            . "\n        \$this->app->bind({$this->name}Contract::class, {$this->name}::class);"
            . substr($fileContent, $insertPos);

        // Escreve o novo conteúdo de volta no arquivo.
        $this->files->put($filePath, $newContent);

        $this->info('Updating dumps');

        // Executa o comando dump para carregar a estrutura.
        exec('composer dump-autoload');

        $this->info("Bindings and imports added to AppServiceProvider!");

    }

    private function createFile($filepath , $sufix) {

        // Seleciona qual conteudo deve pegar.
        $methods = [
            'Repositories' => $this->getContentRepository(),
            'Services' => $this->getContentService(),
            'DTOs' => $this->getContentDTO(),
        ];

        // Busca o conteudo para criação.
        $stub = $methods[$sufix];

        // cria o arquivo.
        $this->files->put($filepath, $stub);

        // cria contratos apenas para service e repositório
        if($sufix != 'DTOs'){
            $this->createContract($sufix);
        }
    }

    private function checkDirectory(string $directory) : void
    {
        if (file_exists($directory)) {
            return;
        }
        mkdir($directory,0777,true);
    }

}
