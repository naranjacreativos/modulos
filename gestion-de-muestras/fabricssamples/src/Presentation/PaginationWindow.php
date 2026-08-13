<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Presentation;

final class PaginationWindow
{
    /**
     * Builds a compact page list. A zero represents an omitted range.
     *
     * @return list<int>
     */
    public static function build(int $currentPage, int $totalPages, int $radius = 2): array
    {
        $totalPages = max(1, $totalPages);
        $currentPage = min($totalPages, max(1, $currentPage));
        $radius = min(5, max(0, $radius));

        if ($totalPages <= ($radius * 2) + 5) {
            return range(1, $totalPages);
        }

        $pages = [1, $totalPages];
        for ($page = max(2, $currentPage - $radius); $page <= min($totalPages - 1, $currentPage + $radius); ++$page) {
            $pages[] = $page;
        }
        if ($currentPage <= $radius + 3) {
            for ($page = 2; $page <= min($totalPages - 1, ($radius * 2) + 4); ++$page) {
                $pages[] = $page;
            }
        }
        if ($currentPage >= $totalPages - $radius - 2) {
            for ($page = max(2, $totalPages - (($radius * 2) + 3)); $page < $totalPages; ++$page) {
                $pages[] = $page;
            }
        }

        $pages = array_values(array_unique($pages));
        sort($pages);

        $window = [];
        $previous = 0;
        foreach ($pages as $page) {
            if ($previous > 0 && $page - $previous > 1) {
                $window[] = 0;
            }
            $window[] = $page;
            $previous = $page;
        }

        return $window;
    }
}
