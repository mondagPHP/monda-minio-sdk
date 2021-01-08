<?php

namespace Minoi;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;

/**
 * Class MinoiClient
 * @package Minoi
 * minio sdk
 */
class MinioClient
{
    protected $s3Client;
    protected $bucket;
    //url默认一天有限时间
    protected $urlExpireTime = 24 * 60 * 60;
    protected $publicPolicies = [];

    // 错误状态码
    protected $errorCode;
    // 错误信息
    protected $errorMessage;

    public function __construct($config = [], array $publicPolicies = [])
    {
        $this->s3Client = new S3Client([
            'credentials' => [
                'key' => $config['key'] ?? '',
                'secret' => $config['secret'] ?? '',
            ],
            'region' => $config['region'] ?? '',
            'version' => $config['version'] ?? '',
            'endpoint' => $config['endpoint'] ?? '',
            //minio必须开启
            'use_path_style_endpoint' => true,
        ]);
        $this->bucket = $config['bucket'] ?? '';
        $this->publicPolicies = $publicPolicies;
    }


    /**
     * @param array $policies
     * @param string|null $bucket
     * 设置公开路由
     */
    public function setPolicies(array $policies, string $bucket = null)
    {
        if (is_null($bucket)) {
            $bucket = $this->bucket;
        }
        $this->publicPolicies[$bucket] = $policies;
    }

    /**
     * @param string|null $bucket
     * @return array|mixed
     * 获取公开路由
     */
    public function getPolicies(string $bucket = null)
    {
        if (is_null($bucket)) {
            $bucket = $this->bucket;
        }
        return $this->publicPolicies[$bucket] ?? [];
    }

    public function getS3Client()
    {
        return $this->s3Client;
    }

    public function setS3Client(S3Client $s3Client)
    {
        $this->s3Client = $s3Client;
        return $this;
    }

    public function setBucket($bucket)
    {
        $this->bucket = $bucket;
        return $this;
    }

    public function getBucket()
    {
        return $this->bucket;
    }

    /**
     * @return mixed
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * @return mixed
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * 返回false并保存错误信息
     * @param AwsException $awsException
     * @return bool
     */
    protected function returnFalseAndSaveExceptionMessage(AwsException $awsException)
    {
        if ($awsException->getResponse()) {
            $errorCode = $awsException->getResponse()->getStatusCode();
        } else {
            $errorCode = 400;
        }
        $errorMessage = $awsException->getMessage();
        $this->setErrorMessage($errorCode, $errorMessage);
        return false;
    }

    /**
     * 返回bucket未设置的错误信息
     * @return bool
     */
    protected function returnFalseByBucketNoSetError(): bool
    {
        $this->setErrorMessage(400, "need to set bucket");
        return false;
    }


    /**
     * @param int $errorCode
     * @param string $errorMessage
     * 设置错误信息
     */
    private function setErrorMessage(int $errorCode, string $errorMessage)
    {
        $this->errorCode = $errorCode;
        $this->errorMessage = $errorMessage;
    }
}