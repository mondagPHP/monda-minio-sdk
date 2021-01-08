<?php
/**
 * @author chenzf@pvc123.com
 * 正则匹配支持字符串是否存在
 */
if (!function_exists("pregStr")) {
    function pregStr($patterns, $value)
    {
        if (!is_array($patterns)) {
            return false;
        }
        foreach ($patterns as $pattern) {
            if ($pattern === '*') {
                return true;
            }
            if ($pattern === $value) {
                return true;
            }
            if (strpos($value, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }
}

/**
 * 格式化处理, 去除前后的“/”
 * @param string $storageSavePath
 * @return string
 */
if (!function_exists("formatStorageSavePath")) {
    function formatStorageSavePath(string $storageSavePath): string
    {
        return trim($storageSavePath, '/');
    }
}

/**
 * generateMiniOFileName 生成minio的文件路径,
 * uniqid — 生成一个唯一ID
 */
if (!function_exists("generateMiniOFileName")) {
    function generateMiniOFileName(string $uploadFileName): string
    {
        $info = pathinfo($uploadFileName);
        return date("Y/m/d/") . uniqid('', true) . "." . $info["extension"];
    }
}