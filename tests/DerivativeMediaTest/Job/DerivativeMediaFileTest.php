<?php declare(strict_types=1);

namespace DerivativeMediaTest\Job;

use CommonTest\AbstractHttpControllerTestCase;
use DerivativeMedia\Job\DerivativeMediaFile;
use DerivativeMediaTest\DerivativeMediaTestTrait;
use Omeka\Entity\Job;

/**
 * Tests for the DerivativeMediaFile job.
 *
 * These tests focus on the utility methods (rangeToArray, exprRange)
 * and job initialization, since actual media conversion requires
 * external tools (ffmpeg, gs).
 */
class DerivativeMediaFileTest extends AbstractHttpControllerTestCase
{
    use DerivativeMediaTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        parent::tearDown();
    }

    /**
     * Test that job completes with no matching media.
     */
    public function testJobCompletesWithNoMatchingMedia(): void
    {
        // Run with a query that matches no items.
        $job = $this->runJob(DerivativeMediaFile::class, [
            'query_items' => ['id' => 999999],
        ]);
        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());
    }

    /**
     * Test that job completes with empty args.
     */
    public function testJobCompletesWithEmptyArgs(): void
    {
        // The job should complete (possibly with a warning about no ffmpeg/gs).
        $job = $this->runJob(DerivativeMediaFile::class, []);
        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());
    }

    /**
     * Test rangeToArray with simple IDs.
     */
    public function testRangeToArrayWithSimpleIds(): void
    {
        $job = $this->createJobInstance();
        $result = $this->invokeMethod($job, 'rangeToArray', ['1 2 3']);
        $this->assertEquals(['1', '2', '3'], $result);
    }

    /**
     * Test rangeToArray with ranges.
     */
    public function testRangeToArrayWithRanges(): void
    {
        $job = $this->createJobInstance();
        $result = $this->invokeMethod($job, 'rangeToArray', ['2-6 8 38-52 80-']);
        $this->assertEquals(['2-6', '8', '38-52', '80-'], $result);
    }

    /**
     * Test rangeToArray with special characters.
     */
    public function testRangeToArrayCleansSpecialCharacters(): void
    {
        $job = $this->createJobInstance();
        $result = $this->invokeMethod($job, 'rangeToArray', ['1, 3; 5-10']);
        $this->assertEquals(['1', '3', '5-10'], $result);
    }

    /**
     * Test rangeToArray with array input.
     */
    public function testRangeToArrayWithArrayInput(): void
    {
        $job = $this->createJobInstance();
        $result = $this->invokeMethod($job, 'rangeToArray', [['1-5', '10', '20-']]);
        $this->assertEquals(['1-5', '10', '20-'], $result);
    }

    /**
     * Test rangeToArray skips entries with multiple dashes.
     */
    public function testRangeToArraySkipsMultipleDashes(): void
    {
        $job = $this->createJobInstance();
        $result = $this->invokeMethod($job, 'rangeToArray', ['1-2-3 4']);
        // '1-2-3' should be skipped because it has more than one dash.
        $this->assertEquals(['4'], $result);
    }

    /**
     * Test rangeToArray with empty input.
     */
    public function testRangeToArrayWithEmptyInput(): void
    {
        $job = $this->createJobInstance();
        $result = $this->invokeMethod($job, 'rangeToArray', ['']);
        $this->assertEquals([], $result);
    }

    /**
     * Test exprRange with simple IDs creates equality conditions.
     */
    public function testExprRangeWithSimpleIds(): void
    {
        $job = $this->createJobInstance();
        $result = $this->invokeMethod($job, 'exprRange', ['id', '1 2 3']);
        $this->assertCount(3, $result);
    }

    /**
     * Test exprRange with range creates composite conditions.
     */
    public function testExprRangeWithRange(): void
    {
        $job = $this->createJobInstance();
        $result = $this->invokeMethod($job, 'exprRange', ['id', '2-6']);
        $this->assertCount(1, $result);
    }

    /**
     * Test exprRange with open-ended range (from only).
     */
    public function testExprRangeWithOpenEndedRangeFrom(): void
    {
        $job = $this->createJobInstance();
        $result = $this->invokeMethod($job, 'exprRange', ['id', '80-']);
        $this->assertCount(1, $result);
    }

    /**
     * Test exprRange with empty input returns empty array.
     */
    public function testExprRangeWithEmptyInput(): void
    {
        $job = $this->createJobInstance();
        $result = $this->invokeMethod($job, 'exprRange', ['id', '']);
        $this->assertEmpty($result);
    }

    /**
     * Test exprRange with mixed input.
     */
    public function testExprRangeWithMixedInput(): void
    {
        $job = $this->createJobInstance();
        $result = $this->invokeMethod($job, 'exprRange', ['id', '2-6 8 38-52 80-']);
        // 4 conditions: range 2-6, eq 8, range 38-52, gte 80.
        $this->assertCount(4, $result);
    }

    /**
     * Create a DerivativeMediaFile job instance for testing protected methods.
     */
    protected function createJobInstance(): DerivativeMediaFile
    {
        $services = $this->getServiceLocator();
        $entityManager = $services->get('Omeka\EntityManager');
        $auth = $services->get('Omeka\AuthenticationService');

        $job = new Job();
        $job->setStatus(Job::STATUS_STARTING);
        $job->setClass(DerivativeMediaFile::class);
        $job->setArgs([]);
        $job->setOwner($auth->getIdentity());

        $entityManager->persist($job);
        $entityManager->flush();

        return new DerivativeMediaFile($job, $services);
    }

    /**
     * Invoke a protected/private method on an object.
     *
     * @param object $object
     * @param string $method
     * @param array $args
     * @return mixed
     */
    protected function invokeMethod(object $object, string $method, array $args = [])
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($object, $args);
    }
}
