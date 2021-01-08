# monda-minio-sdk

## 使用方法

1. BucketClient 使用说明
```php
    // minio配置信息
    $minioConfig = [
        'key' => 'minioadmin',
        'secret' => 'minioadmin',
        'region' => '',
        'version' => 'latest',
        'endpoint' => 'http://172.28.1.3:9000',
        'bucket' => 'test',
    ];
    $bucketClient = new \Minoi\Client\BucketClient($minioConfig);
    
    //创建bucket
    $res = $bucketClient->create("helloword2");
    var_dump($res);
    if ($res === false) {
        var_dump($bucketClient->getErrorMessage());
    }
    
    //获取列表
    $list = $bucketClient->listBuckets();
    var_dump($list);
    
    //设置策略
    $policies = [
        'read' => ['a', 'b'], //只读
        'write' => ['c', 'd'],  //只写
        'read+write' => ['e', 'f'] // 读+写
    ];
    $res = $bucketClient->setBucketPolicies($policies);
    var_dump($res);
    
    //删除策略
    $res = $bucketClient->deleteBucketPolicies("test");
    var_dump($res);
    
    //删除bucket
    $res = $bucketClient->delete("helloword");
    var_dump($res);
```
2. ObjectClient 使用方法
```php
    // minio配置信息
    $minioConfig = [
        'key' => 'minioadmin',
        'secret' => 'minioadmin',
        'region' => '',
        'version' => 'latest',
        'endpoint' => 'http://172.28.1.3:9000',
        'bucket' => 'test',
    ];
    
    $objectClient = new \Minoi\Client\ObjectClient($minioConfig);
    
    //上传文件(注意-存在同名直接覆盖,最好生成uuid+timestamp)
    $res = $objectClient->putObjectBySavePath(__DIR__ . "/composer.json", "php/composer.json");
    var_dump($res);
    
    //写入内容上传文件，返回对象服务器地址
    $res = $objectClient->putObjectByContent("php/helloworld.txt", "helloworld");
    
    
    //获取对象流
    $stream = $objectClient->getObject("php/helloworld.txt");
    if ($stream != false) {
        var_dump($stream->getContents());
    }
    
    //下载文件到本地
    $objectClient->getObjectSaveAs("php/helloworld.txt", "demo.txt");
    
    //获取图片的地址url,有限时间时间120S，不填默认1天
    $url = $objectClient->getObjectUrl("php/helloworld.txt", time() + 120);
    var_dump($url);
    
    //复制对象
    $res = $objectClient->copyObject("php/helloworld.txt", "php/helloworld2.txt");
    
    //获取对象，1000个对象
    $list = $objectClient->listObjects();
    var_dump($list);
    
    $list2 = $objectClient->getAllObjects();
    var_dump($list2);
    
    //有可以是数组
    $objectClient->removeObject("php/helloworld.txt");
    $objectClient->removeObject([
        "php/helloworld.txt",
        "php/helloworld2.txt"
    ]);
    echo "-----------------" . PHP_EOL;
    $results = $objectClient->getPaginator("test");
    foreach ($results as $result) {
        foreach ($result['Contents'] as $object) {
            echo $object['Key'] . "\n";
        }
    }
```