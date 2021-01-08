<?php

use PHPUnit\Framework\TestCase;

require dirname(__DIR__) . "/vendor/autoload.php";

class ObjectTest extends TestCase
{
    /**
     * @var \Minoi\Client\ObjectClient
     */
    private $o;

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
        $this->o = new \Minoi\Client\ObjectClient($minioConfig);
    }

    public function testUpload()
    {
        $localPath = "/Users/chenzifan/www/monda-minio-sdk/a.txt";
        //$r = $this->o->putObjectBySavePath($localPath,"ccc/2.txt");
        //var_dump($r);

        //$url = $this->o->getObjectUrl("ccc/2.txt");

        //var_dump($url);

        //$this->o->removeObject("ccc/2.txt");

//        $r = $this->o->listObjects();

        $r = $this->o->copyObject("ccc/aax.txt","ccc/333.txt","test");



        var_dump($this->o->getErrorInfo());
        var_dump($r);
    }


    public function testUploadContent()
    {
        $r = $this->o->putObjectByContent("ccc/aax.txt","ceshi");
        var_dump($r);
    }

    public function testGetObject()
    {
       $r = $this->o->getObject("ccc/aax.txt");
       var_dump($r->getContents());
    }

    public function testGetObjectInBucketSaveAs()
    {
        $r = $this->o->getObjectInBucketSaveAs("test","ccc/aax.txt","demo.txt");
        var_dump($r);
    }
}