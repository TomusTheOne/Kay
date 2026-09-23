<?php
declare(strict_types=1);

/**
 * Every French string the admin can show, found by reading the code rather
 * than by trusting anyone to remember them: the literal arguments of kay_t(),
 * kay_th() and kay_tn(), and the labels in the constant tables. The test
 * runner checks each one has a Spanish translation, so an untranslated
 * string fails the tests instead of appearing in French in front of Kay.
 *
 * @param string[] $files
 * @return string[] unique, in the order found
 */
function kay_i18n_scan(array $files): array
{
    $found = [];
    foreach ($files as $file) {
        $tokens = token_get_all((string) file_get_contents($file));
        $count  = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $tok = $tokens[$i];
            if (!is_array($tok) || $tok[0] !== T_STRING || !in_array($tok[1], ['kay_t', 'kay_th', 'kay_tn'], true)) {
                continue;
            }
            // Skip the definitions themselves: `function kay_t(`.
            $prev = $i - 1;
            while ($prev >= 0 && is_array($tokens[$prev]) && $tokens[$prev][0] === T_WHITESPACE) {
                $prev--;
            }
            if ($prev >= 0 && is_array($tokens[$prev]) && $tokens[$prev][0] === T_FUNCTION) {
                continue;
            }
            // kay_tn's first argument is the count; its strings are the next two.
            $wanted = $tok[1] === 'kay_tn' ? [1, 2] : [0];
            $arg = 0;
            $depth = 0;
            for ($j = $i + 1; $j < $count; $j++) {
                $t = $tokens[$j];
                if ($t === '(' || $t === '[') {
                    $depth++;
                    continue;
                }
                if ($t === ')' || $t === ']') {
                    if (--$depth === 0) {
                        break;
                    }
                    continue;
                }
                if ($t === ',' && $depth === 1) {
                    $arg++;
                    continue;
                }
                if ($depth === 1 && is_array($t) && $t[0] === T_CONSTANT_ENCAPSED_STRING && in_array($arg, $wanted, true)) {
                    // Only a whole literal argument, not a piece of a concatenation.
                    $k = $j + 1;
                    while (is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) {
                        $k++;
                    }
                    if ($tokens[$k] === ',' || $tokens[$k] === ')') {
                        $found[] = kay_i18n_literal($t[1]);
                    }
                }
            }
        }
    }

    foreach ([KAY_STATUSES, KAY_ACTION_LABELS, KAY_PAYMENT_METHODS, KAY_PAYMENT_KINDS, KAY_BOOKING_VIEWS,
              KAY_NOTE_KINDS, KAY_SEGMENTS, KAY_CUSTOMER_SORTS, KAY_TRAFFIC_RANGES, KAY_FLASH,
              KAY_LANG_NAMES, KAY_SITE_LANGS, KAY_DEVICE_NAMES] as $table) {
        foreach ($table as $label) {
            $found[] = $label;
        }
    }
    foreach (KAY_NAV as [$long, $short]) {
        $found[] = $long;
        $found[] = $short;
    }
    return array_values(array_unique($found));
}

/** The value of a PHP string literal token, quotes and escapes resolved. */
function kay_i18n_literal(string $token): string
{
    if ($token[0] === "'") {
        return str_replace(["\\\\", "\\'"], ["\\", "'"], substr($token, 1, -1));
    }
    return stripcslashes(substr($token, 1, -1));
}
