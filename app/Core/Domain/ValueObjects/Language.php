<?php

namespace App\Core\Domain\ValueObjects;

/**
 * The two locales this stage's i18n infrastructure supports. Backed by the
 * same 2-letter code stored in tenants.default_language and every
 * lang/{code}/*.json directory name, so a raw string from a query
 * parameter, an Accept-Language header, or the database can always be
 * turned into one of these via the native ::tryFrom().
 */
enum Language: string
{
    case English = 'en';
    case Persian = 'fa';
}
