<?php
namespace Minoi\Client;

use Aws\Api\DateTimeResult;
use Aws\Exception\AwsException;
use Minoi\MinioClient;

/**
 * Class BucketClient
 * @package Minoi\Object
 * bucket操作
 */
class BucketClient extends MinioClient
{
    /**
     * @param String $bucket
     * @return bool
     * 创建bucket
     */
    public function create(String $bucket = null): bool
    {
        if (is_null($bucket)) {
            $bucket = $this->bucket;
        }
        try {
            $this->s3Client->createBucket(['Bucket' => $bucket]);
        } catch (AwsException $awsException) {
            return $this->returnFalseAndSaveExceptionMessage($awsException);
        }
        return true;
    }

    /**
     * @param String $bucket
     * @return bool
     * 删除桶
     */
    public function delete(String $bucket = null): bool
    {
        if (is_null($bucket)) {
            $bucket = $this->bucket;
        }
        try {
            $this->s3Client->deleteBucket([
                'Bucket' => $bucket,
            ]);
        } catch (AwsException $awsException) {
            return $this->returnFalseAndSaveExceptionMessage($awsException);
        }
        return true;
    }

    /**
     * 获取列表桶
     * @return array|bool 成功返回桶列表，失败返回false
     */
    public function listBuckets()
    {
        try {
            $result = $this->s3Client->listBuckets([]);
            $list = $result->get('Buckets');
            return array_map(static function ($object) {
                /* @var DateTimeResult $date */
                $date = $object['CreationDate'];
                return [
                    'name' => $object['Name'],
                    'createdAt' => $date->getTimestamp(),
                ];
            }, $list);
        } catch (AwsException $awsException) {
            return $this->returnFalseAndSaveExceptionMessage($awsException);
        }
    }


    /**
     * 获取策略
     * @param string|null $bucket
     * @return array|bool
     */
    public function getBucketPolicies(string $bucket = null)
    {
        if (is_null($bucket)) {
            $bucket = $this->bucket;
        }
        try {
            $result = $this->s3Client->getBucketPolicy([
                'Bucket' => $bucket,
            ]);
            $policy = $result->get('Policy');
            return json_decode($policy->getContents(), 1);
        } catch (AwsException $awsException) {
            return $this->returnFalseAndSaveExceptionMessage($awsException);
        }
    }


    /**
     * 设置策略，会覆盖原来设置的!!!!!!
     * @param array $policies
     * $policies = [
     *   'read' => ['read1', 'read2'], //只读
     *   'write' => ['write1', 'write2'],  //只写
     *   'read+write' => ['readwrite1', 'rw'] // 读+写
     *   ];
     * @param string|null $bucket
     * @return bool
     */
    public function setBucketPolicies($policies = [], string $bucket = null): bool
    {
        if (is_null($bucket)) {
            $bucket = $this->bucket;
        }
        $policy_string = $this->getPolicyString($policies, $bucket);
        try {
            $this->s3Client->putBucketPolicy([
                'Bucket' => $bucket,
                'Policy' => $policy_string
            ]);
        } catch (AwsException $awsException) {
            return $this->returnFalseAndSaveExceptionMessage($awsException);
        }
        return true;
    }

    /**
     * 删除所有策略
     * @param string|null $bucket
     * @return bool
     */
    public function deleteBucketPolicies(string $bucket = null): bool
    {
        if (is_null($bucket)) {
            $bucket = $this->bucket;
        }
        try {
            $this->s3Client->deleteBucketPolicy([
                'Bucket' => $bucket,
            ]);
        } catch (AwsException $awsException) {
            return $this->returnFalseAndSaveExceptionMessage($awsException);
        }
        return true;
    }

