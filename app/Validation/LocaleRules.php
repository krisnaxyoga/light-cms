<?php

namespace App\Validation;

use Config\Database;
use Config\Services;

/**
 * Validation rules for per-language content (registered in Config\Validation).
 */
class LocaleRules
{
    /**
     * slug_unique_in_locale[table] — the slug must be unique among rows of
     * the same locale, ignoring the row being edited (its `id` is in $data,
     * same convention as the is_unique[...,id,{id}] placeholder trick), and
     * — for the default language only — may not collide with an active
     * language's URL prefix (or /id would stop meaning "Indonesian home
     * page"). CI4's own is_unique cannot add either check, hence one
     * combined custom rule (a bracketed rule[param] is required to have CI4
     * pass $data through at all — see Validation::processRules()).
     */
    public function slug_unique_in_locale(?string $str, string $table, array $data, ?string &$error = null): bool
    {
        if ($str === null || $str === '') {
            return true; // `required` handles emptiness
        }

        $locale = Services::locale();
        $code   = (string) ($data['locale'] ?? '') ?: $locale->defaultCode();

        if ($code === $locale->defaultCode() && $locale->byPrefix($str) !== null) {
            $error = 'The {field} "' . $str . '" is reserved as a language URL prefix.';

            return false;
        }

        $builder = Database::connect()->table($table)
            ->where('slug', $str)
            ->where('locale', $code);

        if (! empty($data['id'])) {
            $builder->where('id !=', (int) $data['id']);
        }

        if ($builder->countAllResults() > 0) {
            $error = 'The {field} "' . $str . '" is already used by another item in this language.';

            return false;
        }

        return true;
    }
}
