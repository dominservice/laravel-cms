<?php

namespace Dominservice\LaravelCms\Console\Commands;

use Dominservice\LaravelCms\Support\RedirectRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
    protected $description = 'Rebuild the redirect runtime cache';

    /**
     * Execute the console command.
     */
    public function handle(RedirectRepository $redirects): int
    {
        $redirects->rebuild();

        if (Schema::hasTable('settings')) {
            DB::table('settings')->where('key', 'cms.redirects')->delete();
        }

        config(['cms.redirects' => $redirects->records()]);

        return Command::SUCCESS;
    }
}
