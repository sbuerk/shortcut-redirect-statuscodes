<?php

declare(strict_types=1);

/*
 * This file is part of the "shortcut_redirect_statuscodes" Extension for TYPO3 CMS.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace StefanBuerk\ShortcutRedirectStatuscodes\Tests\Functional\Fixtures\Traits;

trait PermutationMethodsTrait
{
    /**
     * @param array<int|string, mixed> $array
     * @return array<int|string, mixed>
     */
    protected static function wrapInArray(array $array): array
    {
        return array_map(
            static function ($item) {
                return [$item];
            },
            $array
        );
    }

    /**
     * @param array<int|string> $array
     * @return array<int|string>
     */
    protected static function keysFromValues(array $array): array
    {
        return array_combine($array, $array);
    }

    /**
     * Generates key names based on a template and array items as arguments.
     *
     * + keysFromTemplate([[1, 2, 3], [11, 22, 33]], '%1$d->%2$d (user:%3$d)')
     * + returns the following array with generated keys
     *   [
     *     '1->2 (user:3)'    => [1, 2, 3],
     *     '11->22 (user:33)' => [11, 22, 33],
     *   ]
     * @param array<int|string, array<int|string>> $array
     * @return array<string, array<int|string>>
     */
    protected static function keysFromTemplate(array $array, string $template, callable $callback = null): array
    {
        $keys = array_unique(
            array_map(
                static function (array $values) use ($template, $callback) {
                    if ($callback !== null) {
                        $values = $callback($values);
                    }
                    return vsprintf($template, $values);
                },
                $array
            )
        );

        if (count($keys) !== count($array)) {
            throw new \LogicException(
                'Amount of generated keys does not match to item count.',
                1534682840
            );
        }

        return array_combine($keys, $array);
    }
}