    /**
     * 生成policy设置字符串
     * @param $policies
     * @param $bucket
     * @return string
     */
    protected function getPolicyString($policies, $bucket): string
    {
        $policy_types = array_keys($policies);
        sort($policy_types);
        $policy_types = implode('&', $policy_types);
        switch ($policy_types) {
            case 'read':
                $paths = $policies['read'];
                $prefix_string = '"' . implode('","', $paths) . '"';
                $resource = '"'
                    . implode(
                        '","',
                        array_map(static function ($path) use ($bucket) {
                            return "arn:aws:s3:::$bucket/$path*";
                        }, $paths)
                    )
                    . '"';
                $str = <<<STR
{
	"Version": "2012-10-17",
	"Statement": [{
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:GetBucketLocation"],
		"Resource": ["arn:aws:s3:::$bucket"]
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:ListBucket"],
		"Resource": ["arn:aws:s3:::$bucket"],
		"Condition": {
			"StringEquals": {
				"s3:prefix": [$prefix_string]
			}
		}
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:GetObject"],
		"Resource": [$resource]
	}]
}
STR;
                break;
            case 'write':
                $paths = $policies['write'];
                $resource = '"'
                    . implode(
                        '","',
                        array_map(static function ($path) use ($bucket) {
                            return "arn:aws:s3:::$bucket/$path*";
                        }, $paths)
                    )
                    . '"';
                $str = <<<STR
{
	"Version": "2012-10-17",
	"Statement": [{
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:GetBucketLocation", "s3:ListBucketMultipartUploads"],
		"Resource": ["arn:aws:s3:::$bucket"]
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:AbortMultipartUpload", "s3:DeleteObject", "s3:ListMultipartUploadParts", "s3:PutObject"],
		"Resource": [$resource]
	}]
}
STR;

                break;
            case 'read+write':
                $paths = $policies['read+write'];
                $prefix_string = '"' . implode('","', $paths) . '"';
                $resource = '"'
                    . implode(
                        '","',
                        array_map(static function ($path) use ($bucket) {
                            return "arn:aws:s3:::$bucket/$path*";
                        }, $paths)
                    )
                    . '"';
                $str = <<<STR
{
	"Version": "2012-10-17",
	"Statement": [{
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:GetBucketLocation", "s3:ListBucketMultipartUploads"],
		"Resource": ["arn:aws:s3:::$bucket"]
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:ListBucket"],
		"Resource": ["arn:aws:s3:::$bucket"],
		"Condition": {
			"StringEquals": {
				"s3:prefix": [$prefix_string]
			}
		}
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:GetObject", "s3:ListMultipartUploadParts", "s3:PutObject", "s3:AbortMultipartUpload", "s3:DeleteObject"],
		"Resource": [$resource]
	}]
}
STR;
                break;
            case 'read&read+write':
                $prefix_string = '"' . implode('","', array_merge($policies['read'], $policies['read+write'])) . '"';
                $all_resource = '"'
                    . implode(
                        '","',
                        array_map(static function ($path) use ($bucket) {
                            return "arn:aws:s3:::$bucket/$path*";
                        }, $policies['read+write'])
                    )
                    . '"';
                $read_resource = '"'
                    . implode(
                        '","',
                        array_map(static function ($path) use ($bucket) {
                            return "arn:aws:s3:::$bucket/$path*";
                        }, $policies['read'])
                    )
                    . '"';
                $str = <<<STR
{
	"Version": "2012-10-17",
	"Statement": [{
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:GetBucketLocation", "s3:ListBucketMultipartUploads"],
		"Resource": ["arn:aws:s3:::$bucket"]
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:ListBucket"],
		"Resource": ["arn:aws:s3:::$bucket"],
		"Condition": {
			"StringEquals": {
				"s3:prefix": [$prefix_string]
			}
		}
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:AbortMultipartUpload", "s3:DeleteObject", "s3:GetObject", "s3:ListMultipartUploadParts", "s3:PutObject"],
		"Resource": [$all_resource]
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:GetObject"],
		"Resource": [$read_resource]
	}]
}
STR;

                break;
            case 'read+write&write':
                $prefix_string = '"' . implode('","', array_merge($policies['read'], $policies['read+write'])) . '"';
                $all_resource = '"'
                    . implode(
                        '","',
                        array_map(static function ($path) use ($bucket) {
                            return "arn:aws:s3:::$bucket/$path*";
                        }, $policies['read+write'])
                    )
                    . '"';
                $read_resource = '"'
                    . implode(
                        '","',
                        array_map(static function ($path) use ($bucket) {
                            return "arn:aws:s3:::$bucket/$path*";
                        }, $policies['read'])
                    )
                    . '"';

                $str = <<<STR
{
	"Version": "2012-10-17",
	"Statement": [{
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:GetBucketLocation", "s3:ListBucket", "s3:ListBucketMultipartUploads"],
		"Resource": ["arn:aws:s3:::$bucket"]
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:ListBucket"],
		"Resource": ["arn:aws:s3:::$bucket"],
		"Condition": {
			"StringEquals": {
				"s3:prefix": [$prefix_string]
			}
		}
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:GetObject"],
		"Resource": [$read_resource]
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:AbortMultipartUpload", "s3:DeleteObject", "s3:ListMultipartUploadParts", "s3:PutObject"],
		"Resource": [$all_resource]
	}]
}
STR;

                break;
            case 'read&write':
                $prefix_string = '"' . implode('","', $policies['read']) . '"';

                $read_resource = '"'
                    . implode(
                        '","',
                        array_map(static function ($path) use ($bucket) {
                            return "arn:aws:s3:::$bucket/$path*";
                        }, $policies['read'])
                    )
                    . '"';
                $write_resource = '"'
                    . implode(
                        '","',
                        array_map(static function ($path) use ($bucket) {
                            return "arn:aws:s3:::$bucket/$path*";
                        }, $policies['write'])
                    )
                    . '"';
                $str = <<<STR
{
	"Version": "2012-10-17",
	"Statement": [{
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:ListBucketMultipartUploads", "s3:GetBucketLocation", "s3:ListBucket"],
		"Resource": ["arn:aws:s3:::$bucket"]
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:ListBucket"],
		"Resource": ["arn:aws:s3:::$bucket"],
		"Condition": {
			"StringEquals": {
				"s3:prefix": [$prefix_string]
			}
		}
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:GetObject"],
		"Resource": [$read_resource]
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:PutObject", "s3:AbortMultipartUpload", "s3:DeleteObject", "s3:ListMultipartUploadParts"],
		"Resource": [$write_resource]
	}]
}
STR;
                break;
            case 'read&read+write&write':
                $prefix_string = '"' . implode('","', array_merge($policies['read'], $policies['read+write'])) . '"';
                $all_resource = '"'
                    . implode(
                        '","',
                        array_map(function ($path) use ($bucket) {
                            return "arn:aws:s3:::$bucket/$path*";
                        }, $policies['read+write'])
                    )
                    . '"';
                $read_resource = '"'
                    . implode(
                        '","',
                        array_map(function ($path) use ($bucket) {
                            return "arn:aws:s3:::$bucket/$path*";
                        }, $policies['read'])
                    )
                    . '"';
                $write_resource = '"'
                    . implode(
                        '","',
                        array_map(function ($path) use ($bucket) {
                            return "arn:aws:s3:::$bucket/$path*";
                        }, $policies['write'])
                    )
                    . '"';
                $str = <<<STR
{
	"Version": "2012-10-17",
	"Statement": [{
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:GetBucketLocation", "s3:ListBucket", "s3:ListBucketMultipartUploads"],
		"Resource": ["arn:aws:s3:::$bucket"]
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:ListBucket"],
		"Resource": ["arn:aws:s3:::$bucket"],
		"Condition": {
			"StringEquals": {
				"s3:prefix": [$prefix_string]
			}
		}
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:DeleteObject", "s3:GetObject", "s3:ListMultipartUploadParts", "s3:PutObject", "s3:AbortMultipartUpload"],
		"Resource": [$all_resource]
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:GetObject"],
		"Resource": [$read_resource]
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:AbortMultipartUpload", "s3:DeleteObject", "s3:ListMultipartUploadParts", "s3:PutObject"],
		"Resource": [$write_resource]
	}]
}
STR;
                break;
            default:
                $str = '';
                break;
        }
        return $str;
    }
}