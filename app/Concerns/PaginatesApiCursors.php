<?php

namespace App\Concerns;

use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;

/**
 * Cursor pages for the public REST API.
 *
 * Offset `page=` is refused on purpose: it slows down as the list grows and
 * can skip or repeat rows when the set is written to between requests.
 */
trait PaginatesApiCursors
{
    protected function cursorPerPage(Request $request): int
    {
        $limit = $request->integer('limit', 50);

        if ($limit < 1) {
            $limit = 50;
        }

        return min($limit, 100);
    }

    /**
     * Laravel already puts `next_cursor` on a resource collection. Adding it
     * again merges the two strings into an array. Only `has_more` is ours.
     *
     * @param  CursorPaginator<int, mixed>  $paginator
     * @return array{has_more: bool}
     */
    protected function cursorMeta(CursorPaginator $paginator): array
    {
        return [
            'has_more' => $paginator->hasMorePages(),
        ];
    }
}
