<?php
namespace Supervisord\Beanstalkd;

require __DIR__ . '/../../../vendor/autoload.php';

class WorkersTest extends \PHPUnit\Framework\TestCase
{
    protected $ini_file;
    protected $beanstalk;
    protected $worker;

    public function setUp()
    {
        $this->ini_file = tempnam(sys_get_temp_dir(), 'supervisord');
        file_put_contents($this->ini_file, $this->getExampleIniFile());
        $this->beanstalk = $this->getMockBuilder('\Pheanstalk\Pheanstalk')
            ->setConstructorArgs([''])
            ->getMock();

        $this->worker = new Workers($this->beanstalk, '');
        $this->worker->addRules('default', $this->ini_file, ['minimum_jobs' => 0, 'required_workers' => 1]);
        $this->worker->addRules('default', $this->ini_file, ['minimum_jobs' => 5, 'required_workers' => 3]);
        $this->worker->addRules('default', $this->ini_file, ['minimum_jobs' => 10, 'required_workers' => 5]);
    }

    public function testWorkersDecrease()
    {
        $this->beanstalk->expects($this->once())->method('listTubes')->will($this->returnValue(['default']));
        $this->beanstalk->expects($this->once())->method('statsTube')->will($this->returnValue(['current-jobs-ready' => 2]));
        $updated = $this->worker->run();

        $file = file_get_contents($this->ini_file);
        $this->assertContains('numprocs=1', $file, 'We should have 1 processes based on 2 current jobs');
        $this->assertTrue($updated);
    }

    public function testWorkersIncrease()
    {

        $this->beanstalk->expects($this->once())->method('listTubes')->will($this->returnValue(['default']));
        $this->beanstalk->expects($this->once())->method('statsTube')->will($this->returnValue(['current-jobs-ready' => 15]));
        $updated = $this->worker->run();

        $file = file_get_contents($this->ini_file);
        $this->assertContains('numprocs=5', $file, 'We should have 3 processes based on 5 current jobs');
        $this->assertTrue($updated);
    }

    public function testWorkersDoNotChange()
    {

        $this->beanstalk->expects($this->once())->method('listTubes')->will($this->returnValue(['default']));
        $this->beanstalk->expects($this->once())->method('statsTube')->will($this->returnValue(['current-jobs-ready' => 5]));
        $updated = $this->worker->run();

        $file = file_get_contents($this->ini_file);
        $this->assertContains('numprocs=3', $file, 'We should have 3 processes based on 5 current jobs');
        $this->assertFalse($updated);
    }

    protected function getExampleIniFile()
    {
        return <<<EOF
[program:example]
command=php example.php
directory=/path/to/your/workers
numprocs=3
process_name=%(program_name)s_%(process_num)s
autostart=true
autorestart=true
EOF;
    }
}
