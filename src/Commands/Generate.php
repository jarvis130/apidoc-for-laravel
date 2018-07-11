<?php
namespace Biggold\Apidoc\Commands;

use Biggold\Apidoc\ApiDocServiceProvider;
use Biggold\Apidoc\Exceptions\DocException;

use Biggold\Apidoc\Log;
use Illuminate\Console\Command;

class Generate extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'doc:generate 
                   
                        {--queue= : Whether the job should be queued}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command create doc for me!';

    protected $toolbox;


    public function __construct()
    {
        parent::__construct();
        $this->toolbox = app()->getProvider(ApiDocServiceProvider::class)->getToolbox();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->call("doc:cleanall");
        Log::setCommand($this);
        try {
            $desc = $this->toolbox->getDesc();
            $render = $this->toolbox->getRender();
            $render->before();
            foreach ($desc->extraDesc() as $item) {
                $render->render($item);
            }
            $render->after();
        } catch (DocException $e) {
            $this->error($e->getMessage());
        }
    }

}