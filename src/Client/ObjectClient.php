<?php

namespace Minoi\Client;

use Aws\Api\DateTimeResult;
use Aws\Exception\AwsException;
use Aws\ResultPaginator;
use DateTime;
use GuzzleHttp\Psr7\LazyOpenStream;
use Minoi\MinioClient;

/**
 * Class ObjectClient
 * @package Minoi\Client
 * 资源操作客户端
 */
class ObjectClient extends MinioClient
{
    /**
     * 上传对象
     * @param string $localObjectPath 本地对象路径，支持相对和绝对路径
     * @param string|null $storageSavePath minio存储路径，自动创建文件夹
     *                    如a/b.txt, 注意开头不能是 “/”, 回报错
     * @return string|bool 成功返回真实的$storageSavePath
     *
     * 注意: 直接覆盖的。按时间戳加生成uuid作为名称
     */
    public function putObjectBySavePath($localObjectPath, $storageSavePath = null)
    {
        return $this->putObjectBySavePathToBucket($this->bucket, $localObjectPath, $storageSavePath);
    }

    /**
     * 上传对象（指定bucket）
     * @param string $bucket
     * @param string $localObjectPath 本地对象路径，支持相对和绝对路径
     * @param string|null $storageSavePath minio存储路径，自动创建文件夹，如a/b.txt, 注意开头不能是 “/”, 回报错
     * @return string|bool 成功返回真实的$storageSavePath
     */
    public function putObjectBySavePathToBucket(string $bucket, string $localObjectPath, string $storageSavePath = null)
    {
        if (empty($bucket)) {
            return $this->returnFalseByBucketNoSetError();
        }
        if ($storageSavePath === null) {
            $storageSavePath = $localObjectPath;
        }
        try {
            $storageSavePath = formatStorageSavePath($storageSavePath);
            $this->s3Client->putObject([
                'Bucket' => $bucket,
                'Key' => $storageSavePath,
                'SourceFile' => $localObjectPath,
            ]);
            return $storageSavePath;
        } catch (AwsException $awsException) {
            return $this->returnFalseAndSaveExceptionMessage($awsException);
        }
    }


    /**
     * 写入内容到对象并上传
     * @param string $storageSavePath
     * @param string $content
     * @return string|bool 成功返回真实的$storageSavePath
     */
    public function putObjectByContent(string $storageSavePath, string $content)
    {
        return $this->putObjectByContentToBucket($this->bucket, $storageSavePath, $content);
    }


    /**
     * 写入内容到对象并上传（指定bucket）
     * @param string $bucket
     * @param string $storageSavePath
     * @param string $content
     * @return string|bool 成功返回真实的$storageSavePath
     */
    public function putObjectByContentToBucket(string $bucket, string $storageSavePath, string $content)
    {
        if (empty($bucket)) {
            return $this->returnFalseByBucketNoSetError();
        }
        $storageSavePath = formatStorageSavePath($storageSavePath);
        try {
            $this->s3Client->putObject([
                'Bucket' => $bucket,
                'Key' => $storageSavePath,
                'Body' => $content
            ]);
            return $storageSavePath;
        } catch (AwsException $awsException) {
            return $this->returnFalseAndSaveExceptionMessage($awsException);
        }
    }


    /**
     * 获取文件
     * @param string $storageSavePath
     * @return bool|mixed|LazyOpenStream
     */
    public function getObject(string $storageSavePath)
    {
        return $this->getObjectInBucket($this->bucket, $storageSavePath);
    }

    /**
     * 获取文件并另存到本地（指定bucket）
     *  save_path: object_example/putObjectByContent.txt
     * @param string $bucket
     * @param string $storageSavePath
     * @param string $localSaveAsPath
     * @return bool|LazyOpenStream|mixed
     */
    public function getObjectInBucketSaveAs(string $bucket, string $storageSavePath, string $localSaveAsPath)
    {
        return $this->getObjectInBucket($bucket, $storageSavePath, $localSaveAsPath);
    }

    /**
     * 获取文件并另存到本地
     * @param string $storageSavePath
     * @param string $localSaveAsPath
     * @return bool|mixed|LazyOpenStream
     */
    public function getObjectSaveAs(string $storageSavePath, string $localSaveAsPath)
    {
        return $this->getObjectInBucketSaveAs($this->bucket, $storageSavePath, $localSaveAsPath);
    }

    /**
     * 获取文件（指定bucket）
     * @param string $bucket
     * @param string $storageSavePath
     * @param string $localSaveAsPath
     * @return bool|mixed|LazyOpenStream
     */
    public function getObjectInBucket(string $bucket, string $storageSavePath, string $localSaveAsPath = null)
    {
        if (empty($bucket)) {
            return $this->returnFalseByBucketNoSetError();
        }
        $param = [
            'Bucket' => $bucket,
            'Key' => $storageSavePath,
        ];
        if (!is_null($localSaveAsPath)) {
            $param = [
                'Bucket' => $bucket,
                'Key' => $storageSavePath,
                'SaveAs' => $localSaveAsPath
            ];
        }
        try {
            $result = $this->s3Client->getObject($param);
            return $result['Body'];
        } catch (AwsException $awsException) {
            return $this->returnFalseAndSaveExceptionMessage($awsException);
        }
    }


