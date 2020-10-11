<?php
namespace Shahnewaz\RedprintNg\Services;

use Illuminate\Database\Migrations\Migrator as BaseMigrator;

class Migrator extends BaseMigrator
{
    /**
     * The migration repository implementation.
     *
     * @var \Illuminate\Database\Migrations\MigrationRepositoryInterface
     */
    protected $repository;

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * The connection resolver instance.
     *
     * @var \Illuminate\Database\ConnectionResolverInterface
     */
    protected $resolver;

    /**
     * Create a new migrator instance.
     *
     * @param  \Illuminate\Database\Migrations\MigrationRepositoryInterface  $repository
     * @param  \Illuminate\Database\ConnectionResolverInterface  $resolver
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @return void
     */
    public function __construct()
    {
        $migrator = app('migrator');
        parent::__construct($migrator->repository, $migrator->resolver, $migrator->files);
    }

    public function rollbackSelectedMigrations($migrations)
    {
        $paths = database_path('migrations');
        // echo "<pre>"; print_r($migrations); exit;
        return $this->rollbackMigrations($migrations, [$paths], []);
    }
}