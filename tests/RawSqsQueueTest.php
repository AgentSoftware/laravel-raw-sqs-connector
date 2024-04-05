<?php

namespace Tests;

use Aws\Sqs\SqsClient;
use Illuminate\Container\Container;
use Illuminate\Queue\InvalidPayloadException;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use PHPUnit\Framework\TestCase;
use AgentSoftware\LaravelRawSqsConnector\RawSqsQueue;
use Tests\Support\TestJobClass;

class RawSqsQueueTest extends TestCase
{
    public function testPopShouldReturnNewSqsJob(): void
    {
        $firstName = 'Primitive';
        $lastName = 'Sense';

        $sqsReturnMessage = [
            'Body' => json_encode([
                'first_name' => $firstName,
                'last_name' => $lastName
            ])
        ];

        $sqsClientMock = Mockery::mock(SqsClient::class);
        $sqsClientMock->shouldReceive('receiveMessage')
            ->andReturn([
                'Messages' => [
                    $sqsReturnMessage
                ]
            ]);


        $rawSqsQueue = new RawSqsQueue(
            $sqsClientMock,
            'default',
            'prefix'
        );

        $container = Mockery::mock(Container::class);
        $rawSqsQueue->setContainer($container);
        $rawSqsQueue->setJobClass(TestJobClass::class);
        $jobPayload = $rawSqsQueue->pop()->payload();

        $this->assertSame($jobPayload['displayName'], TestJobClass::class);
        $this->assertSame($jobPayload['job'], 'Illuminate\Queue\CallQueuedHandler@call');
        $this->assertSame($jobPayload['data']['commandName'], TestJobClass::class);

        $testJob = unserialize($jobPayload['data']['command']);
        $this->assertSame($testJob->data['first_name'], $firstName);
        $this->assertSame($testJob->data['last_name'], $lastName);
    }

    public function testPopShouldReturnNullIfMessagesAreNull(): void
    {
        $sqsClientMock = Mockery::mock(SqsClient::class);
        $sqsClientMock->shouldReceive('receiveMessage')
            ->andReturn([
                'Messages' => null
            ]);


        $rawSqsQueue = new RawSqsQueue(
            $sqsClientMock,
            'default',
            'prefix'
        );

        $container = Mockery::mock(Container::class);
        $rawSqsQueue->setContainer($container);
        $rawSqsQueue->setJobClass(TestJobClass::class);
        $this->assertNull($rawSqsQueue->pop());
    }

    public function testPushShouldthrowInvalidPayLoadException(): void
    {
        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('push is not permitted for raw-sqs connector');

        $sqsClientMock = Mockery::mock(SqsClient::class);

        $rawSqsQueue = new RawSqsQueue(
            $sqsClientMock,
            'default',
            'prefix'
        );

        $rawSqsQueue->push(null, null, null);
    }

    public function testPushRawShouldThrowInvalidPayLoadException(): void
    {
        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('pushRaw is not permitted for raw-sqs connector');

        $sqsClientMock = Mockery::mock(SqsClient::class);

        $rawSqsQueue = new RawSqsQueue(
            $sqsClientMock,
            'default',
            'prefix'
        );

        $rawSqsQueue->pushRaw(null, null, []);
    }

    public function testLaterShouldThrowInvalidPayLoadException(): void
    {
        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('later is not permitted for raw-sqs connector');

        $sqsClientMock = Mockery::mock(SqsClient::class);

        $rawSqsQueue = new RawSqsQueue(
            $sqsClientMock,
            'default',
            'prefix'
        );

        $rawSqsQueue->later(null, null);
    }

