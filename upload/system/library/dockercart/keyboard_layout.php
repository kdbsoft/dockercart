<?php
/**
 * Keyboard Layout Converter Library for OpenCart
 *
 * Detects queries typed in the wrong keyboard layout (e.g. Cyrillic text
 * typed with the English layout: "ghbdtn" instead of "привет") and converts
 * them between the QWERTY (Latin) and ЙЦУКЕН (Cyrillic) layouts.
 *
 * @package    DockerCart
 * @subpackage Library
 * @author     DockerCart Official
 * @copyright  2026 DockerCart
 * @license    MIT
 * @version    1.0.0
 */

namespace Dockercart;

class KeyboardLayout {
    /**
     * Latin (QWERTY) key -> Cyrillic (ЙЦУКЕН) character map (lowercase).
     */
    private const LATIN_TO_CYRILLIC = [
        '`' => 'ё',
        'q' => 'й', 'w' => 'ц', 'e' => 'у', 'r' => 'к', 't' => 'е', 'y' => 'н',
        'u' => 'г', 'i' => 'ш', 'o' => 'щ', 'p' => 'з', '[' => 'х', ']' => 'ъ',
        'a' => 'ф', 's' => 'ы', 'd' => 'в', 'f' => 'а', 'g' => 'п', 'h' => 'р',
        'j' => 'о', 'k' => 'л', 'l' => 'д', ';' => 'ж', "'" => 'э',
        'z' => 'я', 'x' => 'ч', 'c' => 'с', 'v' => 'м', 'b' => 'и', 'n' => 'т',
        'm' => 'ь', ',' => 'б', '.' => 'ю',
    ];

    /**
     * Cyrillic (ЙЦУКЕН) character -> Latin (QWERTY) key map (lowercase).
     */
    private const CYRILLIC_TO_LATIN = [
        'ё' => '`',
        'й' => 'q', 'ц' => 'w', 'у' => 'e', 'к' => 'r', 'е' => 't', 'н' => 'y',
        'г' => 'u', 'ш' => 'i', 'щ' => 'o', 'з' => 'p', 'х' => '[', 'ъ' => ']',
        'ф' => 'a', 'ы' => 's', 'в' => 'd', 'а' => 'f', 'п' => 'g', 'р' => 'h',
        'о' => 'j', 'л' => 'k', 'д' => 'l', 'ж' => ';', 'э' => "'",
        'я' => 'z', 'ч' => 'x', 'с' => 'c', 'м' => 'v', 'и' => 'b', 'т' => 'n',
        'ь' => 'm', 'б' => ',', 'ю' => '.',
    ];

    /**
     * Convert a query from one keyboard layout to the other.
     *
     * Direction is detected automatically from the script of the text:
     * - Latin-only text -> converted to Cyrillic
     * - Cyrillic-only text -> converted to Latin
     * - Mixed or script-less text -> returned unchanged
     *
     * @param string $text
     * @return string
     */
    public static function convert($text) {
        $text = (string)$text;

        if ($text === '') {
            return '';
        }

        $has_cyrillic = (bool)preg_match('/[\x{0400}-\x{04FF}]/u', $text);
        $has_latin    = (bool)preg_match('/[a-zA-Z]/u', $text);

        if ($has_cyrillic && !$has_latin) {
            return self::convertToLatin($text);
        }

        if ($has_latin && !$has_cyrillic) {
            return self::convertToCyrillic($text);
        }

        return $text;
    }

    /**
     * Convert Latin (QWERTY) text to Cyrillic (ЙЦУКЕН).
     *
     * @param string $text
     * @return string
     */
    public static function convertToCyrillic($text) {
        $result = '';

        for ($i = 0, $len = mb_strlen($text, 'UTF-8'); $i < $len; $i++) {
            $char  = mb_substr($text, $i, 1, 'UTF-8');
            $lower = mb_strtolower($char, 'UTF-8');

            if (isset(self::LATIN_TO_CYRILLIC[$lower])) {
                $mapped = self::LATIN_TO_CYRILLIC[$lower];

                if ($char === mb_strtoupper($char, 'UTF-8') && $char !== $lower) {
                    $mapped = mb_strtoupper($mapped, 'UTF-8');
                }

                $char = $mapped;
            }

            $result .= $char;
        }

        return $result;
    }

    /**
     * Convert Cyrillic (ЙЦУКЕН) text to Latin (QWERTY).
     *
     * @param string $text
     * @return string
     */
    public static function convertToLatin($text) {
        $result = '';

        for ($i = 0, $len = mb_strlen($text, 'UTF-8'); $i < $len; $i++) {
            $char  = mb_substr($text, $i, 1, 'UTF-8');
            $lower = mb_strtolower($char, 'UTF-8');

            if (isset(self::CYRILLIC_TO_LATIN[$lower])) {
                $mapped = self::CYRILLIC_TO_LATIN[$lower];

                if ($char === mb_strtoupper($char, 'UTF-8') && $char !== $lower) {
                    $mapped = mb_strtoupper($mapped, 'UTF-8');
                }

                $char = $mapped;
            }

            $result .= $char;
        }

        return $result;
    }
}