    /**
     * 获取url 自动判断
     * @param string $storageSavePath
     * @param null $expiredAt
     * @return string
     */
    public function getObjectUrl(string $storageSavePath, $expiredAt = null)
    {
        return $this->getObjectUrlInBucket($this->bucket, $storageSavePath, $expiredAt);
    }


    public function getObjectUrlInBucket(string $bucket, string $storageSavePath, $expiredAt = null)
    {
        if ($this->isPublic($bucket, $storageSavePath)) {
            return $this->getObjectPlainUrlInBucket($bucket, $storageSavePath);
        }

        return $this->getObjectPresignedUrlInBucket($bucket, $storageSavePath, $expiredAt);
    }

    /**
     * 判断是否public
     * @param string $bucket
     * @param string $storageSavePath
     * @return bool
     */
    public function isPublic(string $bucket, string $storageSavePath): bool
    {
        $policies = $this->getPolicies($bucket);
        foreach ($policies as $policy) {
            if (pregStr($policy, $storageSavePath)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 获取开放url
     * @param string $storageSavePath
     * @return string
     */
    public function getObjectPlainUrl(string $storageSavePath)
    {
        return $this->getObjectPlainUrlInBucket($this->bucket, $storageSavePath);
    }

    public function getObjectPlainUrlInBucket(string $bucket, string $storageSavePath)
    {
        return $this->s3Client->getObjectUrl($bucket, $storageSavePath);
    }

    /**
     * 获取对象（预览/下载）URL（指定bucket）
     * @param $storageSavePath
     * @param null $expiredAt
     * @return string
     */
    public function getObjectPresignedUrl(string $storageSavePath, $expiredAt = null)
    {
        return $this->getObjectPresignedUrlInBucket($this->bucket, $storageSavePath, $expiredAt);
    }

    /**
     * 获取对象（预览/下载）URL
     * @param string $bucket
     * @param string $storageSavePath
     * @param null|int|string|DateTime $expiredAt The time at which the URL should
     *     expire. This can be a Unix timestamp, a PHP DateTime object, or a
     *     string that can be evaluated by strtotime.
     * @return string
     */
    public function getObjectPresignedUrlInBucket(string $bucket, string $storageSavePath, $expiredAt = null)
    {
        // Get a command object from the client
        $command = $this->s3Client->getCommand('GetObject', [
            'Bucket' => $bucket,
            'Key' => $storageSavePath
        ]);

        if (is_null($expiredAt)) {
            $expiredAt = time() + $this->urlExpireTime;
        }
        $presignedRequest = $this->s3Client->createPresignedRequest($command, $expiredAt);
        return (string)$presignedRequest->getUri();
    }

    /**
     * 删除对象 (可批量)（指定bucket）
     * @param string $bucket
     * @param array|string $storageSavePath
     * @return bool
     */
    public function removeObjectInBucket(string $bucket, $storageSavePath)
    {
        try {
            if (is_array($storageSavePath)) {
                $this->s3Client->deleteObjects([
                    'Bucket' => $bucket,
                    'Delete' => [
                        'Objects' => array_map(function ($key) {
                            return ['Key' => $key];
                        }, $storageSavePath)
                    ],
                ]);
            } else {
                $this->s3Client->deleteObject([
                    'Bucket' => $bucket,
                    'Key' => $storageSavePath
                ]);
            }
            return true;
        } catch (AwsException $awsException) {
            return $this->returnFalseAndSaveExceptionMessage($awsException);
        }
    }

    /**
     * 删除对象(可批量)
     * @param array|string $storageSavePath
     * @return bool
     */
    public function removeObject($storageSavePath)
    {
        return $this->removeObjectInBucket($this->bucket, $storageSavePath);
    }

    /*
     * 低级别的 listObjects() 方法将映射到底层 Amazon S3 REST API。每个 listObjects() 请求均返回最多有 1000 个对象的页面。如果您
     * 的存储桶中有超过 1000 个对象，则将截断您的响应，并且您需要发送其他 listObjects() 请求，以检索下一组 1000 个对象。
     *
     * 高级别 ListObjects 分页工具使列出存储桶中包含的对象的任务变得更轻松。要使用 ListObjects 分页工具创建对象列表，请执行从
     * Aws/AwsClientInterface 类继承的 Amazon S3 客户端 getPaginator() 方法，将 ListObjects 作为第一个参数，将包含从指定存储桶返
     * 回的对象的数组作为第二个参数。当作为 ListObjects 分页工具使用时，getPaginator() 方法将返回指定存储桶中包含的所有对象。不存在
     * 1000 个对象的限制，因此，您无需担心响应是否被截断。
     */
    /**
     * 获取1000个对象
     * @param string $prefix 前缀过滤
     * @return array|bool
     */
    public function listObjects($prefix = '')
    {
        return $this->listObjectsInBucket($this->bucket, $prefix);
    }


    /**
     * @param string $bucket
     * @param string $prefix
     * @return ResultPaginator
     * 返回一个迭代器,可以foreach
     * url:https://docs.amazonaws.cn/sdk-for-php/v3/developer-guide/guide_paginators.html
     */
    public function getPaginator(string $bucket, string $prefix = ''): ResultPaginator
    {
        return $this->s3Client->getPaginator('ListObjects', [
            'Bucket' => $bucket,
            'Prefix' => $prefix
        ]);
    }


    /**
     * 获取1000个对象（指定bucket）
     * @param string $bucket
     * @param string $prefix 前缀过滤
     * @return array|bool
     */
    public function listObjectsInBucket(string $bucket, string $prefix = '')
    {
        try {
            $result = $this->s3Client->listObjects([
                'Bucket' => $bucket,
                'Prefix' => $prefix
            ]);
            if (empty($result['Contents'])) {
                return [];
            }
            return array_map(static function ($object) {
                /* @var DateTimeResult $lastModified */
                $lastModified = $object['LastModified'];
                return [
                    'key' => $object['Key'],
                    'size' => $object['Size'],
                    'lastModified' => $lastModified->getTimestamp(),
                ];
            }, $result['Contents']);
        } catch (AwsException $awsException) {
            return $this->returnFalseAndSaveExceptionMessage($awsException);
        }
    }

    /**
     * 获取所有对象
     * @param string $prefix 前缀过滤
     * @return array|bool
     */
    public function getAllObjects($prefix = '')
    {
        return $this->getAllObjectsInBucket($this->bucket, $prefix);
    }

    /**
     * /**
     * 获取所有对象（指定bucket）
     * @param string $bucket
     * @param string $prefix 前缀过滤
     * @return array|bool
     */
    public function getAllObjectsInBucket(string $bucket, $prefix = '')
    {
        try {
            $result = $this->s3Client->getPaginator('ListObjects', [
                'Bucket' => $bucket,
                'Prefix' => $prefix
            ]);
            if (empty($result->current()['Contents'])) {
                return [];
            }
            return array_map(static function ($object) {
                /* @var DateTimeResult $lastModified */
                $lastModified = $object['LastModified'];
                return [
                    'key' => $object['Key'],
                    'size' => $object['Size'],
                    'lastModified' => $lastModified->getTimestamp(),
                ];
            }, $result->current()['Contents']);

        } catch (AwsException $awsException) {
            return $this->returnFalseAndSaveExceptionMessage($awsException);
        }
    }

    /**
     * 复制对象
     * @param string $sourceStorageSavePath 源对象路径
     * @param string|null $targetStorageSavePath 目标保存对象路径，默认是源对象路径加上部分前缀[copy_time()_]
     * @param string|null $targetBucket 目标对象bucket, 默认是源对象bucket
     * @return bool|string $targetStorageSavePath
     */
    public function copyObject(string $sourceStorageSavePath, string $targetStorageSavePath = null, string $targetBucket = null)
    {
        return $this->copyObjectInBucket($sourceStorageSavePath, $this->bucket, $targetStorageSavePath, $targetBucket);
    }

    /**
     * 复制对象
     * @param string $sourceStorageSavePath 源对象路径
     * @param string $sourceBucket 源对象bucket
     * @param string|null $targetStorageSavePath 目标保存对象路径，默认是源对象路径加上部分前缀[copy_time()_]
     * @param string|null $targetBucket 目标对象bucket, 默认是源对象bucket
     * @return bool|string $targetStorageSavePath
     */
    public function copyObjectInBucket(string $sourceStorageSavePath, string $sourceBucket, string $targetStorageSavePath = null, string $targetBucket = null)
    {
        try {
            if (is_null($targetBucket)) {
                $targetBucket = $sourceBucket;
            }
            if (is_null($targetStorageSavePath)) {
                $targetStorageSavePath = 'copy_' . time() . '_' . $sourceStorageSavePath;
            }
            $this->s3Client->copyObject([
                'Bucket' => $targetBucket,
                'Key' => $targetStorageSavePath,
                'CopySource' => "{$sourceBucket}/{$sourceStorageSavePath}",
            ]);
            return $targetStorageSavePath;
        } catch (AwsException $awsException) {
            return $this->returnFalseAndSaveExceptionMessage($awsException);
        }
    }

}