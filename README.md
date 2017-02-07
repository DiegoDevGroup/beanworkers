# Beanworkers
This is a command line script to automatically update the number of processes in supervisord dynamically based on number of ready jobs in a beanstalk tube(s)

Usage is simple, but there are assumptions made.   Please ensure you have a backup before attempting to use this code.

# Assumptions
1. Supervisord programs are listed individually in separate ini files.
2. You are running this as a user that has access to edit ini files and run `supervisorctl`

# Example

```
$pheanstalk = new \Pheanstalk\Pheanstalk("$host:$port");
$worker = new Supervisord\Beanstalkd\Workers($pheanstalk, '/usr/bin/supervisorctl');
$worker->addRules('default', '/etc/supervisord.d/default.ini', ['minimum_jobs' => 0, 'required_workers' => 1]);
$worker->addRules('default', '/etc/supervisord.d/default.ini', ['minimum_jobs' => 10, 'required_workers' => 3]);
$worker->run();
```

In the above example, the worker gets the stats of the default tube, and if there are less than 10 jobs currently ready, it will set the numprocs to 1.  If there are 10 or more, it will set numprocs to 3.  It will then update supervisord via `supervisorctl`.

# Add to cron
Your script should probably run every minute.

Add as many rules as you like, just keep your resources in mind as you do so.
