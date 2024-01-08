<?php

namespace Cleantalk\Custom\Cron;

class Cron extends \Cleantalk\Common\Cron\Cron
{
    public function __construct()
    {
        parent::__construct();
    }
    public function checkCronData()
    {
        $unexists = array_diff(['plugin_get_latest_version', 'sfw_update', 'sfw_send_logs'], array_keys($this->tasks));
        if (!empty($unexists)) {
            foreach ($unexists as $task) {
                switch ($task) {
                    case 'sfw_update':
                        $this->addTask( 'sfw_update', 'apbct_sfw_update', 86400, time() + 60 );
                        break;
                    case 'sfw_send_logs':
                        $this->addTask( 'sfw_send_logs', 'apbct_sfw_send_logs', 3600 );
                        break;
                    case 'plugin_get_latest_version':
                        $this->addTask( 'plugin_get_latest_version', 'apbct__plugin_get_latest_version', 86400 );
                        break;
                }
            }
        }
    }
}
