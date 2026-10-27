<?php

namespace App\Support;

/** SMS text helpers shared by templates and compose (legacy General_DAL rules). */
final class SmsText
{
    private const GSM = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    private const GSM_EXTENDED = "\f^{}\\[~]|€";

    /** Legacy convert_msg_text_sp_char: strip tags and swap typographic characters. */
    public static function clean(string $message): string
    {
        return str_replace(
            ['…', "\r\n", '’', '‘', '“', '”', 'Ã', 'Í', 'í', 'ì', 'É', 'é', 'è', 'ę', 'ú', 'ó', 'ä', '`', '·', '–', '½', "\u{00A0}", "\u{202F}"],
            ['...', "\n", "'", "'", '"', '"', 'A', 'I', 'i', 'i', 'E', 'e', 'e', 'e', 'u', 'o', 'a', "'", '.', '-', '1/2', ' ', ' '],
            strip_tags($message),
        );
    }

    public static function isUnicode(string $message): bool
    {
        foreach (mb_str_split($message) as $char) {
            if (mb_strpos(self::GSM, $char) === false && mb_strpos(self::GSM_EXTENDED, $char) === false) {
                return true;
            }
        }

        return false;
    }

    /** Number of SMS parts: GSM 160/153 (extended chars count twice), Unicode 70/67. */
    public static function parts(string $message): int
    {
        if (self::isUnicode($message)) {
            $length = mb_strlen($message);

            return $length <= 70 ? 1 : (int) ceil($length / 67);
        }

        $length = 0;

        foreach (mb_str_split($message) as $char) {
            $length += mb_strpos(self::GSM_EXTENDED, $char) !== false ? 2 : 1;
        }

        return $length <= 160 ? 1 : (int) ceil($length / 153);
    }

        /** Legacy detectEmojis: emojis, pictographs, misc symbols and flags are not allowed. */
    public static function hasEmoji(string $message): bool
    {
        return (bool) preg_match(
            '/[\x{1F300}-\x{1F64F}\x{1F680}-\x{1F6FF}\x{1F700}-\x{1FAFF}\x{1F004}-\x{1F0CF}\x{1F1E6}-\x{1F1FF}\x{2600}-\x{27BF}\x{2B50}\x{23F0}\x{231A}-\x{231B}]/u',
            $message,
        );
    }

    /**
     * Legacy Check_Blocked_Text_DAL: case-insensitive match of every blocked word; a word is
     * trimmed only when it is as long as the whole message.
     *
     * @param  array<int, string>  $words
     * @return array<int, string> the words found
     */
    public static function blockedWords(string $message, array $words): array
    {
        $found = [];

        foreach ($words as $word) {
            if (trim($word) === '') {
                continue;
            }

            $needle = mb_strlen($message) === mb_strlen(trim($word)) ? trim($word) : $word;

            if (preg_match('/' . preg_quote($needle, '/') . '/iu', $message)) {
                $found[] = trim($word);
            }
        }

        return $found;
    }
}