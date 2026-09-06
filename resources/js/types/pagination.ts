/**
 * A Laravel length-aware paginator as Inertia serialises it.
 *
 * Only the fields the screens actually read are declared. `prev_page_url` and
 * `next_page_url` are preferred over the `links` array because the array mixes
 * page numbers with the "Previous"/"Next" pseudo-links.
 */
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};
