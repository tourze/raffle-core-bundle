<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Tests\EventSubscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractEventSubscriberTestCase;
use Tourze\RaffleCoreBundle\EventSubscriber\RequestFileCleanSubscriber;

/**
 * @internal
 */
#[CoversClass(RequestFileCleanSubscriber::class)]
#[RunTestsInSeparateProcesses]
final class RequestFileCleanSubscriberTest extends AbstractEventSubscriberTestCase
{
    public function getEventSubscriberClass(): string
    {
        return RequestFileCleanSubscriber::class;
    }

    protected function onSetUp(): void
    {
        // Nothing to setup for this test
    }

    public function testOnTerminatedWithUploadedFile(): void
    {
        // 创建临时文件
        $tempFilePath = tempnam(sys_get_temp_dir(), 'test');
        $this->assertFileExists($tempFilePath);

        // 创建真实的UploadedFile对象
        $uploadedFile = new UploadedFile(
            $tempFilePath,
            'test.txt',
            'text/plain',
            null,
            true // test mode
        );

        // 创建Request对象
        $request = new Request();
        $request->files = new FileBag(['test_file' => $uploadedFile]);

        // 创建Response对象
        $response = new Response();

        // 创建TerminateEvent对象
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new TerminateEvent($kernel, $request, $response);

        // 执行订阅器方法
        $subscriber = self::getService(RequestFileCleanSubscriber::class);
        $subscriber->onTerminated($event);

        // 验证文件已被删除
        $this->assertFileDoesNotExist($tempFilePath);
    }

    public function testOnTerminatedWithArrayItem(): void
    {
        // 创建临时文件
        $tempFilePath = tempnam(sys_get_temp_dir(), 'test');
        $this->assertFileExists($tempFilePath);

        // 创建Request对象和FileBag
        $request = new Request();
        $fileBag = new FileBag();

        // 使用反射来设置内部的parameters属性，模拟array形式的上传数据
        $reflection = new \ReflectionClass($fileBag);
        $parametersProperty = $reflection->getProperty('parameters');
        $parametersProperty->setAccessible(true);
        $parametersProperty->setValue($fileBag, [
            'test_file' => [
                'tmp_name' => $tempFilePath,
                'name' => 'test.txt',
                'type' => 'text/plain',
                'size' => 0,
                'error' => 0,
            ],
        ]);

        $request->files = $fileBag;

        // 创建Response对象
        $response = new Response();

        // 创建TerminateEvent对象
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new TerminateEvent($kernel, $request, $response);

        // 执行订阅器方法
        $subscriber = self::getService(RequestFileCleanSubscriber::class);
        $subscriber->onTerminated($event);

        // 验证文件已被删除
        $this->assertFileDoesNotExist($tempFilePath);
    }

    public function testOnTerminatedWithNonExistentFile(): void
    {
        // 创建一个不存在的文件路径
        $nonExistentPath = sys_get_temp_dir() . '/test' . uniqid() . '_non_existent';

        // 确保文件不存在
        if (file_exists($nonExistentPath)) {
            unlink($nonExistentPath);
        }

        // 创建一个mock的UploadedFile对象，模拟不存在的文件
        $uploadedFile = $this->createMock(UploadedFile::class);
        $uploadedFile->method('getPathname')->willReturn($nonExistentPath);
        $uploadedFile->method('getRealPath')->willReturn($nonExistentPath);

        // 创建Request对象
        $request = new Request();
        $request->files = new FileBag(['test_file' => $uploadedFile]);

        // 创建Response对象
        $response = new Response();

        // 创建TerminateEvent对象 - 使用简单的mock，只期望不调用任何方法
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new TerminateEvent($kernel, $request, $response);

        // 执行订阅器方法 - 不应该抛出异常
        $subscriber = self::getService(RequestFileCleanSubscriber::class);
        $subscriber->onTerminated($event);

        // 验证不存在的文件依然不存在（测试方法处理不存在文件的鲁棒性）
        $this->assertFileDoesNotExist($nonExistentPath);
    }

    public function testOnTerminatedWithEmptyFileBag(): void
    {
        // 创建Request对象
        $request = new Request();
        $request->files = new FileBag([]);

        // 创建Response对象
        $response = new Response();

        // 创建TerminateEvent对象
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new TerminateEvent($kernel, $request, $response);

        // 执行订阅器方法
        $subscriber = self::getService(RequestFileCleanSubscriber::class);
        $subscriber->onTerminated($event);

        // 验证空的 FileBag 处理正常，没有副作用
        $this->assertEmpty($request->files->all());
    }

    public function testOnTerminatedWithMultipleFiles(): void
    {
        // 创建多个临时文件
        $tempFilePath1 = tempnam(sys_get_temp_dir(), 'test1');
        $tempFilePath2 = tempnam(sys_get_temp_dir(), 'test2');
        $this->assertFileExists($tempFilePath1);
        $this->assertFileExists($tempFilePath2);

        // 创建真实的UploadedFile对象
        $uploadedFile1 = new UploadedFile(
            $tempFilePath1,
            'test1.txt',
            'text/plain',
            null,
            true // test mode
        );

        $uploadedFile2 = new UploadedFile(
            $tempFilePath2,
            'test2.txt',
            'text/plain',
            null,
            true // test mode
        );

        // 创建Request对象
        $request = new Request();
        $request->files = new FileBag([
            'test_file1' => $uploadedFile1,
            'test_file2' => $uploadedFile2,
        ]);

        // 创建Response对象
        $response = new Response();

        // 创建TerminateEvent对象
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new TerminateEvent($kernel, $request, $response);

        // 执行订阅器方法
        $subscriber = self::getService(RequestFileCleanSubscriber::class);
        $subscriber->onTerminated($event);

        // 验证文件已被删除
        $this->assertFileDoesNotExist($tempFilePath1);
        $this->assertFileDoesNotExist($tempFilePath2);
    }

    public function testCleanFileItemWithMixedTypes(): void
    {
        // 创建临时文件
        $tempFilePath1 = tempnam(sys_get_temp_dir(), 'test1');
        $tempFilePath2 = tempnam(sys_get_temp_dir(), 'test2');
        $this->assertFileExists($tempFilePath1);
        $this->assertFileExists($tempFilePath2);

        // 创建真实的UploadedFile对象
        $uploadedFile = new UploadedFile(
            $tempFilePath1,
            'test1.txt',
            'text/plain',
            null,
            true // test mode
        );

        // 创建Request对象和FileBag
        $request = new Request();
        $fileBag = new FileBag();

        // 使用反射来设置内部的parameters属性，模拟混合类型的上传数据
        $reflection = new \ReflectionClass($fileBag);
        $parametersProperty = $reflection->getProperty('parameters');
        $parametersProperty->setAccessible(true);
        $parametersProperty->setValue($fileBag, [
            'uploaded_file' => $uploadedFile,  // UploadedFile 对象
            'array_file' => [  // 数组形式
                'tmp_name' => $tempFilePath2,
                'name' => 'test2.txt',
                'type' => 'text/plain',
                'size' => 0,
                'error' => 0,
            ],
        ]);

        $request->files = $fileBag;

        // 创建Response对象
        $response = new Response();

        // 创建TerminateEvent对象
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new TerminateEvent($kernel, $request, $response);

        // 执行订阅器方法
        $subscriber = self::getService(RequestFileCleanSubscriber::class);
        $subscriber->onTerminated($event);

        // 验证文件已被删除
        $this->assertFileDoesNotExist($tempFilePath1);
        $this->assertFileDoesNotExist($tempFilePath2);
    }
}
