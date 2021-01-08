<?php

use PHPUnit\Framework\TestCase;

require dirname(__DIR__) . "/vendor/autoload.php";

class BucketTest extends TestCase
{
    /**
     * @var \Minoi\Client\BucketClient
     */
    private $bucketClient;

    protected function setUp(): void
    {
        // minio配置信息
        $minioConfig = [
            'key' => 'minioadmin',
            'secret' => 'minioadmin',
            'region' => '',
            'version' => 'latest',
            'endpoint' => 'http://172.28.1.3:9000',
            'bucket' => 'test',
        ];
        $this->bucketClient = new \Minoi\Client\BucketClient($minioConfig);
    }

    public function testCreate()
    {
        $name = "test-01";
        $r = $this->bucketClient->create($name);
        $this->assertEquals(true, $r);
        $r2 = $this->bucketClient->create($name);
        $this->assertEquals(false, $r2);
    }

    public function testDelete()
    {
        $name = "test-01";
        $r = $this->bucketClient->delete($name);
        $this->assertEquals(true, $r);
    }

    public function testList()
    {
        $r = $this->bucketClient->listBuckets();
        var_dump($r);
    }

    public function testGetBucketPolicies()
    {
        $name = "test-1605439678";
        $r = $this->bucketClient->getBucketPolicies($name);
        var_dump($r);
    }

    public function testSetBucketPolicies()
    {
        $policies = [
           'read' => ['read1', 'read2'], //只读
           'write' => ['write1', 'write2'],  //只写
           'read+write' => ['readwrite1', 'rw'] // 读+写
        ];
        $b = $this->bucketClient->setBucketPolicies($policies,"test-1605439678");
    }

    public function testDeletePolicies()
    {
        $r = $this->bucketClient->deleteBucketPolicies("test-1605439678");
        var_dump($r);
    }
}