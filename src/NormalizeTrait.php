<?php
namespace SamIT\Streams;

/**
 * Class NormalizeTrait
 * @package SamIT\Streams
 *
 * This normalizes some data obtained from sources that do not have native types (like CSV), or cleans up strings.
 *
 */
trait NormalizeTrait
{
    protected function normalize($value, $type, $row) {
        $value = trim($value);

        // Support custom validation / normalization functions.
        if (empty($value) && $type instanceof \Closure) {
            return $type(null, $row);
        } elseif ($type instanceof \Closure) {
            return $type($value, $row);
        } elseif (empty($value)) {
            return null;
        }

        switch ($type) {

            case '!bool':
                // Field contains a boolean, but we want to store the negation.
                $invert = true;
            case 'bool':
                // Check for textual representation of booleans.
                if ($value === 'J' || $value === 'Y') {
                    return isset($invert) ? !true : true;
                } elseif ($value == 'N') {
                    return isset($invert) ? !false : false;
                } elseif (is_numeric($value)) {
                    return isset($invert) ? !($value != 0) : $value != 0;
                }
                throw new \Exception("Unknown boolean value $value");
            case 'initials':
                // Remove anything that is not a letter.
                return preg_replace('/[^[:alpha:]]+/', '', $value);
            case 'gender':
                // Gender, either M(ale), F(emale) or null.
                // O is used for O(ther)
                if ($value === 'M') {
                    return 'M';
                } elseif ($value === 'V' || $value === 'F') {
                    return 'F';
                } elseif ($value == 'O') {
                    return null;
                }
                throw new \Exception("Unknown gender value $value");
            case 'int_id':
                // An integer identifier, the range does not include 0, so 0 is replaced with null.
                return intval($value) === 0 ? null : intval($value);
            case 'int':
                return is_numeric($value) ? intval($value) : null;
            case 'string':
                return $this->normalizeString($value);
            case 'string-utf8':
                return $this->normalizeString($value, null, false);

            case 'pc':
                // Parse Dutch postal code.
                if (preg_match('/([[:digit:]]{4})\s*([[:alpha:]]{2})/', $value, $matches)) {
                    return $matches[1] . strtoupper($matches[2]);
                } else {
                    return null;
                }
            case 'phone' :
                // Dumb phone number removes all non numerics.
                return preg_replace("/[^0-9]/", "", $value);
            case 'date':
                // Parse a date in YYYYMMDD format.
                $year = substr($value, 0, 4);
                $month = substr($value, 4, 2);
                $day = substr($value, 6, 2);
                $result = $year . '-' . $month . '-' . $day;
                if($result == '0000-00-00') {
                    return null;
                }

                if(!checkdate($month, $day, $year)) {
                    $year = substr($value, 4, 4);
                    $month = substr($value, 2, 2);
                    $day = substr($value, 0, 2);
                    $result = $year . '-' . $month . '-' . $day;
                }
                return $result;
            case 'address-addition':
                return $this->normalizeAddition($value);
            case 'address-letter':
                return $this->normalizeAddition($value, true);
            default:
                throw new \Exception("Unknown type $type");
        }
    }

    protected function normalizeAddition($value, $needLetter = false) {
        if (empty($value)) {
            return null;
        }
        //Single letters -> letter
        elseif (preg_match("-^/?([[:alpha:]])$-", $value, $matches)) {
            return $matches[1];
        }
        //Letter with until number and letter
        elseif (preg_match("#^/?([[:alpha:]])(([\+\-/][[:digit:]]*/?[[:alpha:]]*)+)$#", $value, $matches)) {
            return $needLetter ? $matches[1] : $matches[2];
        }
        elseif (!$needLetter) {
            //Everything starting with - -> addition
            if (preg_match("/^-[\S]*$/", $value, $matches)) {
                return $matches[0];
            }
            //Everything that starts with a digit and followed by letters -> addition
            elseif (preg_match("#^/?(\+?[[:digit:]]+[\S]*)$#", $value, $matches))
            {
                return $matches[1];
            }
            //Multiple letters -> addition
            elseif (preg_match("-^/?([[:alpha:]]{2,100}([\-\+/]?[[:alpha:]]*[[:digit:]]*)*)$-", $value, $matches)) {
                return $matches[1];
            }
            //Multiple letters - numbers -> addition
            elseif (preg_match("#^/?([[:alpha:]]+-[[:digit:]]+)$#", $value, $matches)) {
                return $matches[1];
            }
            //T/M number
            elseif (preg_match("#^/?(T/M-[[:digit:]]+/?[[:alpha:]]*)$#", $value, $matches)) {
                return $matches[1];
            }
        }
    }

    protected function normalizeString($value, $length = null, $encode = true)
    {
        if (empty($value)) {
            return null;
        }

        $encoded = $encode ? utf8_encode($value) : $value;


        return isset($length) ? substr($encoded, 0, $length) : $encoded;
    }
}