    public function testDoesNotUseRateLimiterIfRateLimitNotSpecified(): void
    {
        $firstName = 'Primitive';
        $lastName = 'Sense';

        $sqsReturnMessage = [
            'Body' => json_encode([
                'first_name' => $firstName,
                'last_name' => $lastName
            ])
        ];

        $sqsClientMock = Mockery::mock(SqsClient::class);
        $sqsClientMock->shouldReceive('receiveMessage')
            ->andReturn([
                'Messages' => [
                    $sqsReturnMessage
                ]
            ]);

        $sqsClientMock->shouldNotReceive('hasRemainingAttempts');

        $rawSqsQueue = new RawSqsQueue(
            $sqsClientMock,
            'default',
            'prefix'
        );

        $container = Mockery::mock(Container::class);
        $rawSqsQueue->setContainer($container);
        $rawSqsQueue->setJobClass(TestJobClass::class);

        $rawSqsQueue->pop();

        $this->expectNotToPerformAssertions();
    }

    public function testWillReturnMessageIfRateLimitEnabled(): void
    {
        $firstName = 'Primitive';
        $lastName = 'Sense';

        $sqsReturnMessage = [
            'Body' => json_encode([
                'first_name' => $firstName,
                'last_name' => $lastName
            ])
        ];

        $sqsClientMock = Mockery::mock(SqsClient::class);
        $sqsClientMock->shouldReceive('receiveMessage')
            ->andReturn([
                'Messages' => [
                    $sqsReturnMessage
                ]
            ]);

        $rawSqsQueue = Mockery::mock(RawSqsQueue::class, [
            $sqsClientMock,
            'default',
            'prefix'
        ])
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $rawSqsQueue
            ->shouldReceive('hasRemainingAttempts')
            ->andReturn(true);

        $container = Mockery::mock(Container::class);
        $rawSqsQueue->setContainer($container);
        $rawSqsQueue->setJobClass(TestJobClass::class);
        $rawSqsQueue->setRateLimit(1);

        $rawSqsQueue->pop();

        $this->expectNotToPerformAssertions();
    }

    public function testWillReturnMessageIfRateLimitEnabledUsingAClosure(): void
    {
        $firstName = 'Primitive';
        $lastName = 'Sense';

        $sqsReturnMessage = [
            'Body' => json_encode([
                'first_name' => $firstName,
                'last_name' => $lastName
            ])
        ];

        $sqsClientMock = Mockery::mock(SqsClient::class);
        $sqsClientMock->shouldReceive('receiveMessage')
            ->andReturn([
                'Messages' => [
                    $sqsReturnMessage
                ]
            ]);

        $rawSqsQueue = Mockery::mock(RawSqsQueue::class, [
            $sqsClientMock,
            'default',
            'prefix'
        ])
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $rawSqsQueue
            ->shouldReceive('hasRemainingAttempts')
            ->andReturn(true);

        $container = Mockery::mock(Container::class);
        $rawSqsQueue->setContainer($container);
        $rawSqsQueue->setJobClass(TestJobClass::class);
        $rawSqsQueue->setRateLimit(fn () => 1);

        $rawSqsQueue->pop();

        $this->expectNotToPerformAssertions();
    }

    public function testWillNotReturnMessageIfRateLimitEnabledButNoAttemptsLeft(): void
    {
        $sqsClientMock = Mockery::mock(SqsClient::class);
        $sqsClientMock->shouldNotReceive('receiveMessage');

        $rawSqsQueue = Mockery::mock(RawSqsQueue::class, [
            $sqsClientMock,
            'default',
            'prefix'
        ])
            ->makePartial();

        $rawSqsQueue
            ->shouldAllowMockingProtectedMethods()
            ->shouldReceive('hasRemainingAttempts')
            ->andReturn(false);

        $rawSqsQueue
            ->shouldAllowMockingProtectedMethods()
            ->shouldReceive('log')
            ->once();

        $rawSqsQueue
            ->shouldAllowMockingProtectedMethods()
            ->shouldNotReceive('querySqs');

        $container = Mockery::mock(Container::class);
        $rawSqsQueue->setContainer($container);
        $rawSqsQueue->setJobClass(TestJobClass::class);
        $rawSqsQueue->setRateLimit(1);

        $rawSqsQueue->pop();

        $this->expectNotToPerformAssertions();
    }
}
