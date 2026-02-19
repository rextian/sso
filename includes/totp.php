<?php
/**
 * REXTIAN SSO - TOTP 辅助（RFC 6238）
 * 无外部依赖，兼容 Google Authenticator
 */

class TotpHelper {
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** 生成随机 Base32 密钥（16 字符 = 80 bits） */
    public static function generateSecret(int $length = 16): string {
        $secret = '';
        $chars = self::BASE32_CHARS;
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }

    /** Base32 解码 */
    public static function base32Decode(string $input): string {
        $input = strtoupper(str_replace([' ', '-'], '', $input));
        $buffer = 0;
        $bufferSize = 0;
        $output = '';
        $chars = self::BASE32_CHARS;
        $map = array_flip(str_split($chars));
        for ($i = 0; $i < strlen($input); $i++) {
            $c = $input[$i];
            if (!isset($map[$c])) continue;
            $buffer = ($buffer << 5) | $map[$c];
            $bufferSize += 5;
            if ($bufferSize >= 8) {
                $bufferSize -= 8;
                $output .= chr(($buffer >> $bufferSize) & 0xFF);
            }
        }
        return $output;
    }

    /** 生成 TOTP 码（6 位） */
    public static function getCode(string $secret, ?int $timestamp = null): string {
        $timestamp = $timestamp ?? time();
        $counter = (int) floor($timestamp / 30);
        $secretBin = self::base32Decode($secret);
        $counterBin = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $counterBin, $secretBin, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncated = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );
        return str_pad((string) ($truncated % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /** 验证 TOTP 码（允许 ±1 个时间窗口的时钟偏差） */
    public static function verify(string $secret, string $code, int $window = 1): bool {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6) return false;
        $timestamp = time();
        for ($i = -$window; $i <= $window; $i++) {
            $ts = $timestamp + ($i * 30);
            if (self::getCode($secret, $ts) === $code) {
                return true;
            }
        }
        return false;
    }

    /** 生成 otpauth URL（用于二维码） */
    public static function getOtpAuthUrl(string $secret, string $account, string $issuer = 'REXTIAN ID'): string {
        $label = rawurlencode($issuer . ':' . $account);
        $params = [
            'secret' => $secret,
            'issuer' => $issuer,
        ];
        return 'otpauth://totp/' . $label . '?' . http_build_query($params);
    }
}
