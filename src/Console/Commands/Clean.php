<?php

namespace Shahnewaz\Redprint\Console\Commands;

use Artisan;
use Illuminate\Console\Command;

class Clean extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redprint:clean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'General Laravel cleanup.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Dump Composer autoload
        try {
            $process = system('composer dump-autoload');
            $this->info('Generated optimized autoload files.');
        } catch (\Exception $e) {
            // Silence!
        }

        $this->call('clear-compiled');
        $this->call('route:clear');
        $this->call('config:clear');
        $this->call('view:clear');
    }
}
