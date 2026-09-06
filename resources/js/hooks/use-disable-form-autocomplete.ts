import { useEffect } from 'react';

/**
 * Stops the browser from offering saved values from other sites.
 *
 * Chrome still treats a field named `name` as a contact name unless the
 * surrounding form is also `autocomplete="off"`. Login and register sit in
 * the auth layout and keep their own field tokens.
 */
export function useDisableFormAutocomplete(): void {
    useEffect(() => {
        const root = document.getElementById('app') ?? document.body;

        const apply = (): void => {
            root.querySelectorAll('form').forEach((form) => {
                if (!form.hasAttribute('autocomplete')) {
                    form.setAttribute('autocomplete', 'off');
                }
            });
        };

        apply();

        const observer = new MutationObserver(apply);
        observer.observe(root, { childList: true, subtree: true });

        return () => observer.disconnect();
    }, []);
}
