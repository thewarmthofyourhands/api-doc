<?php

declare(strict_types=1);

namespace Eva\ApiDoc;

use Eva\Common\CaseConverter;
use Eva\Filesystem\Filesystem;

class Generator
{
    protected array $doc = [];
    protected Filesystem $filesystem;

    public function __construct(protected string $path = '/src/Dto/Api', protected string $namespace = 'App\Dto\Api')
    {
        $this->path = getcwd() . $path;
        $this->filesystem = new Filesystem();
    }

    public function generateApiDto(): void
    {
        $this->clear();
        $this->filesystem->mkdir($this->path, 0777, true);

        foreach ($this->doc as $dto => $config) {
            $dtoClass = CaseConverter::toPascaleCase($dto) . 'Dto';
            if (isset($config['request'])) {
                $requestConfig = array_filter($config['request'], fn ($key) => $key === 'headers' || $key === 'params' || $key === 'body', ARRAY_FILTER_USE_KEY);
                $this->generate($dtoClass.'Request', $requestConfig, $this->namespace, $this->path);
            }

            if (isset($config['response'])) {
                foreach ($config['response'] as $statusCode => $responseConfig) {
                    if (isset($responseConfig)) {
                        $this->generate($dtoClass.'Response'.$statusCode, $responseConfig, $this->namespace, $this->path);
                    }
                }
            }
        }
    }

    protected function generate(string $dtoClass, array $config, string $namespace, string $path): void
    {
        if ($this->filesystem->isDir($path) === false) {
            $this->filesystem->mkdir($path);
        }

        $properties = [];

        foreach ($config as $name => $value) {
            if (str_contains($name, '-')) {
                $name = CaseConverter::toCamelCase($name);
            }

            $caseName = CaseConverter::toCamelCase($name);

            if (is_array($value)) {
                if (isset($value['type'])) {
                    $properties[$name] = $value;
                } else if (isset($value[0])) {
                    $this->generate($dtoClass . ucfirst($caseName), $value[0], $namespace . '\Nested', $path . '/Nested');
                    $collectionData = $this->generateCollectionFile(
                        $dtoClass . ucfirst($caseName) . 'Collection',
                        '\\'.$namespace . '\Nested\\' . $dtoClass . ucfirst($caseName),
                        $namespace . '\Nested',
                    );
                    $this->filesystem->filePutContents($path . '/Nested' . '/'.$dtoClass.ucfirst($caseName) . 'Collection.php', $collectionData);
                    $properties[$name] = ['type' => '\\'.$namespace . '\Nested\\' . $dtoClass . ucfirst($caseName) . 'Collection'];
                } else {
                    $this->generate($dtoClass . ucfirst($caseName), $value, $namespace . '\Nested', $path . '/Nested');
                    $properties[$name] = ['type' => '\\'.$namespace . '\Nested\\' . $dtoClass . ucfirst($caseName)];
                }
            } else {
                $type = gettype($value);
                $type = match ($type) {
                    'boolean' => 'bool',
                    'integer' => 'int',
                    'double' => 'float',
                    default => $type,
                };
                $properties[$name] = ['type' => $type, 'default' => $value];
            }
        }

        $data = $this->generateDtoFile($dtoClass, $namespace, $properties);
        $this->filesystem->filePutContents($path . '/'.$dtoClass.'.php', $data);
    }

    protected function generateCollectionFile(string $collectionClass, string $dtoClass, string $namespace): string
    {
        $collection = '$collection';
        $str = <<<EOD
        <?php

        declare(strict_types=1);

        namespace $namespace;

        use Eva\Common\ObjectCollection;

        class $collectionClass extends ObjectCollection
        {
            public function __construct(array $collection)
            {
                parent::__construct('$dtoClass', $collection);
            }
        }

        EOD;

        return $str;
    }

    protected function generateDtoFile(string $dtoClass, string $namespace, array $properties): string
    {
        $propStr = '';
        $exclude = [];

        foreach ($properties as $name => $value) {
            $propStr .= 'public '.$value['type'].' $'.$name;

            if (true === isset($value['default'])) {
                if (is_string($value['default'])) {
                    $propStr .= ' = \''.$value['default'].'\'';
                } else {
                    $propStr .= ' = ' . $value['default'];
                }
            }

            if (true === isset($value['required']) && false === $value['required']) {
                $exclude[] = $name;
            }

            $propStr .= ';'.PHP_EOL.'    ';
        }

        $excludeList = '';

        foreach ($exclude as $item) {
            $excludeList .= "'$item',";
        }

        $str = <<<EOD
        <?php
        
        declare(strict_types=1);

        namespace $namespace;
        
        use Eva\Common\NestedDto;
        
        class $dtoClass extends NestedDto
        {
            protected const EXCLUDE_PROPERTIES = [$excludeList];
        
            $propStr
        }

        EOD;

        return $str;
    }

    public function clear(): void
    {
        if ($this->filesystem->isDir($this->path)) {
            $this->filesystem->rm($this->path);
        }
    }

    public function setDoc(array $doc): void
    {
        $this->doc = $doc;

        if (true === isset($doc['settings'])) {
            $this->path = getcwd() . $doc['settings']['path'];
            $this->namespace = $doc['settings']['namespace'];
        }
    }
}
