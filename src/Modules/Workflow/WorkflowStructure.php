<?php

declare(strict_types=1);

namespace App\Modules\Workflow;

use RuntimeException;

final class WorkflowStructure
{
    public static function orderedIds(array $requested, array $existing): array
    {
        $ids = [];
        foreach ($requested as $value) {
            if ((!is_int($value) && (!is_string($value) || !ctype_digit($value))) || (int) $value < 1) {
                throw new RuntimeException('شناسه مرحله معتبر نیست.');
            }
            $ids[] = (int) $value;
        }
        $expected = array_map('intval', $existing);
        $sorted = $ids;
        sort($sorted);
        sort($expected);
        if ($ids === [] || count(array_unique($ids)) !== count($ids) || $sorted !== $expected) {
            throw new RuntimeException('فهرست مراحل تغییر کرده است؛ صفحه را تازه کنید و همه مراحل را دوباره مرتب کنید.');
        }
        return $ids;
    }
}
