<?php

namespace Dominservice\LaravelCms\Console\Commands;

use Dominservice\LaravelConfig\Config;
use Illuminate\Console\Command;

class RedirectConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'regenerate-redirect';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate redirect config';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $value = \Dominservice\LaravelCms\Models\Redirect::whereNotNull('active_at')->get()->toArray();
        (new Config())->set('cms.redirects', $value, true);
        return Command::SUCCESS;
    }
}
