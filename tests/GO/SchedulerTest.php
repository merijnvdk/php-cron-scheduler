<?php namespace GO\Job\Tests;

use GO\Scheduler;
use PHPUnit\Framework\TestCase;

class SchedulerTest extends TestCase
{
    public function testShouldQueueJobs()
    {
        $scheduler = new Scheduler();

        $this->assertEquals(count($scheduler->getQueuedJobs()), 0);

        $scheduler->raw('ls');

        $this->assertEquals(count($scheduler->getQueuedJobs()), 1);
    }

    public function testShouldQueueAPhpScript()
    {
        $scheduler = new Scheduler();

        $script = __DIR__.'/../test_job.php';

        $this->assertEquals(count($scheduler->getQueuedJobs()), 0);

        $scheduler->php($script);

        $this->assertEquals(count($scheduler->getQueuedJobs()), 1);
    }

    public function testShouldQueueAShellCommand()
    {
        $scheduler = new Scheduler();

        $this->assertEquals(count($scheduler->getQueuedJobs()), 0);

        $scheduler->raw('ls');

        $this->assertEquals(count($scheduler->getQueuedJobs()), 1);
    }

    public function testShouldQueueAFunction()
    {
        $scheduler = new Scheduler();

        $this->assertEquals(count($scheduler->getQueuedJobs()), 0);

        $scheduler->call(function () {
            return true;
        });

        $this->assertEquals(count($scheduler->getQueuedJobs()), 1);
    }

    public function testShouldKeepTrackOfExecutedJobs()
    {
        $scheduler = new Scheduler();

        $scheduler->call(function () {
            return true;
        });

        $this->assertEquals(count($scheduler->getQueuedJobs()), 1);
        $this->assertEquals(count($scheduler->getExecutedJobs()), 0);

        $scheduler->run();

        $this->assertEquals(count($scheduler->getExecutedJobs()), 1);
    }

    public function testShouldPassParametersToAFunction()
    {
        $scheduler = new Scheduler();

        $outputFile = __DIR__.'/../tmp/output.txt';
        $scheduler->call(function ($phrase) {
            return $phrase;
        }, [
            'Hello World!'
        ])->output($outputFile);

        @unlink($outputFile);

        $this->assertFalse(file_exists($outputFile));

        $scheduler->run();

        $this->assertNotEquals('Hello', file_get_contents($outputFile));
        $this->assertEquals('Hello World!', file_get_contents($outputFile));

        @unlink($outputFile);
    }

    public function testShouldKeepTrackOfFailedJobs()
    {
        $scheduler = new Scheduler();

        $scheduler->call(function () {
            throw new \Exception("Something failed");
        });

        $this->assertEquals(count($scheduler->getFailedJobs()), 0);

        $scheduler->run();

        $this->assertEquals(count($scheduler->getExecutedJobs()), 0);
        $this->assertEquals(count($scheduler->getFailedJobs()), 1);
    }

    public function testShouldKeepExecutingJobsIfOneFails()
    {
        $scheduler = new Scheduler();

        $scheduler->call(function () {
            throw new \Exception("Something failed");
        });

        $scheduler->call(function () {
            return true;
        });

        $scheduler->run();

        $this->assertEquals(count($scheduler->getExecutedJobs()), 1);
        $this->assertEquals(count($scheduler->getFailedJobs()), 1);
    }

    public function testShouldInjectConfigToTheJobs()
    {
        $schedulerConfig = [
            'email' => [
                'subject' => 'My custom subject'
            ]
        ];
        $scheduler = new Scheduler($schedulerConfig);

        $job = $scheduler->raw('ls');

        $this->assertEquals($job->getEmailConfig()['subject'], $schedulerConfig['email']['subject']);
    }

    public function testShouldPrioritizeJobConfigOverSchedulerConfig()
    {
        $schedulerConfig = [
            'email' => [
                'subject' => 'My custom subject'
            ]
        ];
        $scheduler = new Scheduler($schedulerConfig);

        $jobConfig = [
            'email' => [
                'subject' => 'My job subject'
            ]
        ];
        $job = $scheduler->raw('ls')->configure($jobConfig);

        $this->assertNotEquals($job->getEmailConfig()['subject'], $schedulerConfig['email']['subject']);
        $this->assertEquals($job->getEmailConfig()['subject'], $jobConfig['email']['subject']);
    }

    public function testShouldShowClosuresVerboseOutputAsText()
    {
        $scheduler = new Scheduler();

        $scheduler->call(function ($phrase) {
            return $phrase;
        }, [
            'Hello World!'
        ]);

        $scheduler->run();

        $this->assertRegexp('/ Executing Closure$/', $scheduler->getVerboseOutput());
        $this->assertRegexp('/ Executing Closure$/', $scheduler->getVerboseOutput('text'));
    }

    public function testShouldShowClosuresVerboseOutputAsHtml()
    {
        $scheduler = new Scheduler();

        $scheduler->call(function ($phrase) {
            return $phrase;
        }, [
            'Hello World!'
        ]);

        $scheduler->call(function () {
            return true;
        });

        $scheduler->run();

        $this->assertRegexp('/<br>/', $scheduler->getVerboseOutput('html'));
    }

    public function testShouldShowClosuresVerboseOutputAsArray()
    {
        $scheduler = new Scheduler();

        $scheduler->call(function ($phrase) {
            return $phrase;
        }, [
            'Hello World!'
        ]);

        $scheduler->call(function () {
            return true;
        });

        $scheduler->run();

        $this->assertTrue(is_array($scheduler->getVerboseOutput('array')));
        $this->assertEquals(count($scheduler->getVerboseOutput('array')), 2);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testShouldThrowExceptionWithInvalidOutputType()
    {
        $scheduler = new Scheduler();

        $scheduler->call(function ($phrase) {
            return $phrase;
        }, [
            'Hello World!'
        ]);

        $scheduler->call(function () {
            return true;
        });

        $scheduler->run();

        $scheduler->getVerboseOutput('multiline');
    }
}