<?php
namespace Supervisord\Beanstalkd;

require __DIR__ . '/../../../vendor/autoload.php';

use Pheanstalk\Pheanstalk;

class Workers
{
    /** @var Pheanstalk */
    protected $beanstalk;
    protected $rules = [];
    protected $num_proc_adjustment = false;
    protected $supervisor_ctl = '/bin/supervisorctl';

    /**
     * Workers constructor.
     * @param Pheanstalk $beanstalk
     * @param string $supervisor_ctl Leave blank for testing
     */
    public function __construct(Pheanstalk $beanstalk, $supervisor_ctl = '/bin/supervisorctl')
    {
        $this->beanstalk      = $beanstalk;
        $this->supervisor_ctl = $supervisor_ctl;
    }

    public function addIniFile($tube, $ini_file)
    {
        if ( ! isset($this->rules[$tube])) {
            $this->rules[$tube] = [
                'ini_file' => '',
                'rules' => [],
            ];
        };

        $this->rules[$tube]['ini_file'] = $ini_file;
    }

    public function addRules($tube, $rules)
    {
        if ( ! isset($this->rules[$tube])) {
            $this->rules[$tube] = [
                'ini_file' => '',
                'rules' => [],
            ];
        };

        $this->rules[$tube]['rules'][] = $rules;
    }

    public function run()
    {
        $tubes = $this->beanstalk->listTubes();
        $update_happened = false;
        foreach ($tubes as $tube)
        {
            $stats = $this->beanstalk->statsTube($tube);

            $this->adjustWorkers($tube, $stats);
            if ($this->num_proc_adjustment && $this->supervisor_ctl) {
                `{$this->supervisor_ctl} update $tube`;
            }
            if ($this->num_proc_adjustment) {
                $update_happened = true;
            }
        }
        return $update_happened;
    }

    protected function adjustWorkers($tube, $stats)
    {
        $this->num_proc_adjustment = false;
        $current_jobs = $stats['current-jobs-ready'];
        if ( ! isset($this->rules[$tube])) { return; }

        $required_workers = 1;
        foreach ($this->rules[$tube]['rules'] as $rule)
        {
            if ($current_jobs >= $rule['minimum_jobs']) {
                $required_workers = $rule['required_workers'];
            }
        }

        if (file_exists($this->rules[$tube]['ini_file'])) {
            $file = file_get_contents($this->rules[$tube]['ini_file']);
            preg_match('#numprocs=(\d)*#', $file, $matches);
            if ($required_workers != $matches[1]) {
                $file = preg_replace('#numprocs=\d*#', 'numprocs=' . $required_workers, $file);
                file_put_contents($this->rules[$tube]['ini_file'], $file);
                $this->num_proc_adjustment = true;
            }
        }
    }
}
